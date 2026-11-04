#!/usr/bin/env bash
#
# One backup = one dated folder in Cloudflare R2 holding everything needed to
# rebuild the site on a new server, encrypted on this machine before it leaves:
#
#   <bucket>/<env>/2026-10-23_02-30-00/
#       database.sql.gz.age   pg_dump of the db container
#       media.tar.gz.age      the SeaweedFS volume — every product, variant,
#                             store and blog image (its .dat/.idx files and the
#                             filer's index are the backup; SeaweedFS has no
#                             pg_dump of its own)
#       config.tar.gz.age     this checkout's .env and
#                             docker/seaweedfs/s3-config.json, which git does
#                             not hold and a dead server would take with it
#       _complete             written last, only if everything above succeeded
#
# THE RULE: a folder without _complete is a failed backup, whatever else is in
# it. A pg_dump dying half-way still leaves a valid-looking object, because the
# upload only ever sees the pipe close. _complete holds the size and sha256 of
# each ciphertext, which restore.sh checks.
#
# The dumps hold every buyer's name, address, phone and password hash, so
# nothing readable and no archive is ever written to this disk, and this server
# holds only the age PUBLIC key: whoever breaks in cannot read past backups.
# The private key stays offline with the owner.
#
# Retention is not this script's job, on purpose: the R2 token here cannot be
# trusted to manage deletion, since a stolen one must not be able to erase the
# backups. The bucket has a 30-day bucket lock and a lifecycle rule deleting
# objects at 35 days. That rule keeps deleting if this script stops running, so
# a missed night has to be noticed: set BACKUP_HEARTBEAT_URL.
#
# Both halves are taken hot: pg_dump is consistent on its own, and the media
# volume is mounted read-only into a throwaway container and tarred while
# SeaweedFS keeps running. A photo uploaded during the few seconds between the
# two may be in one half and not the other.
#
# To restore, see docker/scripts/restore.sh — and never untar media over a
# volume in use (the October 2026 incident).
#
# Usage: BACKUP_ENV=prod ./docker/scripts/backup.sh  (as root)
# Normally through `make prod-backup`, scheduled by hirfaty-backup.timer.
# Settings: /etc/hirfaty/backup.env, see docker/scripts/backup.env.example.
# Env overrides: DB_CONTAINER, VOLUME.

set -euo pipefail
umask 077

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHECKOUT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
# shellcheck source-path=SCRIPTDIR source=backup-lib.sh
. "${SCRIPT_DIR}/backup-lib.sh"

# A streamed upload cannot know its size up front, so it is cut into parts of
# this size and S3 allows 10,000 parts: 32 MiB gives 312 GiB, more than this
# server's disks hold, where rclone's 5 MiB default stops at 48 GiB. Each part
# being uploaded is buffered in memory, hence only 2 at a time (~64 MiB).
CHUNK_SIZE="32M"
UPLOAD_CONCURRENCY="2"

HEARTBEAT_URL=""
WORK=""

ping_heartbeat() {
    [ -n "${HEARTBEAT_URL}" ] || return 0
    # Never lets a monitoring hiccup change the outcome of the backup.
    curl --fail --silent --show-error --max-time 10 --retry 3 "$1" >/dev/null \
        || echo "Could not reach the backup heartbeat URL." >&2
}

finish() {
    local status=$?
    trap - EXIT
    [ -z "${WORK}" ] || rm -rf -- "${WORK}"
    if [ "${status}" -ne 0 ]; then
        echo "BACKUP FAILED (exit ${status}). Any folder left in R2 without ${MARKER_NAME} is unusable; the bucket's lifecycle rule removes it." >&2
        ping_heartbeat "${HEARTBEAT_URL%/}/fail"
    fi
    exit "${status}"
}

trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM HUP

[ -n "${BACKUP_ENV:-}" ] || die "BACKUP_ENV is not set (prod or staging). Use 'make prod-backup' or 'make stage-backup'."
require_env "${BACKUP_ENV}"
read_settings

# Per environment, so a staging run by hand can never check in for production
# and hide that production's backups stopped.
case "${BACKUP_ENV}" in
    prod)
        DB_CONTAINER="${DB_CONTAINER:-hirfaty-db}"
        VOLUME="${VOLUME:-hirfaty_seaweedfs_data}"
        HEARTBEAT_URL="${BACKUP_HEARTBEAT_URL:-}"
        ;;
    staging)
        DB_CONTAINER="${DB_CONTAINER:-hirfaty-staging-db}"
        VOLUME="${VOLUME:-hirfaty-staging_seaweedfs_data}"
        HEARTBEAT_URL="${BACKUP_HEARTBEAT_URL_STAGING:-}"
        ;;
esac

# From here on a failure also reaches the heartbeat, a bad setting included.
validate_settings

require_command age rclone docker gzip sha256sum tar
[ -z "${HEARTBEAT_URL}" ] || require_command curl

[ "$(docker inspect --format '{{.State.Running}}' "${DB_CONTAINER}" 2>/dev/null)" = "true" ] \
    || die "Container '${DB_CONTAINER}' is not running."
docker volume inspect "${VOLUME}" >/dev/null 2>&1 \
    || die "Volume '${VOLUME}' does not exist."
for file in .env docker/seaweedfs/s3-config.json; do
    [ -f "${CHECKOUT}/${file}" ] || die "${CHECKOUT}/${file} does not exist."
done

TIMESTAMP="$(date -u +%Y-%m-%d_%H-%M-%S)"
REMOTE="r2:${R2_BUCKET}/${BACKUP_ENV}/${TIMESTAMP}"
# Only the hash and size of each ciphertext and the pipes that carry them.
WORK="$(mktemp -d)"

# The role and grants are left out so the dump also loads into a server whose
# database user is named differently; the app only ever has the one owner.
produce_database() {
    # shellcheck disable=SC2016 # expanded by the shell inside the container
    docker exec "${DB_CONTAINER}" sh -c \
        'pg_dump --no-owner --no-privileges -U "$POSTGRES_USER" "$POSTGRES_DB"' \
        | gzip
}

produce_media() {
    docker run --rm --network none -v "${VOLUME}:/data:ro" alpine \
        tar czf - -C /data .
}

produce_config() {
    tar czf - -C "${CHECKOUT}" .env docker/seaweedfs/s3-config.json
}

# Runs one producer into R2 and checks every stage of the pipe. `set -e` is off
# for the pipeline alone: it would otherwise exit before PIPESTATUS can be read,
# and the message naming the stage that broke is what makes a failure fixable.
upload() {
    local producer="$1" object="$2" statuses stages i
    stages=("${producer}" age measure rclone)
    echo "Uploading ${object}..."

    set +e
    "${producer}" \
        | age --encrypt --recipient "${AGE_RECIPIENT}" \
        | measure "${WORK}/${object}" \
        | r2 rcat --stats 0 \
            --s3-chunk-size "${CHUNK_SIZE}" \
            --s3-upload-concurrency "${UPLOAD_CONCURRENCY}" \
            "${REMOTE}/${object}"
    statuses=("${PIPESTATUS[@]}")
    set -e

    for i in "${!statuses[@]}"; do
        if [ "${statuses[i]}" -ne 0 ]; then
            die "${object}: ${stages[i]} exited with status ${statuses[i]}."
        fi
    done
}

upload produce_database database.sql.gz.age
upload produce_media media.tar.gz.age
upload produce_config config.tar.gz.age

# Build the manifest and compare with what R2 actually holds before vouching
# for any of it.
remote_listing="$(remote_sizes "${REMOTE}/")"
manifest="$(
    printf '# Hirfaty backup. A folder without this file is a failed backup.\n'
    printf 'format=1\nenv=%s\ntimestamp=%s\nrecipient=%s\n' \
        "${BACKUP_ENV}" "${TIMESTAMP}" "${AGE_RECIPIENT}"
    printf '# object <name> <ciphertext bytes> <ciphertext sha256>\n'
    for object in "${BACKUP_OBJECTS[@]}"; do
        printf 'object %s %s %s\n' "${object}" \
            "$(measured_size "${WORK}/${object}")" "$(measured_sha256 "${WORK}/${object}")"
    done
)"

for object in "${BACKUP_OBJECTS[@]}"; do
    sent="$(measured_size "${WORK}/${object}")"
    stored="$(awk -v name="${object}" '$2 == name { print $1 }' <<<"${remote_listing}")"
    [ "${sent}" = "${stored}" ] \
        || die "${object}: sent ${sent} bytes but R2 reports '${stored}'."
done

printf '%s\n' "${manifest}" | r2 rcat --stats 0 "${REMOTE}/${MARKER_NAME}"

# The marker is what makes this backup count, so make sure it landed too.
[ "$(r2 cat "${REMOTE}/${MARKER_NAME}")" = "${manifest}" ] \
    || die "${MARKER_NAME} did not read back as written."

echo "Backed up to ${REMOTE}:"
for object in "${BACKUP_OBJECTS[@]}"; do
    echo "  ${object}  $(measured_size "${WORK}/${object}") bytes"
done

ping_heartbeat "${HEARTBEAT_URL}"
