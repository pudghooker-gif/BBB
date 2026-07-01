# B2B Backoffice

The first B2B operations surfaces are available inside the existing backend:

```text
/backend/b2b
/backend/b2b/wallet/manual-actions
/backend/b2b/settlements
```

They are protected by the existing backend `auth`, `2fa`, `access.admin.panel`, `only_for_admin`, and dedicated `b2b.admin:*` controls. The dashboard does not expose API secrets, raw wallet payloads, callback bodies, player identifiers, or silent financial mutation controls. Mutating wallet and settlement actions are kept on dedicated routes with session-bound web step-up middleware.

Current widgets:

- active/degraded operator counts and open circuit count;
- active B2B session count;
- wallet transaction status summary;
- open reconciliation item count;
- settlement status summary;
- recent wallet transaction operational state without raw payloads;
- recent reconciliation queue items;
- links to manual wallet actions, settlement workflow, health, readiness, metrics, and the repository OpenAPI artifact path.

Authenticated web step-up is available for mutating B2B backoffice actions:

- `GET /backend/b2b/step-up/{action}` renders the confirmation challenge for a configured privileged action;
- `POST /backend/b2b/step-up/{action}` stores a session-bound confirmation for the authenticated backend user;
- route middleware `b2b.web_step_up:{action}` blocks protected web actions until the confirmation is fresh.

Implemented mutating screens:

- `/backend/b2b/wallet/manual-actions` applies audited manual wallet state transitions with `b2b.wallet.manual_action` and `b2b.web_step_up:wallet.manual_action`;
- `/backend/b2b/settlements` submits exported settlements and records approve/reject decisions with `b2b.settlements.submit`, `b2b.settlements.approve`, and matching `settlement.*` web step-up actions.

API credential rotation/revocation and richer operator case-management screens still need dedicated web workflows before this becomes a complete production backoffice.
