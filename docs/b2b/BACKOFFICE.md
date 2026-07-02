# B2B Backoffice

The first B2B operations surfaces are available inside the existing backend:

```text
/backend/b2b
/backend/b2b/wallet/manual-actions
/backend/b2b/settlements
/backend/b2b/credentials
/backend/b2b/operators
/backend/b2b/payloads
/backend/b2b/cases
```

They are protected by the existing backend `auth`, `2fa`, `access.admin.panel`, `only_for_admin`, and dedicated `b2b.admin:*` controls. The dashboard does not expose API secrets, raw wallet payloads, callback bodies, player identifiers, or silent financial mutation controls. The default payload review and case tables keep request/response/context bodies redacted with the wallet redaction policy. Mutating wallet, settlement, credential, operator, case, and raw-payload access actions are kept on dedicated routes with session-bound web step-up middleware.

Current widgets:

- active/degraded operator counts and open circuit count;
- active B2B session count;
- wallet transaction status summary;
- open reconciliation item count;
- settlement status summary;
- recent wallet transaction operational state without raw payloads;
- recent reconciliation queue items;
- links to manual wallet actions, settlement workflow, credential lifecycle, operator configuration, payload review, case management, health, readiness, metrics, and the repository OpenAPI artifact path.

Authenticated web step-up is available for mutating B2B backoffice actions:

- `GET /backend/b2b/step-up/{action}` renders the confirmation challenge for a configured privileged action;
- `POST /backend/b2b/step-up/{action}` stores a session-bound confirmation for the authenticated backend user;
- route middleware `b2b.web_step_up:{action}` blocks protected web actions until the confirmation is fresh.

Implemented mutating and sensitive-review screens:

- `/backend/b2b/wallet/manual-actions` applies audited manual wallet state transitions with `b2b.wallet.manual_action` and `b2b.web_step_up:wallet.manual_action`;
- `/backend/b2b/settlements` submits exported settlements and records approve/reject decisions with `b2b.settlements.submit`, `b2b.settlements.approve`, and matching `settlement.*` web step-up actions.
- `/backend/b2b/credentials` rotates and revokes operator API keys with `b2b.credentials.rotate`, `b2b.credentials.revoke`, and matching `api_key.*` web step-up actions. Newly rotated plaintext secrets are shown once and are not stored in plaintext.
- `/backend/b2b/operators` updates non-secret operator configuration and records suspend/resume decisions with `b2b.operators.update`, `b2b.operators.suspend`, and matching `operator.*` web step-up actions.
- `/backend/b2b/payloads` lists recent wallet attempts with recursively redacted request/response payloads under `b2b.payloads.view_redacted`. Raw payload reveal uses `POST /backend/b2b/payloads/raw`, requires `b2b.payloads.view_raw` plus `b2b.web_step_up:payload.view_raw`, and writes `payload.raw_viewed` audit events with actor, reason, source, IP, and user-agent context.
- `/backend/b2b/cases` lists reconciliation/manual-review cases with redacted context under `b2b.cases.view`. Claim, resolve, and reopen actions require `b2b.cases.manage`, matching `case.*` web step-up actions, and write `case.claimed`, `case.resolved`, or `case.reopened` audit events.
- `/backend/b2b/audit` lists B2B operator audit events under `b2b.audit.view`, with filters for operator, event, actor, subject, and period. Metadata and free-text reasons are redacted before display.

Production operator portal UX, including staging validation and operator-visible support/case follow-up beyond read-only pages, still needs dedicated work before this becomes a complete production backoffice.
