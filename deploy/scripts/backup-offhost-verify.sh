#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/bbb}"
BACKUP_ARTIFACT_DIR="${BACKUP_ARTIFACT_DIR:-${BACKUP_DIR}/evidence}"
BACKUP_HASH_FILE="${BACKUP_HASH_FILE:-}"
OFFHOST_BACKUP_DIR="${OFFHOST_BACKUP_DIR:-}"
BACKUP_OFFHOST_ARTIFACT="${BACKUP_OFFHOST_ARTIFACT:-${BACKUP_ARTIFACT_DIR}/backup-and-offhost-storage-verification.log}"

mkdir -p "$(dirname "$BACKUP_OFFHOST_ARTIFACT")"
: > "$BACKUP_OFFHOST_ARTIFACT"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$BACKUP_OFFHOST_ARTIFACT"
}

fail() {
    log "FAIL $*"
    exit 1
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

    fail "Missing sha256sum or shasum for off-host backup verification."
}

latest_hash_manifest() {
    find "$BACKUP_ARTIFACT_DIR" -maxdepth 1 -type f -name 'b2b-backup-*.sha256' -print 2>/dev/null \
        | sort \
        | tail -n 1
}

find_offhost_archive() {
    local archive_path="$1"
    local basename_path
    local relative_path

    basename_path="$(basename "$archive_path")"
    relative_path="${archive_path#${BACKUP_DIR}/}"

    if [[ "$relative_path" != "$archive_path" && -r "${OFFHOST_BACKUP_DIR%/}/${relative_path}" ]]; then
        printf '%s' "${OFFHOST_BACKUP_DIR%/}/${relative_path}"
        return 0
    fi

    if [[ -r "${OFFHOST_BACKUP_DIR%/}/${basename_path}" ]]; then
        printf '%s' "${OFFHOST_BACKUP_DIR%/}/${basename_path}"
        return 0
    fi

    find "$OFFHOST_BACKUP_DIR" -type f -name "$basename_path" -print -quit 2>/dev/null
}

if [[ -z "$OFFHOST_BACKUP_DIR" ]]; then
    fail "Set OFFHOST_BACKUP_DIR to the mounted off-host backup storage path."
fi

if [[ ! -d "$OFFHOST_BACKUP_DIR" ]]; then
    fail "OFFHOST_BACKUP_DIR is not a readable directory: $OFFHOST_BACKUP_DIR"
fi

if [[ -z "$BACKUP_HASH_FILE" ]]; then
    BACKUP_HASH_FILE="$(latest_hash_manifest)"
fi

if [[ -z "$BACKUP_HASH_FILE" || ! -r "$BACKUP_HASH_FILE" ]]; then
    fail "Missing readable BACKUP_HASH_FILE. Run deploy/scripts/backup.sh first or pass BACKUP_HASH_FILE explicitly."
fi

log "Starting off-host backup verification"
log "backup_hash_file=${BACKUP_HASH_FILE}"
log "backup_dir=${BACKUP_DIR}"
log "offhost_backup_dir=${OFFHOST_BACKUP_DIR}"

verified=0
while read -r expected_hash archive_path _; do
    if [[ -z "${expected_hash:-}" || -z "${archive_path:-}" ]]; then
        continue
    fi

    if [[ ! "$expected_hash" =~ ^[a-fA-F0-9]{64}$ ]]; then
        fail "Invalid SHA-256 entry in $BACKUP_HASH_FILE"
    fi

    offhost_archive="$(find_offhost_archive "$archive_path")"
    if [[ -z "$offhost_archive" || ! -r "$offhost_archive" ]]; then
        fail "Missing off-host archive for $(basename "$archive_path")"
    fi

    actual_hash="$(sha256_value "$offhost_archive")"
    if [[ "${actual_hash,,}" != "${expected_hash,,}" ]]; then
        fail "SHA-256 mismatch for $(basename "$archive_path")"
    fi

    verified=$((verified + 1))
    log "PASS offhost_archive basename=$(basename "$archive_path") sha256=${actual_hash}"
done < "$BACKUP_HASH_FILE"

if [[ "$verified" -lt 2 ]]; then
    fail "Expected at least database and storage archive hashes; verified=${verified}"
fi

log "PASS offhost_backup_storage verified_archives=${verified}"
log "Off-host backup verification completed. Artifact: ${BACKUP_OFFHOST_ARTIFACT}"
