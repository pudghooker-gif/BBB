# B2B Backoffice

The first read-only B2B operations surface is available inside the existing backend:

```text
/backend/b2b
```

It is protected by the existing backend `auth`, `2fa`, `access.admin.panel`, and `only_for_admin` controls. The page is intentionally read-only at this stage and does not expose API secrets, raw wallet payloads, callback bodies, player identifiers, or silent financial mutation controls.

Current widgets:

- active/degraded operator counts and open circuit count;
- active B2B session count;
- wallet transaction status summary;
- open reconciliation item count;
- settlement status summary;
- recent wallet transaction operational state without raw payloads;
- recent reconciliation queue items;
- links to health, readiness, metrics, and the repository OpenAPI artifact path.

Authenticated web step-up is now available for future mutating B2B backoffice actions:

- `GET /backend/b2b/step-up/{action}` renders the confirmation challenge for a configured privileged action;
- `POST /backend/b2b/step-up/{action}` stores a session-bound confirmation for the authenticated backend user;
- route middleware `b2b.web_step_up:{action}` blocks protected web actions until the confirmation is fresh.

Dangerous actions such as API credential rotation, settlement approval, and manual wallet state changes remain hidden from the read-only dashboard until their mutation controllers are wired to `b2b.web_step_up:{action}`, audit logging, and provider-specific operational workflows.
