#!/usr/bin/env bash
set -e

ROOT_DIR="$(pwd)"
PATCH_DIR="$ROOT_DIR/bbb_b2b_sandbox_v8_nocleanup"

if [ ! -f "$ROOT_DIR/artisan" ]; then
  echo "ERROR: Run this script from Laravel project root where artisan exists."
  exit 1
fi

if [ ! -d "$PATCH_DIR/files" ]; then
  echo "ERROR: Patch files directory not found: $PATCH_DIR/files"
  exit 1
fi

echo "Applying B2B Sandbox v8 nocleanup patch..."
cp -R "$PATCH_DIR/files/"* "$ROOT_DIR/"

# Ensure routes/api.php loads the v8 sandbox API route file.
if [ -f "$ROOT_DIR/routes/api.php" ]; then
  if ! grep -q "routes/b2b_sandbox_v8.php" "$ROOT_DIR/routes/api.php"; then
    printf "\n// B2B Sandbox v8 routes\nrequire base_path('routes/b2b_sandbox_v8.php');\n" >> "$ROOT_DIR/routes/api.php"
    echo "Added routes/b2b_sandbox_v8.php include to routes/api.php"
  else
    echo "routes/api.php already includes routes/b2b_sandbox_v8.php"
  fi
else
  echo "WARNING: routes/api.php not found"
fi

# Ensure routes/console.php loads the v8 sandbox console route file.
if [ -f "$ROOT_DIR/routes/console.php" ]; then
  if ! grep -q "routes/b2b_sandbox_console.php" "$ROOT_DIR/routes/console.php"; then
    printf "\n// B2B Sandbox v8 console commands\nrequire base_path('routes/b2b_sandbox_console.php');\n" >> "$ROOT_DIR/routes/console.php"
    echo "Added routes/b2b_sandbox_console.php include to routes/console.php"
  else
    echo "routes/console.php already includes routes/b2b_sandbox_console.php"
  fi
else
  echo "WARNING: routes/console.php not found"
fi

echo "B2B Sandbox v8 applied. No cleanup was performed."
