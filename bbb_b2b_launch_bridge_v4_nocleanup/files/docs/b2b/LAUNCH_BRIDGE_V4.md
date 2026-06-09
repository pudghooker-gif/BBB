# B2B Launch Bridge v4

This update connects the B2B launch API to the existing Goldsvet/VanguardLTE launcher.

Flow:

```text
POST /api/b2b/v1/games/launch
  -> creates b2b_game_sessions
  -> returns /b2b/launcher/{game}/{b2b_session_token}

Player opens /b2b/launcher/{game}/{b2b_session_token}
  -> resolves b2b_game_sessions.token_hash
  -> creates/finds shadow user in users
  -> refreshes users.api_token
  -> redirects to existing /launcher/{game}/{api_token}
  -> old AuthController::apiLogin logs the shadow user in
  -> old project redirects to /game/{game}
```

This package does not delete `.env`, `vendor`, SQL dumps, or any other project files.

New HMAC endpoint:

```http
GET /api/b2b/v1/sessions/{session_uid}
```
