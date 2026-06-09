# BBB B2B Launch Bridge v4 — No Cleanup

This package adds the browser launch bridge for B2B sessions and does not delete any files.

## Apply on Windows Git Bash

```bash
cd /c/pro/casino
unzip bbb_b2b_launch_bridge_v4_nocleanup.zip
chmod +x bbb_b2b_launch_bridge_v4_nocleanup/scripts/*.sh
./bbb_b2b_launch_bridge_v4_nocleanup/scripts/apply_launch_bridge_v4.sh
./bbb_b2b_launch_bridge_v4_nocleanup/scripts/verify_launch_bridge_v4.sh
composer dump-autoload
php artisan migrate
php artisan route:list | grep b2b
```

If `grep` is not available:

```bash
php artisan route:list | findstr b2b
```

Commit message:

```text
Add B2B launch bridge
```
