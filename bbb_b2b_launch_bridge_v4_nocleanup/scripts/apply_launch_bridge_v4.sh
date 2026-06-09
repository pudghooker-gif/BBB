#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"
PATCH_DIR="$ROOT/bbb_b2b_launch_bridge_v4_nocleanup"
FILES_DIR="$PATCH_DIR/files"

if [ ! -f "$ROOT/artisan" ] || [ ! -f "$ROOT/composer.json" ]; then
  echo "ERROR: Run this from the Laravel project root, for example: cd /c/pro/casino" >&2
  exit 1
fi

if [ ! -d "$FILES_DIR" ]; then
  echo "ERROR: files directory not found: $FILES_DIR" >&2
  exit 1
fi

mkdir -p "$ROOT/app/B2B/Contracts" "$ROOT/app/B2B/Providers" "$ROOT/app/B2B/Services" "$ROOT/app/B2B/Models" "$ROOT/app/Http/Controllers/Api/B2B" "$ROOT/database/migrations" "$ROOT/routes" "$ROOT/resources/views/errors" "$ROOT/docs/b2b"
cp -R "$FILES_DIR"/* "$ROOT"/

if [ -f "$ROOT/routes/api.php" ]; then
  if ! grep -q "routes/b2b.php" "$ROOT/routes/api.php"; then
    printf "\nrequire base_path('routes/b2b.php');\n" >> "$ROOT/routes/api.php"
  fi
else
  cat > "$ROOT/routes/api.php" <<'PHP'
<?php

use Illuminate\Support\Facades\Route;

require base_path('routes/b2b.php');
PHP
fi

if [ -f "$ROOT/routes/web.php" ]; then
  if ! grep -q "routes/b2b_web.php" "$ROOT/routes/web.php"; then
    tmp="$ROOT/routes/web.php.v4tmp"
    {
      echo "<?php if (file_exists(base_path('routes/b2b_web.php'))) { require base_path('routes/b2b_web.php'); } ?>"
      cat "$ROOT/routes/web.php"
    } > "$tmp"
    mv "$tmp" "$ROOT/routes/web.php"
  fi
else
  cat > "$ROOT/routes/web.php" <<'PHP'
<?php

require base_path('routes/b2b_web.php');
PHP
fi

echo "B2B Launch Bridge v4 applied. No cleanup was performed."
