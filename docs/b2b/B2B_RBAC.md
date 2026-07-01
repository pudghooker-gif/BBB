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

The backend now has a session-bound step-up guard for mutating B2B web actions.

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
```

Middleware for dangerous web routes:

```php
'middleware' => 'b2b.web_step_up:api_key.rotate'
```

The guard requires the authenticated backend user to have the configured B2B permission, enter the exact action confirmation phrase, and keep the confirmation inside the same user session. The default TTL is 300 seconds and can be changed with `B2B_WEB_STEP_UP_TTL_SECONDS`.

## Remaining Production Work

The web guard is only the confirmation/session foundation. Production B2B mutation screens still need provider-specific forms, full audit event coverage, stronger re-authentication such as TOTP/WebAuthn for high-risk actions, session revocation hooks, and operator-visible case workflow.
