# B2B HMAC Authentication

All protected B2B API routes under `/api/b2b/v1` require these headers:

- `X-Operator-Id`
- `X-Api-Key`
- `X-Timestamp`
- `X-Nonce`
- `X-Body-Hash`
- `X-Signature`
- optional `X-Request-Id`

## Body Hash

`X-Body-Hash` is the lowercase SHA-256 hash of the exact raw request body bytes. For an empty body, hash an empty string.

## Canonical Request

The signature payload is:

```text
METHOD
/path
canonical_query
body_hash
timestamp
nonce
```

Rules:

- `METHOD` is uppercase.
- `/path` includes the API prefix, for example `/api/b2b/v1/operator/me`.
- `canonical_query` is the RFC3986-encoded query string with keys sorted recursively. Use an empty line when there is no query.
- `body_hash` is the same value sent in `X-Body-Hash`.
- `timestamp` is Unix seconds and must be within the configured replay window.
- `nonce` must be unique for the operator inside the replay window.

`X-Signature` is `HMAC-SHA256(canonical_request, api_secret)`.

## PHP Example

```php
$method = 'GET';
$path = '/api/b2b/v1/operator/me';
$query = '';
$body = '';
$timestamp = (string) time();
$nonce = bin2hex(random_bytes(16));
$bodyHash = hash('sha256', $body);

$canonical = implode("\n", [
    $method,
    $path,
    $query,
    $bodyHash,
    $timestamp,
    $nonce,
]);

$signature = hash_hmac('sha256', $canonical, $secret);
```

## cURL Helper

The local helper prints signed headers and a cURL example:

```bash
php artisan b2b:show-hmac op_xxx key_xxx secret_xxx GET /api/b2b/v1/operator/me
```

## Production Notes

Set `B2B_NONCE_CACHE_STORE=redis` and `B2B_RATE_LIMIT_CACHE_STORE=redis` in production so replay protection and app-level rate limits work across multiple API nodes. Keep the sandbox/private callback exception disabled outside isolated local testing.

Successful HMAC authentication updates `last_used_at` and writes a throttled `api_key.used` audit event with method, path, IP address, request ID, and key ID. It does not store API secrets, signatures, nonce values, or request bodies. Tune the sampling window with `B2B_API_KEY_USAGE_AUDIT_SAMPLE_SECONDS`; set it to `0` only if every authenticated request must be audited.
