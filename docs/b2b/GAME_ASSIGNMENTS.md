# B2B Operator Game Assignments

Date: 2026-06-24

`b2b_operator_game_assignments` is the dedicated source for per-operator game access.

## Statuses

| Status | Behavior |
| --- | --- |
| `allowed` | Enables the game for this operator/provider pair. If an operator has any `allowed` assignments, unassigned games are denied by default. |
| `blocked` | Explicitly denies the game for this operator/provider pair. |
| `disabled` | Assignment row is ignored and legacy fallback rules can still apply. |

Assignments are scoped by `operator_id`, `provider`, and `game_uid`.

## Launch And Catalog Rules

`GET /api/b2b/v1/games` and `POST /api/b2b/v1/games/launch` both pass through `B2BGameAvailabilityService`.

Resolution order:

1. A matching `blocked` assignment denies the game.
2. A matching `allowed` assignment enables the game, subject to mode, currency, and country limits on the assignment.
3. If the operator has any active `allowed` assignment, any unassigned game is denied.
4. If no assignment policy is active for the operator, legacy `settings.enabled_games`, `settings.disabled_games`, and Goldsvet `shop_id` visibility rules apply.

For legacy Goldsvet fallback games, the provider key is `goldsvet_internal`. Catalog games use their catalog `provider` value.

## Assignment Limits

Optional assignment fields:

- `demo_enabled`
- `real_enabled`
- `allowed_currencies`
- `allowed_countries`

These limits are enforced before a launch session is created.
