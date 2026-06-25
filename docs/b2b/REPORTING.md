# B2B Reporting API v6

This patch adds report endpoints for operator-facing monitoring and settlement preparation.

## Endpoints

All endpoints require the normal B2B HMAC headers except `/api/b2b/v1/health`.

```http
GET /api/b2b/v1/reports/summary?from=2026-06-01&to=2026-06-09
GET /api/b2b/v1/reports/transactions?limit=100&type=bet&status=success
GET /api/b2b/v1/reports/transactions/{transaction_uid}
GET /api/b2b/v1/reports/ggr?from=2026-06-01&to=2026-06-09
GET /api/b2b/v1/reports/settlements?from=2026-06-01&to=2026-06-09
GET /api/b2b/v1/reports/reconciliation?from=2026-06-01&to=2026-06-09&limit=20
GET /api/b2b/v1/sessions?limit=100&status=active
GET /api/b2b/v1/sessions/{session_uid}
POST /api/b2b/v1/sessions/{session_uid}/close
```

## Notes

- `from` and `to` are optional. Default range is last 7 days.
- `limit` is capped at 1000.
- GGR formula in this MVP: `bets - wins - refunds`.
- Transaction detail includes callback logs plus wallet transition history, recent attempts, and open reconciliation items when those tables exist.
- Reconciliation report is tenant-scoped and includes item counts by state/reason/priority, unresolved aging buckets, oldest open items, and open monetary exposure by currency.
- Open monetary exposure is calculated once per unresolved wallet transaction, even if the transaction has multiple open reconciliation items.
- Heavy monthly settlement exports should later move to queues and summary tables.
