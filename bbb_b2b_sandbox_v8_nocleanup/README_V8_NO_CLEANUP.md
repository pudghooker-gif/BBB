# BBB B2B Sandbox v8 No Cleanup

Apply from the Laravel project root:

```bash
cd /c/pro/casino
unzip bbb_b2b_sandbox_v8_nocleanup.zip
chmod +x bbb_b2b_sandbox_v8_nocleanup/scripts/*.sh
./bbb_b2b_sandbox_v8_nocleanup/scripts/apply_sandbox_v8.sh
./bbb_b2b_sandbox_v8_nocleanup/scripts/verify_sandbox_v8.sh
composer dump-autoload
```

If MySQL is running:

```bash
php artisan migrate
php artisan b2b:sandbox-health
php artisan b2b:sandbox-operator SandboxOperator --shop_id=1 --currency=USD --balance=1000 --player_id=demo_player --app_url=http://localhost
```

This patch does not remove `.env`, `.env_old`, `vendor`, SQL dumps, `composer.phar`, or any other local files.
