#!/usr/bin/env bash
set -euo pipefail

# Archives the SeaweedFS data volume (product/store media: images, videos,
# logos, banners) and rotates old archives. Shaped like backup-db.sh — same
# tmp-then-rename safety, same retention pruning — but works against a
# Docker *volume* rather than a running service's own dump tool: SeaweedFS
# has no equivalent of `pg_dump`, so the volume's on-disk layout (master
# metadata, .dat/.idx files) *is* the backup.
#
# Runs a throwaway container that mounts the volume read-only and streams a
# gzipped tar of it to stdout — the running seaweedfs service is never
# stopped, so this is a "hot" backup. A blob mid-write at the instant tar
# reads it could come out truncated; acceptable for an ad-hoc/nightly backup,
# not a substitute for a true point-in-time snapshot if that's ever needed.
#
# Usage: ./docker/scripts/backup-media.sh
# Env overrides: BACKUP_DIR, RETENTION_DAYS, VOLUME

VOLUME="${VOLUME:-hirfaty_seaweedfs_data}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/hirfaty}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
DEST="${BACKUP_DIR}/hirfaty-media-${TIMESTAMP}.tar.gz"
TMP_DEST="${DEST}.tmp"

if ! docker volume inspect "${VOLUME}" >/dev/null 2>&1; then
    echo "Volume '${VOLUME}' does not exist — aborting backup." >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

# Archive to a .tmp path first and rename on success, so a crashed/killed
# archive never leaves a truncated file behind under the real name.
docker run --rm -v "${VOLUME}:/data:ro" alpine \
    tar czf - -C /data . > "${TMP_DEST}"
mv "${TMP_DEST}" "${DEST}"

echo "Backed up to ${DEST} ($(du -h "${DEST}" | cut -f1))"

# Prune anything past the retention window.
find "${BACKUP_DIR}" -maxdepth 1 -name 'hirfaty-media-*.tar.gz' -mtime "+${RETENTION_DAYS}" -print -delete
