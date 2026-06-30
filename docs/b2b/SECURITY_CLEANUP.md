# Security cleanup required before B2B work

The repository previously tracked sensitive/runtime artifacts such as `.env`, `.env_old`, WebSocket TLS key/cert files, and a SQL dump. They are removed from the current tree and covered by ignore/export-ignore rules; rotate any values committed to history before deploying.

Recommended commands from the repository root:

```bash
git rm --cached .env .env_old totalbet365.sql PTWebSocket/ssl/key.key PTWebSocket/ssl/crt.crt 2>/dev/null || true
git rm -r --cached vendor PTWebSocket/node_modules 2>/dev/null || true
git add .gitignore
git commit -m "Remove secrets and runtime artifacts from repository"
```

Then rotate on the real server:

```bash
php artisan key:generate --force
```

Also replace database credentials and any JWT/API secrets that were committed. If the repository is public, use a history-cleaning tool such as `git filter-repo` or BFG Repo-Cleaner and force-push only after making a backup.
