# B2B RBAC And Step-Up Foundation

This is the server-side foundation for B2B administrative authorization. It is intentionally deny-by-default and separate from the legacy B2C casino role assumptions.

## Permission Catalog

The B2B permission map lives in `config/b2b_admin.php`.

Key permissions:

- `b2b.operators.create`
- `b2b.operators.update`
- `b2b.operators.suspend`
- `b2b.credentials.rotate`
- `b2b.credentials.revoke`
- `b2b.wallet.manual_action`
- `b2b.wallet.retry`
- `b2b.wallet.reconcile`
- `b2b.cases.view`
- `b2b.cases.manage`
- `b2b.payloads.view_redacted`
- `b2b.payloads.view_raw`
- `b2b.reports.view`
- `b2b.reports.export`
- `b2b.settlements.submit`
- `b2b.settlements.approve`
- `b2b.audit.view`
- `b2b.system.release_check`

Configured B2B roles:

- `super_admin`
- `operations`
- `finance`
- `support`
- `auditor`
- `integration_manager`
- `read_only`

Unknown roles and unknown actions are denied.

## API Key Scopes

B2B HMAC keys also carry explicit API scopes in `b2b_operator_api_keys.scopes`. New keys use `B2B_API_KEY_DEFAULT_SCOPES`, which must not include `reports.export` or `*` in production. Scope-protected surfaces include `operator.read`, `portal.read`, `support.write`, `games.read`, `games.launch`, `sessions.read`, `sessions.close`, `wallet.balance`, `wallet.status`, `wallet.mutate`, `reports.read`, `reports.export`, `sandbox.wallet.read`, and `sandbox.wallet.mutate`. Settlement export requires a key with the dedicated `reports.export` scope:

The signed operator portal exposes key IDs, statuses, rate limits, and public scope names for the current/recent keys so operators can self-check integration permissions. It does not expose encrypted secrets or plaintext API secrets.

```bash
php artisan b2b:rotate-api-key op_xxx \
  --scopes=operator.read,portal.read,reports.read,reports.export \
  --actor=security_user \
  --reason="Finance export key" \
  --permission=b2b.credentials.rotate \
  --confirm=ROTATE_API_KEY
```

## Privileged CLI Actions

Dangerous B2B CLI actions require all of:

- `--actor`
- `--reason`
- exact `--permission`
- exact `--confirm` step-up phrase

Examples:

```bash
php artisan b2b:make-operator "Operator" \
  --currency=USD \
  --actor=integration_manager \
  --reason="Onboarding ticket B2B-123" \
  --permission=b2b.operators.create \
  --confirm=CREATE_OPERATOR

php artisan b2b:rotate-api-key op_xxx \
  --scopes=operator.read,portal.read,reports.read \
  --actor=security_user \
  --reason="Quarterly rotation" \
  --permission=b2b.credentials.rotate \
  --confirm=ROTATE_API_KEY \
  --revoke-existing

php artisan b2b:revoke-api-key op_xxx key_xxx \
  --actor=security_user \
  --reason="Partner requested revocation" \
  --permission=b2b.credentials.revoke \
  --confirm=REVOKE_API_KEY

php artisan b2b:wallet-manual-action tx_123 mark-review \
  --operator-id=1 \
  --actor=finance_user \
  --reason="Provider case ABC-123 is unresolved" \
  --permission=b2b.wallet.manual_action \
  --confirm=MANUAL_WALLET_ACTION

php artisan b2b:submit-settlement stl_xxx \
  --actor=finance_user \
  --reason="Monthly settlement close" \
  --permission=b2b.settlements.submit \
  --confirm=SUBMIT_SETTLEMENT

php artisan b2b:approve-settlement stl_xxx approve \
  --actor=finance_lead \
  --reason="Totals match finance reconciliation" \
  --permission=b2b.settlements.approve \
  --confirm=APPROVE_SETTLEMENT

php artisan b2b:approve-settlement stl_xxx reject \
  --actor=finance_lead \
  --reason="Disputed transaction is still open" \
  --permission=b2b.settlements.approve \
  --confirm=REJECT_SETTLEMENT
```

Denied privileged attempts write `privileged_action.denied` to `b2b_operator_audit_events` when the audit table exists.

## Web Step-Up

The backend now has a session-bound step-up guard for mutating B2B web actions. The step-up route is behind the existing backend `auth` and `2fa` middleware and, by default, requires both the configured confirmation phrase and the current account password before the session marker is written.

Routes:

```text
GET  /backend/b2b/step-up/{action}
POST /backend/b2b/step-up/{action}
```

Protected backoffice workflows:

```text
GET  /backend/b2b/wallet/manual-actions
POST /backend/b2b/wallet/manual-actions
GET  /backend/b2b/settlements
POST /backend/b2b/settlements/submit
POST /backend/b2b/settlements/approve
POST /backend/b2b/settlements/reject
GET  /backend/b2b/credentials
POST /backend/b2b/credentials/rotate
POST /backend/b2b/credentials/revoke
GET  /backend/b2b/operators
POST /backend/b2b/operators/update
POST /backend/b2b/operators/suspend
POST /backend/b2b/operators/resume
GET  /backend/b2b/payloads
POST /backend/b2b/payloads/raw
GET  /backend/b2b/cases
POST /backend/b2b/cases/claim
POST /backend/b2b/cases/resolve
POST /backend/b2b/cases/reopen
```

Middleware for dangerous web routes:

```php
'middleware' => 'b2b.web_step_up:api_key.rotate'
```

The guard requires the authenticated backend user to have the configured B2B permission, enter the exact action confirmation phrase, and keep the confirmation inside the same user session. The default TTL is 300 seconds and can be changed with `B2B_WEB_STEP_UP_TTL_SECONDS`.

## Remaining Production Work

The web guard now combines backend 2FA, exact action confirmation, current-password verification, session binding, and a short TTL. Remaining production work is provider-specific form polish, operator-visible portal case workflow validation, and optional WebAuthn/hardware-key policy if the launch jurisdiction or operator policy requires it.
