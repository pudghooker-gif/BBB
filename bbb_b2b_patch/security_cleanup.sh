#!/usr/bin/env bash
set -euo pipefail

if [ ! -d ".git" ]; then
  echo "Run from the Git repository root." >&2
  exit 1
fi

git rm --cached .env .env_old composer.phar composer-setup.php totalbet365.sql 2>/dev/null || true
git rm -r --cached vendor PTWebSocket/node_modules 2>/dev/null || true

echo "Staged removal from Git index. Local files remain on disk."
echo "Now rotate APP_KEY/JWT/DB credentials and commit the cleanup."
