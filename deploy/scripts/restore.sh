#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/bbb/current}"
MYSQL_CNF="${MYSQL_CNF:-/etc/bbb/mysql-backup.cnf}"
DB_NAME="${DB_NAME:-bbb}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
DB_BACKUP="${1:-}"
STORAGE_BACKUP="${2:-}"

if [ "${CONFIRM_RESTORE:-}" != "RESTORE_BBB" ]; then
    echo "Set CONFIRM_RESTORE=RESTORE_BBB to run a destructive restore." >&2
    exit 2
fi

if [ -z "${DB_BACKUP}" ]; then
    echo "Usage: CONFIRM_RESTORE=RESTORE_BBB restore.sh /path/to/db.sql.gz [/path/to/storage.tar.gz]" >&2
    exit 2
fi

if [ ! -r "${MYSQL_CNF}" ]; then
    echo "Missing readable MySQL defaults file: ${MYSQL_CNF}" >&2
    exit 3
fi

if [ ! -r "${DB_BACKUP}" ]; then
    echo "Missing readable database backup: ${DB_BACKUP}" >&2
    exit 4
fi

case "${DB_BACKUP}" in
    *.sql.gz) ;;
    *)
        echo "Database backup must be a gzip-compressed SQL dump (*.sql.gz)." >&2
        exit 5
        ;;
esac

if [ -n "${STORAGE_BACKUP}" ] && [ ! -r "${STORAGE_BACKUP}" ]; then
    echo "Missing readable storage backup: ${STORAGE_BACKUP}" >&2
    exit 6
fi

cd "${APP_DIR}"

"${PHP_BIN}" artisan down --retry=60 --no-interaction || true
restore_up() {
    "${PHP_BIN}" artisan up --no-interaction || true
}
trap restore_up EXIT

gzip -dc "${DB_BACKUP}" | mysql \
    --defaults-extra-file="${MYSQL_CNF}" \
    --default-character-set=utf8mb4 \
    "${DB_NAME}"

if [ -n "${STORAGE_BACKUP}" ]; then
    tar -xzf "${STORAGE_BACKUP}" -C "${APP_DIR}" storage/app public
fi

"${PHP_BIN}" artisan optimize:clear --no-interaction
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction

echo "Restore completed from ${DB_BACKUP}"
