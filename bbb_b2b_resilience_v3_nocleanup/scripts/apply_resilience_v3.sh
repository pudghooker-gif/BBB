#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"
PATCH_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$ROOT/.b2b_backups/resilience_v3_$(date +%Y%m%d_%H%M%S)"

if [ ! -f "$ROOT/artisan" ] || [ ! -f "$ROOT/composer.json" ]; then
  echo "ERROR: Run this script from the Laravel project root. artisan and composer.json were not found."
  exit 1
fi

mkdir -p "$BACKUP_DIR"

backup_file() {
  local file="$1"
  if [ -f "$ROOT/$file" ]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$file")"
    cp "$ROOT/$file" "$BACKUP_DIR/$file"
  fi
}

backup_file "app/B2B/Models/B2BOperator.php"
backup_file "app/B2B/Models/B2BWalletTransaction.php"
backup_file "app/B2B/Models/B2BGameSession.php"
backup_file "app/B2B/Services/OperatorWalletClient.php"
backup_file "app/Http/Middleware/VerifyB2BSignature.php"
backup_file "app/Http/Controllers/Api/B2B/GameLaunchController.php"
backup_file "app/Http/Controllers/Api/B2B/WalletController.php"
backup_file "routes/api.php"

cp -R "$PATCH_DIR/files/"* "$ROOT/"

if ! grep -q "routes/b2b.php" "$ROOT/routes/api.php"; then
  cat >> "$ROOT/routes/api.php" <<'PHP'

// B2B Aggregator API routes
if (file_exists(base_path('routes/b2b.php'))) {
    require base_path('routes/b2b.php');
}
PHP
fi

mkdir -p "$ROOT/bootstrap/cache" "$ROOT/storage/framework/cache/data" "$ROOT/storage/framework/sessions" "$ROOT/storage/framework/views" "$ROOT/storage/logs"

echo "B2B resilience v3 applied. Backup saved to: $BACKUP_DIR"
echo "Next: composer dump-autoload && php artisan migrate && php artisan route:list | grep b2b"
