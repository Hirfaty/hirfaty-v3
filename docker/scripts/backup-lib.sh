#!/usr/bin/env bash
# What docker/scripts/backup.sh and restore.sh share: reading the settings,
# talking to R2, measuring a stream. Sourced, never run.

# Root-only, outside every checkout, and deliberately not the app's .env: that
# file is injected into the containers, and these credentials must not be.
BACKUP_SETTINGS_FILE="${BACKUP_SETTINGS_FILE:-/etc/hirfaty/backup.env}"

# Names the "this folder finished" file. A folder without it is a failed run.
# shellcheck disable=SC2034 # read by the scripts sourcing this
MARKER_NAME="_complete"

# The three objects of a backup, in the order they are written.
# shellcheck disable=SC2034 # read by the scripts sourcing this
BACKUP_OBJECTS=(database.sql.gz.age media.tar.gz.age config.tar.gz.age)

die() {
    echo "BACKUP ERROR: $*" >&2
    exit 1
}

require_command() {
    local name
    for name in "$@"; do
        command -v "${name}" >/dev/null 2>&1 || die "'${name}' is not installed."
    done
}

read_settings() {
    [ -f "${BACKUP_SETTINGS_FILE}" ] \
        || die "Cannot find ${BACKUP_SETTINGS_FILE}. Run this with sudo; if it is really missing, create it from docker/scripts/backup.env.example."
    [ -r "${BACKUP_SETTINGS_FILE}" ] \
        || die "Cannot read ${BACKUP_SETTINGS_FILE}: it is root-only, run this with sudo."

    # It is about to be sourced and it holds the R2 credentials, so it must
    # belong to whoever is running this and be closed to everyone else.
    local mode owner
    mode="$(stat -c '%a' "${BACKUP_SETTINGS_FILE}")"
    owner="$(stat -c '%u' "${BACKUP_SETTINGS_FILE}")"
    [ "${owner}" = "$(id -u)" ] \
        || die "${BACKUP_SETTINGS_FILE} must belong to the user running this (uid $(id -u)), not uid ${owner}."
    [ $((8#${mode} & 8#077)) -eq 0 ] \
        || die "${BACKUP_SETTINGS_FILE} has mode ${mode}: run 'chmod 600 ${BACKUP_SETTINGS_FILE}'."

    # The whole point of public-key encryption is that this server never holds
    # the key that opens the backups.
    if grep -q 'AGE-SECRET-KEY-' "${BACKUP_SETTINGS_FILE}"; then
        die "${BACKUP_SETTINGS_FILE} contains an age PRIVATE key. Keep it offline: only the public key (age1...) belongs on this server."
    fi

    # shellcheck source=/dev/null
    . "${BACKUP_SETTINGS_FILE}"
}

# Every setting is checked here so a mistake reads as one clear line rather
# than as whichever tool trips over an empty value first.
validate_settings() {
    local name
    for name in R2_ENDPOINT R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_BUCKET AGE_RECIPIENT; do
        [ -n "${!name:-}" ] || die "${name} is not set in ${BACKUP_SETTINGS_FILE}."
    done
    case "${AGE_RECIPIENT}" in
        age1*) ;;
        *) die "AGE_RECIPIENT in ${BACKUP_SETTINGS_FILE} must be an age public key (age1...)." ;;
    esac
}

# prod and staging share the bucket and the settings file, under their own
# prefix. Exact names only, so a typo can never open a third folder.
require_env() {
    case "$1" in
        prod | staging) ;;
        *) die "The environment must be 'prod' or 'staging', got '$1'." ;;
    esac
}

# rclone with the R2 credentials in its own environment only: never on a
# command line (where `ps` shows them), never in a config file, and not visible
# to age, tar or docker. no_check_bucket because the token is scoped to one
# bucket and has no right to look for it or create it.
r2() {
    RCLONE_CONFIG="/dev/null" \
    RCLONE_CONFIG_R2_TYPE="s3" \
    RCLONE_CONFIG_R2_PROVIDER="Cloudflare" \
    RCLONE_CONFIG_R2_REGION="auto" \
    RCLONE_CONFIG_R2_ACL="private" \
    RCLONE_CONFIG_R2_ENDPOINT="${R2_ENDPOINT}" \
    RCLONE_CONFIG_R2_ACCESS_KEY_ID="${R2_ACCESS_KEY_ID}" \
    RCLONE_CONFIG_R2_SECRET_ACCESS_KEY="${R2_SECRET_ACCESS_KEY}" \
    RCLONE_CONFIG_R2_NO_CHECK_BUCKET="true" \
        rclone "$@"
}

# A filter: stdin to stdout unchanged, leaving the stream's sha256 in
# <base>.sha256 and its length in <base>.size. Finished writing them by the time
# it exits, so they can be read straight after the pipeline it sits in.
measure() {
    local base="$1" hash_pid size_pid status
    mkfifo "${base}.hash.fifo" "${base}.size.fifo"
    sha256sum <"${base}.hash.fifo" >"${base}.sha256" &
    hash_pid=$!
    wc -c <"${base}.size.fifo" >"${base}.size" &
    size_pid=$!
    tee "${base}.hash.fifo" "${base}.size.fifo"
    status=$?
    wait "${hash_pid}" "${size_pid}"
    return "${status}"
}

measured_sha256() {
    cut -d' ' -f1 "$1.sha256"
}

measured_size() {
    tr -d ' ' <"$1.size"
}

# Remote sizes of one folder, as "<size> <name>" lines.
remote_sizes() {
    r2 lsf --files-only --format "sp" --separator " " "$1"
}
