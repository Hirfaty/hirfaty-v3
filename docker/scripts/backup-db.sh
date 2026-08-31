#!/usr/bin/env bash
set -euo pipefail

# Dumps the production Postgres database and rotates old dumps. Driven by
# systemd (docker/systemd/hirfaty-backup.timer) rather than reading .env
# itself — pg_dump runs *inside* the `db` container using the
# POSTGRES_USER/POSTGRES_DB env vars docker-compose.prod.yml already sets
# there, so this script never has to know the credentials.
#
# Usage: ./docker/scripts/backup-db.sh
# Env overrides: BACKUP_DIR, RETENTION_DAYS, CONTAINER

CONTAINER="${CONTAINER:-hirfaty-db}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/hirfaty}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
DEST="${BACKUP_DIR}/hirfaty-${TIMESTAMP}.sql.gz"
TMP_DEST="${DEST}.tmp"

if ! docker ps --format '{{.Names}}' | grep -qx "${CONTAINER}"; then
    echo "Container '${CONTAINER}' is not running — aborting backup." >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

# Dump to a .tmp path first and rename on success, so a crashed/killed dump
# never leaves a truncated file behind under the real name.
docker exec "${CONTAINER}" sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "${TMP_DEST}"
mv "${TMP_DEST}" "${DEST}"

echo "Backed up to ${DEST} ($(du -h "${DEST}" | cut -f1))"

# Prune anything past the retention window.
find "${BACKUP_DIR}" -maxdepth 1 -name 'hirfaty-*.sql.gz' -mtime "+${RETENTION_DAYS}" -print -delete
