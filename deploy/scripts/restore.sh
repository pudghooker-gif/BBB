#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/bbb/current}"
MYSQL_CNF="${MYSQL_CNF:-/etc/bbb/mysql-backup.cnf}"
DB_NAME="${DB_NAME:-bbb}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
DB_BACKUP="${1:-}"
STORAGE_BACKUP="${2:-}"
RESTORE_ARTIFACT_DIR="${RESTORE_ARTIFACT_DIR:-${APP_DIR}/storage/logs}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="${RESTORE_ARTIFACT_DIR}/b2b-restore-${STAMP}.log"
RELEASE_CHECK_LOG="${RESTORE_ARTIFACT_DIR}/b2b-restore-release-check-${STAMP}.log"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "${LOG_FILE}"
}

sha256_value() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
        return
    fi

    if command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$1" | awk '{print $1}'
        return
    fi

    echo "Missing sha256sum or shasum for restore evidence hashing." >&2
    exit 7
}

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

mkdir -p "${RESTORE_ARTIFACT_DIR}"
log "Starting BBB B2B restore rehearsal ${STAMP}"
log "app_dir=${APP_DIR}"
log "db_name=${DB_NAME}"
log "db_backup=${DB_BACKUP}"
log "db_backup_sha256=$(sha256_value "${DB_BACKUP}")"
if [ -n "${STORAGE_BACKUP}" ]; then
    log "storage_backup=${STORAGE_BACKUP}"
    log "storage_backup_sha256=$(sha256_value "${STORAGE_BACKUP}")"
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
log "PASS database_restore ${DB_BACKUP}"

if [ -n "${STORAGE_BACKUP}" ]; then
    tar -xzf "${STORAGE_BACKUP}" -C "${APP_DIR}" storage/app public
    log "PASS storage_restore ${STORAGE_BACKUP}"
fi

"${PHP_BIN}" artisan optimize:clear --no-interaction
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction >"${RELEASE_CHECK_LOG}"
log "PASS release_check ${RELEASE_CHECK_LOG}"

log "Restore completed from ${DB_BACKUP}. Artifact log: ${LOG_FILE}"
echo "Restore completed from ${DB_BACKUP}. Artifact log: ${LOG_FILE}"
