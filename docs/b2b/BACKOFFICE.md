# B2B Backoffice

The first B2B operations surfaces are available inside the existing backend:

```text
/backend/b2b
/backend/b2b/wallet/manual-actions
/backend/b2b/settlements
/backend/b2b/settlements/detail/{settlement_uid}
/backend/b2b/credentials
/backend/b2b/operators
/backend/b2b/payloads
/backend/b2b/cases
/backend/b2b/cases/reconciliation/{case_id}
/backend/b2b/cases/support-ticket/thread/{ticket_uid}
```

They are protected by the existing backend `auth`, `2fa`, `access.admin.panel`, `only_for_admin`, and dedicated `b2b.admin:*` controls. The dashboard does not expose API secrets, raw wallet payloads, callback bodies, player identifiers, or silent financial mutation controls. The default payload review, case, and support-ticket tables keep request/response/context bodies redacted with the wallet redaction policy. Mutating wallet, settlement, credential, operator, case, support-ticket, and raw-payload access actions are kept on dedicated routes with session-bound web step-up middleware that requires the confirmation phrase plus the current account password.

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
- `POST /backend/b2b/step-up/{action}` stores a session-bound confirmation for the authenticated backend user after the confirmation phrase and current account password are verified;
- route middleware `b2b.web_step_up:{action}` blocks protected web actions until the confirmation is fresh.

Implemented mutating and sensitive-review screens:

- `/backend/b2b/wallet/manual-actions` applies audited manual wallet state transitions with `b2b.wallet.manual_action` and `b2b.web_step_up:wallet.manual_action`;
- `/backend/b2b/settlements` lists settlement cases and submits exported settlements or records approve/reject decisions with `b2b.settlements.submit`, `b2b.settlements.approve`, and matching `settlement.*` web step-up actions. Each settlement links to `/backend/b2b/settlements/detail/{settlement_uid}`, which shows redacted summary, totals, transaction breakdown, approval trail, and snapshot metadata plus a settlement-scoped action form that safely returns to the detail page. Settlement approval/submission reasons are redacted before audit/metadata persistence.
- `/backend/b2b/credentials` rotates and revokes operator API keys with `b2b.credentials.rotate`, `b2b.credentials.revoke`, and matching `api_key.*` web step-up actions. Newly rotated plaintext secrets are shown once and are not stored in plaintext.
- `/backend/b2b/operators` updates non-secret operator configuration and records suspend/resume decisions with `b2b.operators.update`, `b2b.operators.suspend`, and matching `operator.*` web step-up actions.
- `/backend/b2b/payloads` lists recent wallet attempts with recursively redacted request/response payloads under `b2b.payloads.view_redacted`. Raw payload reveal uses `POST /backend/b2b/payloads/raw`, requires `b2b.payloads.view_raw` plus `b2b.web_step_up:payload.view_raw`, and writes `payload.raw_viewed` audit events with actor, reason, source, IP, and user-agent context.
- Before granting broad raw-payload access on an upgraded environment, run `php artisan b2b:payload-redaction-audit`; if legacy findings exist, run the approved `--write` remediation and keep the final clean artifact with release evidence.
- `/backend/b2b/cases` lists reconciliation/manual-review cases with redacted context under `b2b.cases.view`. Each case links to `/backend/b2b/cases/reconciliation/{case_id}`, a `b2b.cases.view` detail page with redacted case summary, wallet transaction summary, bounded operator comments, case event timeline, and case-scoped staff action form. Claim, resolve, and reopen actions require `b2b.cases.manage`, matching `case.*` web step-up actions, return safely to the case detail page when launched from detail, and write `case.claimed`, `case.resolved`, or `case.reopened` audit events.
- `/backend/b2b/cases` also lists operator-created support tickets with redacted subject/reference/context, status, priority, and message counts so support staff can correlate operator-visible tickets with reconciliation cases and audit events. Each ticket links to `/backend/b2b/cases/support-ticket/thread/{ticket_uid}`, a `b2b.cases.view` detail page with redacted ticket summary, context, bounded message thread, and ticket-scoped staff action form. Staff comment, close, and reopen actions require `b2b.cases.manage`, matching `support_ticket.*` web step-up actions, return safely to the ticket thread when launched from detail, and write `support_ticket.staff_commented`, `support_ticket.staff_closed`, or `support_ticket.staff_reopened` audit events.
- `/backend/b2b/audit` lists B2B operator audit events under `b2b.audit.view`, with filters for operator, event, actor, subject, and period. Metadata and free-text reasons are redacted before display.

Signed operator support case detail is readable through `/api/b2b/v1/portal/support/cases/{transaction_uid}` with bounded redacted operator comments, and the same readback is rendered as a signed HTML thread page at `/api/b2b/v1/portal/support/cases/{transaction_uid}/thread`. The signed portal overview/cases/support pages expose tenant-scoped JSON detail endpoint paths and HTML thread page paths for visible open and recent cases so operators can drill down through the signed portal surface. Operator support comments are accepted through `/api/b2b/v1/portal/support/cases/{transaction_uid}/comments`, appended to reconciliation context, redacted before persistence, and visible in `/backend/b2b/cases` context plus `/backend/b2b/audit`.

Signed operator transaction detail is rendered at `/api/b2b/v1/portal/transactions/{transaction_uid}`. It is tenant-scoped to the signed operator and shows transaction summary, status transitions, callback attempts/logs, reconciliation items, and manual actions without raw request/response bodies or foreign-operator rows.

Signed operator support tickets are accepted through `/api/b2b/v1/portal/support/tickets`, readable as bounded redacted message threads through `/api/b2b/v1/portal/support/tickets/{ticket_uid}`, rendered as signed HTML thread pages at `/api/b2b/v1/portal/support/tickets/{ticket_uid}/thread`, and mutable through `/api/b2b/v1/portal/support/tickets/{ticket_uid}/comments` and `/api/b2b/v1/portal/support/tickets/{ticket_uid}/close`. They are tenant-scoped, redacted before persistence and before readback, visible with message counts/latest redacted message context plus JSON detail endpoint and HTML thread page paths in the signed operator portal, manageable in `/backend/b2b/cases` with read-only backoffice thread drilldown, and audited with `support_ticket.created`, `support_ticket.operator_commented`, `support_ticket.closed`, plus the staff-side `support_ticket.staff_*` events.

Signed operator API/audit logs are visible at `/api/b2b/v1/portal/logs` and inside the portal overview. They are tenant-scoped to the signed operator and expose event type, actor, subject, redacted reason, redacted metadata summary, and timestamp without raw payload bodies or foreign-operator events.

Production operator portal UX still needs staging validation and broader workflow polish before this becomes a complete production backoffice.
