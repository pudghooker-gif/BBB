#!/usr/bin/env bash
set -euo pipefail

missing=0
check_file() {
  if [ ! -f "$1" ]; then
    echo "MISSING: $1"
    missing=1
  else
    echo "OK: $1"
  fi
}

check_file app/B2B/Contracts/GameProviderInterface.php
check_file app/B2B/Providers/GoldsvetInternalProvider.php
check_file app/B2B/Services/B2BLaunchBridge.php
check_file app/B2B/Services/ShadowUserManager.php
check_file app/Http/Controllers/Api/B2B/B2BLauncherController.php
check_file app/Http/Controllers/Api/B2B/SessionController.php
check_file app/Http/Controllers/Api/B2B/GameLaunchController.php
check_file app/B2B/Models/B2BGameSession.php
check_file routes/b2b.php
check_file routes/b2b_web.php
check_file resources/views/errors/b2b-launch.blade.php
check_file database/migrations/2026_05_13_000001_add_launch_bridge_fields_to_b2b_game_sessions_table.php
check_file docs/b2b/LAUNCH_BRIDGE_V4.md

if ! grep -q "routes/b2b.php" routes/api.php; then
  echo "MISSING INCLUDE: routes/api.php does not include routes/b2b.php"
  missing=1
else
  echo "OK: routes/api.php includes routes/b2b.php"
fi

if ! grep -q "routes/b2b_web.php" routes/web.php; then
  echo "MISSING INCLUDE: routes/web.php does not include routes/b2b_web.php"
  missing=1
else
  echo "OK: routes/web.php includes routes/b2b_web.php"
fi

if [ "$missing" -ne 0 ]; then
  echo "Verification failed."
  exit 1
fi

echo "Verification passed."
