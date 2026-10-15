#!/usr/bin/env bash
set -euo pipefail

# Dumps the production Postgres database and rotates old dumps. Driven by
# systemd (docker/systemd/hirfaty-backup.timer) rather than reading .env
# itself — pg_dump runs *inside* the `db` container using the
# POSTGRES_USER/POSTGRES_DB env vars docker-compose.prod.yml already sets
# there, so this script never has to know the credentials.
#
# Usage: ./docker/scripts/backup-db.sh
# Env overrides: BACKUP_DIR, KEEP, CONTAINER

CONTAINER="${CONTAINER:-hirfaty-db}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/hirfaty}"
KEEP="${KEEP:-2}"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
DEST="${BACKUP_DIR}/hirfaty-${TIMESTAMP}.sql.gz"
TMP_DEST="${DEST}.tmp"

if ! docker ps --format '{{.Names}}' | grep -qx "${CONTAINER}"; then
    echo "Container '${CONTAINER}' is not running — aborting backup." >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

# Rotate before writing: keep only the newest KEEP-1 dumps, so the run ends
# with exactly KEEP on disk (day 3 deletes day 1, then writes day 3). Done
# after the container check, so a stopped database never costs a backup.
# Timestamped names sort chronologically, newest first under `sort -r`.
find "${BACKUP_DIR}" -maxdepth 1 -name 'hirfaty-*.sql.gz' | sort -r \
    | tail -n "+${KEEP}" | xargs -r rm -v --

# Dump to a .tmp path first and rename on success, so a crashed/killed dump
# never leaves a truncated file behind under the real name.
docker exec "${CONTAINER}" sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "${TMP_DEST}"
mv "${TMP_DEST}" "${DEST}"

echo "Backed up to ${DEST} ($(du -h "${DEST}" | cut -f1))"
