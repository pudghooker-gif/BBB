#!/usr/bin/env bash
set -euo pipefail

if [ ! -f "composer.json" ] || [ ! -d "app" ] || [ ! -d "routes" ]; then
  echo "Run this script from the root of the BBB/Laravel repository." >&2
  exit 1
fi

PATCH_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FILES_DIR="$PATCH_DIR/files"

copy_file() {
  local src="$FILES_DIR/$1"
  local dst="$1"
  mkdir -p "$(dirname "$dst")"
  if [ -f "$dst" ] && ! cmp -s "$src" "$dst"; then
    cp "$dst" "$dst.bak.$(date +%Y%m%d%H%M%S)"
  fi
  cp "$src" "$dst"
  echo "installed $dst"
}

copy_file "routes/b2b.php"
copy_file "app/Http/Middleware/VerifyB2BSignature.php"
copy_file "app/B2B/Models/B2BOperator.php"
copy_file "app/B2B/Models/B2BOperatorApiKey.php"
copy_file "app/B2B/Models/B2BOperatorPlayer.php"
copy_file "app/B2B/Models/B2BGameCatalog.php"
copy_file "app/B2B/Models/B2BGameSession.php"
copy_file "app/B2B/Models/B2BWalletTransaction.php"
copy_file "app/B2B/Models/B2BWalletCallbackLog.php"
copy_file "app/B2B/Models/B2BProviderRequest.php"
copy_file "app/B2B/Models/B2BSettlement.php"
copy_file "app/B2B/Services/OperatorWalletClient.php"
copy_file "app/Http/Controllers/Api/B2B/GameCatalogController.php"
copy_file "app/Http/Controllers/Api/B2B/GameLaunchController.php"
copy_file "app/Http/Controllers/Api/B2B/WalletController.php"
copy_file "app/Http/Controllers/Api/B2B/ReportsController.php"
copy_file "database/migrations/2026_05_11_000001_create_b2b_operators_table.php"
copy_file "database/migrations/2026_05_11_000002_create_b2b_operator_api_keys_table.php"
copy_file "database/migrations/2026_05_11_000003_create_b2b_operator_players_table.php"
copy_file "database/migrations/2026_05_11_000004_create_b2b_game_catalog_table.php"
copy_file "database/migrations/2026_05_11_000005_create_b2b_game_sessions_table.php"
copy_file "database/migrations/2026_05_11_000006_create_b2b_wallet_transactions_table.php"
copy_file "database/migrations/2026_05_11_000007_create_b2b_wallet_callback_logs_table.php"
copy_file "database/migrations/2026_05_11_000008_create_b2b_provider_requests_table.php"
copy_file "database/migrations/2026_05_11_000009_create_b2b_settlements_table.php"
copy_file "docs/b2b/API.md"
copy_file "docs/b2b/SECURITY_CLEANUP.md"

if ! grep -q "routes/b2b.php" routes/api.php; then
  cp routes/api.php "routes/api.php.bak.$(date +%Y%m%d%H%M%S)"
  cat >> routes/api.php <<'PHP'

// B2B Aggregator API routes
require __DIR__ . '/b2b.php';
PHP
  echo "updated routes/api.php"
else
  echo "routes/api.php already includes routes/b2b.php"
fi

if [ -f ".gitignore" ]; then
  cp .gitignore ".gitignore.bak.$(date +%Y%m%d%H%M%S)"
else
  touch .gitignore
fi

append_ignore() {
  local line="$1"
  grep -qxF "$line" .gitignore || echo "$line" >> .gitignore
}

append_ignore ""
append_ignore "# Security/runtime artifacts"
append_ignore ".env"
append_ignore ".env.*"
append_ignore "!.env.example"
append_ignore "/vendor/"
append_ignore "/node_modules/"
append_ignore "/PTWebSocket/node_modules/"
append_ignore "composer.phar"
append_ignore "composer-setup.php"
append_ignore "*.sql"
append_ignore "/storage/*.key"

echo "updated .gitignore"

echo ""
echo "B2B skeleton installed. Next:"
echo "  composer dump-autoload"
echo "  php artisan migrate"
echo "  read docs/b2b/API.md and docs/b2b/SECURITY_CLEANUP.md"
