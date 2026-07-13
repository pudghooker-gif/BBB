# Next Steps After Repair v2

1. Confirm the release artifact is clean of `.env`, `vendor`, SQL dumps, installer folders, and local secret files.
2. On the target host, run:

```bash
composer dump-autoload
php artisan migrate
php artisan route:list | grep b2b
```

3. Create a test B2B operator with `docs/b2b/CREATE_TEST_OPERATOR.md`.
4. Verify the unsigned public health endpoint:

```bash
curl -fsS https://<domain>/api/b2b/v1/health
```

5. Verify signed operator requests:

- `GET /api/b2b/v1/games`
- `POST /api/b2b/v1/games/launch`

6. Goldsvet/internal launcher integration is implemented locally. The public B2B launch URL uses `/b2b/launcher/{game}/{token}`, stores only the one-time token hash on the B2B session, prepares the provider launch only when the player opens the bridge URL, and then redirects to the legacy `/launcher/{game}/{token}` URL. Keep `php artisan b2b:release-check --production` green; the `launcher_integration` gate protects this flow.
7. Before production traffic, close the external blockers: real provider credentials and wallet contract certification, staging migration rehearsal on a production database copy, production domains/TLS/proxy/shared-state validation, target smoke/load/WebSocket/observability evidence, backup/restore/rollback drills, and a target-environment `release-evidence.json` package that passes `b2b:evidence-check --production`.
