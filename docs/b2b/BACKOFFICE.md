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

Dangerous actions such as API credential rotation, settlement approval, and manual wallet state changes remain on the audited CLI/step-up foundation until authenticated web step-up and confirmation flows are implemented.
