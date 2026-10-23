#!/usr/bin/env bash
set -euo pipefail

# One backup = one dated folder holding both halves of the site, so a restore
# always pairs a database with the photos it points to:
#
#   /var/backups/hirfaty/2026-10-23_02-30-00/
#       database.sql.gz   pg_dump of the db container
#       media.tar.gz      the SeaweedFS volume — every product, variant,
#                         store and blog image (its .dat/.idx files and the
#                         filer's index are the backup; SeaweedFS has no
#                         pg_dump of its own)
#
# Keeps the newest KEEP folders (30 by default — a month of nightly runs).
# Scheduled by docker/systemd/hirfaty-backup.timer through `make prod-backup`.
#
# Both halves are taken hot: pg_dump is consistent on its own, and the media
# volume is mounted read-only into a throwaway container and tarred while
# SeaweedFS keeps running. A photo uploaded during the few seconds between
# the two may be in one half and not the other.
#
# Restore, roughly: `gunzip -c database.sql.gz | docker exec -i hirfaty-db
# psql -U <user> <db>` into an empty database, and for the photos stop the
# `seaweedfs` container and untar media.tar.gz into an EMPTY volume — never
# over a volume in use (see the October 2026 incident: copying one SeaweedFS
# volume onto a live one silently destroyed every photo written afterwards).
#
# Usage: ./docker/scripts/backup.sh
# Env overrides: BACKUP_DIR, KEEP, DB_CONTAINER, VOLUME, MIN_FREE_MB

DB_CONTAINER="${DB_CONTAINER:-hirfaty-db}"
VOLUME="${VOLUME:-hirfaty_seaweedfs_data}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/hirfaty}"
KEEP="${KEEP:-30}"
# Refuse to start rather than fill the disk SeaweedFS and Postgres write to.
MIN_FREE_MB="${MIN_FREE_MB:-2048}"

TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
DEST="${BACKUP_DIR}/${TIMESTAMP}"
# Written under .partial and renamed once both halves are verified, so an
# interrupted run never leaves a folder that looks complete.
WORK="${DEST}.partial"

if ! docker ps --format '{{.Names}}' | grep -qx "${DB_CONTAINER}"; then
    echo "Container '${DB_CONTAINER}' is not running — aborting backup." >&2
    exit 1
fi

if ! docker volume inspect "${VOLUME}" >/dev/null 2>&1; then
    echo "Volume '${VOLUME}' does not exist — aborting backup." >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

free_mb="$(df -Pm "${BACKUP_DIR}" | awk 'NR == 2 { print $4 }')"
if [ "${free_mb}" -lt "${MIN_FREE_MB}" ]; then
    echo "Only ${free_mb} MB free under ${BACKUP_DIR} (need ${MIN_FREE_MB}) — aborting backup." >&2
    exit 1
fi

# Leftovers of runs that were killed half-way.
find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -name '*.partial' -exec rm -rf -- {} +

mkdir "${WORK}"
trap 'rm -rf -- "${WORK}"' EXIT

docker exec "${DB_CONTAINER}" sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "${WORK}/database.sql.gz"

docker run --rm -v "${VOLUME}:/data:ro" alpine \
    tar czf - -C /data . > "${WORK}/media.tar.gz"

# A truncated archive fails here instead of sitting unnoticed until the day
# it is needed.
gzip -t "${WORK}/database.sql.gz"
gzip -t "${WORK}/media.tar.gz"

mv "${WORK}" "${DEST}"
trap - EXIT

# Rotate only after the new backup is safely in place. Folder names sort
# chronologically, newest first under `sort -r`; anything else in the
# directory (the older flat hirfaty-*.sql.gz files, say) is left alone.
find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -name '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]_*' \
    | sort -r | tail -n "+$((KEEP + 1))" | xargs -r rm -rv --

kept="$(find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -name '[0-9][0-9][0-9][0-9]-*' | wc -l)"
echo "Backed up to ${DEST} ($(du -sh "${DEST}" | cut -f1)) — ${kept} backup(s) kept, $(du -sh "${BACKUP_DIR}" | cut -f1) in total."
