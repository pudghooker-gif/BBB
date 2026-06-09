#!/usr/bin/env bash
set -e

ROOT_DIR="$(pwd)"
FAILED=0

check_file() {
  if [ -f "$ROOT_DIR/$1" ]; then
    echo "OK: $1"
  else
    echo "MISSING: $1"
    FAILED=1
  fi
}

check_contains() {
  if grep -q "$2" "$ROOT_DIR/$1" 2>/dev/null; then
    echo "OK include: $1 contains $2"
  else
    echo "MISSING include: $1 does not contain $2"
    FAILED=1
  fi
}

check_file "app/B2B/Models/B2BSandboxWallet.php"
check_file "app/B2B/Models/B2BSandboxWalletEntry.php"
check_file "app/B2B/Services/SandboxWalletService.php"
check_file "app/Http/Controllers/Api/B2B/SandboxWalletController.php"
check_file "app/Http/Controllers/Api/B2B/SandboxController.php"
check_file "database/migrations/2026_06_09_180000_create_b2b_sandbox_wallets_table.php"
check_file "database/migrations/2026_06_09_180100_create_b2b_sandbox_wallet_entries_table.php"
check_file "routes/b2b_sandbox_v8.php"
check_file "routes/b2b_sandbox_console.php"
check_file "docs/b2b/SANDBOX_V8.md"

check_contains "routes/api.php" "routes/b2b_sandbox_v8.php"
check_contains "routes/console.php" "routes/b2b_sandbox_console.php"

if command -v php >/dev/null 2>&1; then
  echo "Running php -l on v8 PHP files..."
  find "$ROOT_DIR/app/B2B/Models" "$ROOT_DIR/app/B2B/Services" "$ROOT_DIR/app/Http/Controllers/Api/B2B" "$ROOT_DIR/routes" "$ROOT_DIR/database/migrations" -name '*Sandbox*.php' -o -name 'b2b_sandbox*.php' | while read -r f; do
    php -l "$f" >/dev/null || exit 1
  done
fi

if [ "$FAILED" -ne 0 ]; then
  echo "B2B Sandbox v8 verification failed."
  exit 1
fi

echo "B2B Sandbox v8 verification passed."
