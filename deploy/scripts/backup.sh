#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/bbb/current}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/bbb}"
BACKUP_ARTIFACT_DIR="${BACKUP_ARTIFACT_DIR:-${BACKUP_DIR}/evidence}"
MYSQL_CNF="${MYSQL_CNF:-/etc/bbb/mysql-backup.cnf}"
DB_NAME="${DB_NAME:-bbb}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DB_ARCHIVE="${BACKUP_DIR}/database/${DB_NAME}-${STAMP}.sql.gz"
STORAGE_ARCHIVE="${BACKUP_DIR}/storage/bbb-storage-${STAMP}.tar.gz"
LOG_FILE="${BACKUP_ARTIFACT_DIR}/b2b-backup-${STAMP}.log"
HASH_FILE="${BACKUP_ARTIFACT_DIR}/b2b-backup-${STAMP}.sha256"

umask 077
mkdir -p "${BACKUP_DIR}/database" "${BACKUP_DIR}/storage" "${BACKUP_ARTIFACT_DIR}"

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

    echo "Missing sha256sum or shasum for backup evidence hashing." >&2
    exit 7
}

if [ ! -r "${MYSQL_CNF}" ]; then
    echo "Missing readable MySQL defaults file: ${MYSQL_CNF}" >&2
    exit 2
fi

log "Starting BBB B2B backup ${STAMP}"
log "app_dir=${APP_DIR}"
log "db_name=${DB_NAME}"
log "backup_dir=${BACKUP_DIR}"

mysqldump \
    --defaults-extra-file="${MYSQL_CNF}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    "${DB_NAME}" | gzip -9 > "${DB_ARCHIVE}"
log "PASS database_archive ${DB_ARCHIVE}"

tar \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    -C "${APP_DIR}" \
    -czf "${STORAGE_ARCHIVE}" \
    storage/app public
log "PASS storage_archive ${STORAGE_ARCHIVE}"

DB_SHA256="$(sha256_value "${DB_ARCHIVE}")"
STORAGE_SHA256="$(sha256_value "${STORAGE_ARCHIVE}")"
{
    printf '%s  %s\n' "${DB_SHA256}" "${DB_ARCHIVE}"
    printf '%s  %s\n' "${STORAGE_SHA256}" "${STORAGE_ARCHIVE}"
} > "${HASH_FILE}"
log "PASS database_sha256 ${DB_SHA256}"
log "PASS storage_sha256 ${STORAGE_SHA256}"
log "PASS hash_manifest ${HASH_FILE}"

find "${BACKUP_DIR}/database" -type f -name '*.sql.gz' -mtime +"${RETENTION_DAYS}" -delete
find "${BACKUP_DIR}/storage" -type f -name '*.tar.gz' -mtime +"${RETENTION_DAYS}" -delete
log "PASS retention_cleanup days=${RETENTION_DAYS}"

log "Backup completed: ${STAMP}. Artifact log: ${LOG_FILE}"
echo "Backup completed: ${STAMP}. Artifact log: ${LOG_FILE}. Hash manifest: ${HASH_FILE}"
