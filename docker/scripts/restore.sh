#!/usr/bin/env bash
#
# Reads the encrypted backups docker/scripts/backup.sh leaves in Cloudflare R2.
#
#   restore.sh list    <prod|staging>
#       Every backup, newest first. A folder without _complete is FAILED.
#
#   restore.sh check   <prod|staging> [timestamp]
#       Proves a backup (the newest complete one by default) can really be
#       restored, without touching anything live: decrypts it, loads the dump
#       into a throwaway Postgres with no network whose data lives in memory,
#       prints row counts for the key tables, lists the media archive and the
#       config archive's file names, then removes everything. Exits non-zero
#       if any of it fails. Run through `make prod-backup-check`.
#
#   restore.sh decrypt <prod|staging> <timestamp> <database.sql.gz|media.tar.gz|config.tar.gz>
#       Writes one decrypted archive to stdout, for a real restore (below).
#
# The private key (the AGE-SECRET-KEY-1... line) is read from a prompt each time
# and only ever held in this script's memory: never in a file, never in an
# argument. Typing it on the server exposes it to whoever controls that server,
# so if the server may be compromised, run this from a clean machine instead,
# with docker, age and rclone installed and BACKUP_SETTINGS_FILE pointing at a
# copy of the settings.
#
# ── REAL RESTORE ─────────────────────────────────────────────────────────────
# Rehearse with `check` first. Everything below overwrites data, so make sure
# of which stack you are in; `set -o pipefail` so a failed decrypt is not lost.
#
# Database, into an EMPTY database, with the app stopped. Against tables that
# still exist, psql stops at the first one and the transaction changes nothing:
#
#   make prod-down && docker compose -f docker-compose.prod.yml --env-file .env up -d db
#   set -o pipefail; sudo ./docker/scripts/restore.sh decrypt prod <timestamp> database.sql.gz \
#       | gunzip \
#       | docker exec -i hirfaty-db sh -c 'psql -v ON_ERROR_STOP=1 --single-transaction -U "$POSTGRES_USER" "$POSTGRES_DB"'
#
# Photos — stop the `seaweedfs` container and untar into an EMPTY volume, NEVER
# over a volume in use. In the October 2026 incident, copying one SeaweedFS
# volume onto a live one silently destroyed every photo written afterwards:
#
#   docker compose -f docker-compose.prod.yml --env-file .env stop seaweedfs
#   docker run --rm -v hirfaty_seaweedfs_data:/data alpine sh -c 'ls -A /data'   # must print nothing
#   set -o pipefail; sudo ./docker/scripts/restore.sh decrypt prod <timestamp> media.tar.gz \
#       | docker run --rm -i -v hirfaty_seaweedfs_data:/data alpine tar xzf - -C /data
#
# Config, on a new server: decrypt config.tar.gz to read .env and
# docker/seaweedfs/s3-config.json back into a fresh checkout.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail
umask 077
# A core dump of this process would hold the private key.
ulimit -c 0

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source-path=SCRIPTDIR source=backup-lib.sh
. "${SCRIPT_DIR}/backup-lib.sh"

# Must keep the major version of the `db` service in docker-compose.prod.yml.
PG_IMAGE="postgres:16-alpine"
# The decrypted database only ever exists in this much memory, not on disk.
PG_MEMORY="${RESTORE_PG_MEMORY:-1g}"
# Empty in any real backup means the restore did not bring the data back.
MUST_HAVE_ROWS=(users migrations)
KEY_TABLES=(migrations users stores categories products product_variants media orders order_items payments earnings cities)

AGE_KEY=""
PG_CONTAINER=""
WORK=""

cleanup() {
    local status=$?
    trap - EXIT
    AGE_KEY=""
    if [ -n "${PG_CONTAINER}" ]; then
        docker rm -f "${PG_CONTAINER}" >/dev/null 2>&1 || true
    fi
    [ -z "${WORK}" ] || rm -rf -- "${WORK}"
    exit "${status}"
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM HUP

usage() {
    sed -n '3,17p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//' >&2
    exit 2
}

bucket_folder() {
    printf 'r2:%s/%s' "${R2_BUCKET}" "$1"
}

# "<timestamp> <bytes> <complete|FAILED>" per backup, newest first.
backup_table() {
    r2 lsf -R --files-only --format "sp" --separator " " "$(bucket_folder "$1")/" \
        | awk -v marker="${MARKER_NAME}" '
            {
                split($2, part, "/")
                bytes[part[1]] += $1
                if (part[2] == marker) complete[part[1]] = 1
            }
            END {
                for (name in bytes) {
                    print name, bytes[name], (name in complete ? "complete" : "FAILED")
                }
            }' \
        | sort -r
}

command_list() {
    local env="$1" table
    table="$(backup_table "${env}")"
    [ -n "${table}" ] || die "No backups under ${R2_BUCKET}/${env}/."

    printf '%-21s %10s  %s\n' "BACKUP" "SIZE" "STATE"
    while read -r name bytes state; do
        printf '%-21s %10s  %s\n' "${name}" "$(numfmt --to=iec --suffix=B "${bytes}")" "${state}"
    done <<<"${table}"
}

# Checks the requested backup exists and finished, and loads its manifest.
load_manifest() {
    local env="$1"
    TIMESTAMP="$2"
    if [ -z "${TIMESTAMP}" ]; then
        TIMESTAMP="$(backup_table "${env}" | awk '$3 == "complete" && !found { print $1; found = 1 }')"
        [ -n "${TIMESTAMP}" ] || die "No complete backup under ${R2_BUCKET}/${env}/."
    fi
    case "${TIMESTAMP}" in
        [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]_[0-9][0-9]-[0-9][0-9]-[0-9][0-9]) ;;
        *) die "'${TIMESTAMP}' is not a backup name, expected e.g. 2026-10-23_02-30-00." ;;
    esac

    REMOTE="$(bucket_folder "${env}")/${TIMESTAMP}"
    MANIFEST="$(r2 cat "${REMOTE}/${MARKER_NAME}" 2>/dev/null || true)"
    [ -n "${MANIFEST}" ] \
        || die "${env}/${TIMESTAMP} has no ${MARKER_NAME}: that backup failed, do not trust it."
}

manifest_value() {
    sed -n "s/^$1=//p" <<<"${MANIFEST}"
}

manifest_size() {
    awk -v name="$1" '$1 == "object" && $2 == name { print $3 }' <<<"${MANIFEST}"
}

manifest_sha256() {
    awk -v name="$1" '$1 == "object" && $2 == name { print $4 }' <<<"${MANIFEST}"
}

# Read from stdin, which stays the terminal even when stdout feeds a pipe. Only
# the printf builtin touches the key, so it never reaches a process list, and no
# here-string carries it, since older bash backs those with a temporary file.
read_private_key() {
    local derived
    printf 'Paste the age private key (AGE-SECRET-KEY-1..., not shown): ' >&2
    IFS= read -rs AGE_KEY || true
    printf '\n' >&2

    AGE_KEY="${AGE_KEY//[[:space:]]/}"
    case "${AGE_KEY}" in
        AGE-SECRET-KEY-1*) ;;
        *) die "That is not an age private key." ;;
    esac

    # Fail here on the wrong key, not after a long download.
    derived="$(printf '%s\n' "${AGE_KEY}" | age-keygen -y 2>/dev/null)" || die "That is not a valid age private key."
    [ "${derived}" = "$(manifest_value recipient)" ] \
        || die "This key does not open this backup: it was encrypted to $(manifest_value recipient)."
}

# One object from R2, decrypted to stdout. age refuses tampered or truncated
# data by itself; the size and sha256 also prove it is exactly what backup.sh
# sent. The key travels through a pipe made by the shell, not a file.
decrypt_object() {
    local object="$1" base statuses stages=("rclone" "measure" "age") i
    base="${WORK}/${object}"

    set +e
    r2 cat "${REMOTE}/${object}" \
        | measure "${base}" \
        | age --decrypt --identity <(printf '%s\n' "${AGE_KEY}")
    statuses=("${PIPESTATUS[@]}")
    set -e

    for i in "${!statuses[@]}"; do
        if [ "${statuses[i]}" -ne 0 ]; then
            die "${object}: ${stages[i]} exited with status ${statuses[i]}."
        fi
    done

    [ "$(measured_size "${base}")" = "$(manifest_size "${object}")" ] \
        || die "${object}: read $(measured_size "${base}") bytes, the manifest says $(manifest_size "${object}")."
    [ "$(measured_sha256 "${base}")" = "$(manifest_sha256 "${object}")" ] \
        || die "${object}: sha256 differs from the manifest."
}

command_decrypt() {
    local env="$1" timestamp="$2" name="$3"
    case "${name}" in
        database.sql.gz | media.tar.gz | config.tar.gz) ;;
        *) die "Unknown archive '${name}': database.sql.gz, media.tar.gz or config.tar.gz." ;;
    esac
    [ -n "${timestamp}" ] || die "decrypt needs the backup's name, see 'restore.sh list ${env}'."
    [ ! -t 1 ] || die "Refusing to print an archive on the terminal: pipe or redirect it."

    require_command age age-keygen rclone sha256sum
    load_manifest "${env}" "${timestamp}"
    read_private_key
    WORK="$(mktemp -d)"
    decrypt_object "${name}.age"
}

start_throwaway_postgres() {
    PG_CONTAINER="hirfaty-restore-check-$$"

    # --network none: nothing in the dump can reach anything. tmpfs: the
    # decrypted rows are gone with the container. Trust auth is harmless with no
    # network and spares a password.
    docker run --detach --rm --name "${PG_CONTAINER}" \
        --network none \
        --tmpfs "/var/lib/postgresql/data:rw,size=${PG_MEMORY}" \
        -e POSTGRES_USER=restore_check \
        -e POSTGRES_DB=restore_check \
        -e POSTGRES_HOST_AUTH_METHOD=trust \
        "${PG_IMAGE}" >/dev/null

    # TCP on purpose: while initialising, the image runs a temporary server on
    # the socket only, which a plain pg_isready would mistake for ready.
    local tries=0
    until docker exec "${PG_CONTAINER}" pg_isready -q -h 127.0.0.1 -U restore_check; do
        tries=$((tries + 1))
        [ "${tries}" -lt 60 ] || die "The throwaway Postgres did not start."
        sleep 1
    done
}

pg_query() {
    docker exec -i "${PG_CONTAINER}" psql -X -q -t -A -v ON_ERROR_STOP=1 -U restore_check restore_check -c "$1"
}

check_database() {
    local statuses table count stages=("decrypt" "gunzip" "psql") i
    echo
    echo "── Database ─────────────────────────────────"
    start_throwaway_postgres

    set +e
    decrypt_object database.sql.gz.age \
        | gunzip \
        | docker exec -i "${PG_CONTAINER}" \
            psql -X -q -v ON_ERROR_STOP=1 --single-transaction -U restore_check restore_check >/dev/null
    statuses=("${PIPESTATUS[@]}")
    set -e
    for i in "${!statuses[@]}"; do
        if [ "${statuses[i]}" -ne 0 ]; then
            die "The dump did not load: ${stages[i]} exited with status ${statuses[i]}. A database bigger than ${PG_MEMORY} needs RESTORE_PG_MEMORY=4g or so."
        fi
    done

    echo "Loaded into a throwaway Postgres, in one transaction, with no errors."
    printf '%-20s %10s\n' "TABLE" "ROWS"
    for table in "${KEY_TABLES[@]}"; do
        count="$(pg_query "SELECT count(*) FROM \"${table}\"")" \
            || die "Table '${table}' is missing from the dump."
        printf '%-20s %10s\n' "${table}" "${count}"
    done

    for table in "${MUST_HAVE_ROWS[@]}"; do
        [ "$(pg_query "SELECT count(*) FROM \"${table}\"")" -gt 0 ] \
            || die "Table '${table}' is empty: this dump is not usable."
    done
}

check_media() {
    local statuses listing stages=("decrypt" "tar") i dat_files bytes
    echo
    echo "── Media archive ────────────────────────────"
    listing="${WORK}/media.list"

    set +e
    decrypt_object media.tar.gz.age | tar tzvf - >"${listing}"
    statuses=("${PIPESTATUS[@]}")
    set -e
    for i in "${!statuses[@]}"; do
        if [ "${statuses[i]}" -ne 0 ]; then
            die "The media archive did not read: ${stages[i]} exited with status ${statuses[i]}."
        fi
    done

    cat "${listing}"
    bytes="$(awk '{ total += $3 } END { print total + 0 }' "${listing}")"
    # The photos are in the volume's .dat files; no .dat means no photos.
    dat_files="$(grep -c '\.dat$' "${listing}" || true)"
    echo "$(wc -l <"${listing}") entries, ${dat_files} volume file(s), $(numfmt --to=iec --suffix=B "${bytes}") unpacked."
    [ "${dat_files}" -gt 0 ] || die "The media archive holds no .dat volume file, so no photos."
}

check_config() {
    local statuses listing stages=("decrypt" "tar") i file
    echo
    echo "── Config archive (names only) ──────────────"
    listing="${WORK}/config.list"

    set +e
    decrypt_object config.tar.gz.age | tar tzf - >"${listing}"
    statuses=("${PIPESTATUS[@]}")
    set -e
    for i in "${!statuses[@]}"; do
        if [ "${statuses[i]}" -ne 0 ]; then
            die "The config archive did not read: ${stages[i]} exited with status ${statuses[i]}."
        fi
    done

    cat "${listing}"
    for file in .env docker/seaweedfs/s3-config.json; do
        grep -qxF "${file}" "${listing}" || die "The config archive is missing ${file}."
    done
}

command_check() {
    local env="$1" timestamp="$2"
    require_command age age-keygen rclone docker sha256sum tar numfmt
    load_manifest "${env}" "${timestamp}"
    echo "Checking ${env}/${TIMESTAMP}"
    read_private_key
    WORK="$(mktemp -d)"

    check_database
    check_media
    check_config

    echo
    echo "RESTORE CHECK PASSED: ${env}/${TIMESTAMP}. Size and sha256 of every object match its ${MARKER_NAME}. The throwaway database is being removed."
}

[ "$#" -ge 2 ] || usage
command_name="$1"
env_name="$2"
require_env "${env_name}"
read_settings
validate_settings

case "${command_name}" in
    list)
        require_command rclone numfmt
        command_list "${env_name}"
        ;;
    check)
        command_check "${env_name}" "${3:-}"
        ;;
    decrypt)
        [ "$#" -eq 4 ] || usage
        command_decrypt "${env_name}" "$3" "$4"
        ;;
    *)
        usage
        ;;
esac
