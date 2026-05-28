#!/usr/bin/env bash
set -euo pipefail

missing=0
check() {
  if [ ! -f "$1" ]; then
    echo "MISSING: $1"
    missing=1
  else
    echo "OK: $1"
  fi
}

check app/B2B/Services/B2BResilienceGuard.php
check app/B2B/Services/OperatorWalletClient.php
check app/B2B/Models/B2BOperatorHealthEvent.php
check app/B2B/Models/B2BOperator.php
check app/B2B/Models/B2BWalletTransaction.php
check app/B2B/Models/B2BGameSession.php
check app/Http/Middleware/VerifyB2BSignature.php
check app/Http/Controllers/Api/B2B/WalletController.php
check app/Http/Controllers/Api/B2B/GameLaunchController.php
check database/migrations/2026_05_12_000001_add_resilience_fields_to_b2b_operators_table.php
check database/migrations/2026_05_12_000002_add_resilience_fields_to_b2b_wallet_transactions_table.php
check database/migrations/2026_05_12_000003_create_b2b_operator_health_events_table.php
check database/migrations/2026_05_12_000004_add_session_resilience_fields_to_b2b_game_sessions_table.php
check docs/b2b/RESILIENCE_V3.md

if ! grep -q "routes/b2b.php" routes/api.php; then
  echo "MISSING: routes/api.php does not include routes/b2b.php"
  missing=1
else
  echo "OK: routes/api.php includes routes/b2b.php"
fi

if [ "$missing" -eq 1 ]; then
  echo "Verification failed."
  exit 1
fi

echo "Verification passed."
