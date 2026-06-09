#!/usr/bin/env bash
set -e

PATCH_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PROJECT_DIR="$(pwd)"

if [ ! -f "$PROJECT_DIR/artisan" ]; then
  echo "ERROR: run this script from the Laravel project root, where artisan exists."
  exit 1
fi

echo "Applying B2B Reporting v6 overlay..."
cp -R "$PATCH_DIR/files/"* "$PROJECT_DIR/"

# Ensure B2B routes are loaded from routes/api.php
if [ -f "$PROJECT_DIR/routes/api.php" ]; then
  if ! grep -q "routes/b2b.php" "$PROJECT_DIR/routes/api.php"; then
    echo "" >> "$PROJECT_DIR/routes/api.php"
    echo "require base_path('routes/b2b.php');" >> "$PROJECT_DIR/routes/api.php"
    echo "Added routes/b2b.php require to routes/api.php"
  else
    echo "routes/b2b.php already required from routes/api.php"
  fi
else
  echo "WARNING: routes/api.php not found"
fi

mkdir -p "$PROJECT_DIR/storage/app/b2b_exports"
mkdir -p "$PROJECT_DIR/bootstrap/cache"
mkdir -p "$PROJECT_DIR/storage/framework/cache/data"
mkdir -p "$PROJECT_DIR/storage/framework/sessions"
mkdir -p "$PROJECT_DIR/storage/framework/views"
mkdir -p "$PROJECT_DIR/storage/logs"

echo "B2B Reporting v6 applied."
