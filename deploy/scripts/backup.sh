#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/bbb/current}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/bbb}"
MYSQL_CNF="${MYSQL_CNF:-/etc/bbb/mysql-backup.cnf}"
DB_NAME="${DB_NAME:-bbb}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "${BACKUP_DIR}/database" "${BACKUP_DIR}/storage"
umask 077

if [ ! -r "${MYSQL_CNF}" ]; then
    echo "Missing readable MySQL defaults file: ${MYSQL_CNF}" >&2
    exit 2
fi

mysqldump \
    --defaults-extra-file="${MYSQL_CNF}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    "${DB_NAME}" | gzip -9 > "${BACKUP_DIR}/database/${DB_NAME}-${STAMP}.sql.gz"

tar \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    -C "${APP_DIR}" \
    -czf "${BACKUP_DIR}/storage/bbb-storage-${STAMP}.tar.gz" \
    storage/app public

find "${BACKUP_DIR}/database" -type f -name '*.sql.gz' -mtime +"${RETENTION_DAYS}" -delete
find "${BACKUP_DIR}/storage" -type f -name '*.tar.gz' -mtime +"${RETENTION_DAYS}" -delete

echo "Backup completed: ${STAMP}"
