# Security cleanup required before B2B work

The public repository currently tracks sensitive/runtime artifacts such as `.env`, `.env_old`, `vendor`, `composer.phar`, `composer-setup.php`, and a SQL dump. Remove them from Git and rotate secrets before deploying.

Recommended commands from the repository root:

```bash
git rm --cached .env .env_old composer.phar composer-setup.php totalbet365.sql 2>/dev/null || true
git rm -r --cached vendor PTWebSocket/node_modules 2>/dev/null || true
git add .gitignore
git commit -m "Remove secrets and runtime artifacts from repository"
```

Then rotate on the real server:

```bash
php artisan key:generate --force
```

Also replace database credentials and any JWT/API secrets that were committed. If the repository is public, use a history-cleaning tool such as `git filter-repo` or BFG Repo-Cleaner and force-push only after making a backup.
