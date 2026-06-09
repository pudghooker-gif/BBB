#!/usr/bin/env bash
set -e

missing=0
check_file() {
  if [ ! -f "$1" ]; then
    echo "MISSING: $1"
    missing=1
  else
    echo "OK: $1"
  fi
}

check_file routes/b2b.php
check_file app/Http/Controllers/Api/B2B/ReportsController.php
check_file app/Http/Controllers/Api/B2B/SessionController.php
check_file app/B2B/Services/B2BReportQuery.php
check_file database/migrations/2026_06_09_000060_add_reporting_indexes_to_b2b_tables.php
check_file docs/b2b/REPORTING.md

if [ "$missing" -eq 1 ]; then
  echo "Verification failed: missing files."
  exit 1
fi

if grep -q "routes/b2b.php" routes/api.php; then
  echo "OK: routes/api.php loads routes/b2b.php"
else
  echo "WARNING: routes/api.php does not load routes/b2b.php"
fi

echo "B2B Reporting v6 verification complete."
