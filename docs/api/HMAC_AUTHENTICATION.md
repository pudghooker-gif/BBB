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
$operatorId = 'op_demo';
$apiKey = 'key_public_id';
$secret = 'replace-with-one-time-secret';
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

$headers = [
    'X-Operator-Id' => $operatorId,
    'X-Api-Key' => $apiKey,
    'X-Timestamp' => $timestamp,
    'X-Nonce' => $nonce,
    'X-Body-Hash' => $bodyHash,
    'X-Signature' => $signature,
];
```

## Node.js Example

This example signs a `GET /operator/me` request with an empty query string. For non-empty queries, build `query` with the same sorted RFC3986 canonicalization rules described above.

```js
import crypto from "node:crypto";

const operatorId = "op_demo";
const apiKey = "key_public_id";
const secret = "replace-with-one-time-secret";
const method = "GET";
const path = "/api/b2b/v1/operator/me";
const query = "";
const body = "";
const timestamp = Math.floor(Date.now() / 1000).toString();
const nonce = crypto.randomBytes(16).toString("hex");
const bodyHash = crypto.createHash("sha256").update(body, "utf8").digest("hex");

const canonical = [
  method,
  path,
  query,
  bodyHash,
  timestamp,
  nonce,
].join("\n");

const signature = crypto
  .createHmac("sha256", secret)
  .update(canonical, "utf8")
  .digest("hex");

const response = await fetch(`https://api.example.com${path}`, {
  method,
  headers: {
    "X-Operator-Id": operatorId,
    "X-Api-Key": apiKey,
    "X-Timestamp": timestamp,
    "X-Nonce": nonce,
    "X-Body-Hash": bodyHash,
    "X-Signature": signature,
  },
});

console.log(response.status, await response.text());
```

## cURL Example

This shell example signs and sends the same empty-body request using `openssl` for SHA-256 and HMAC.

```bash
operator_id="op_demo"
api_key="key_public_id"
secret="replace-with-one-time-secret"
method="GET"
path="/api/b2b/v1/operator/me"
query=""
body=""
timestamp="$(date +%s)"
nonce="$(openssl rand -hex 16)"
body_hash="$(printf '%s' "$body" | openssl dgst -sha256 -binary | xxd -p -c 256)"
canonical="$(printf '%s\n%s\n%s\n%s\n%s\n%s' "$method" "$path" "$query" "$body_hash" "$timestamp" "$nonce")"
signature="$(printf '%s' "$canonical" | openssl dgst -sha256 -hmac "$secret" -binary | xxd -p -c 256)"

curl --request "$method" "https://api.example.com${path}" \
  --header "X-Operator-Id: ${operator_id}" \
  --header "X-Api-Key: ${api_key}" \
  --header "X-Timestamp: ${timestamp}" \
  --header "X-Nonce: ${nonce}" \
  --header "X-Body-Hash: ${body_hash}" \
  --header "X-Signature: ${signature}"
```

## Artisan Helper

The local helper prints signed headers and a cURL example:

```bash
php artisan b2b:show-hmac op_xxx key_xxx secret_xxx GET /api/b2b/v1/operator/me
```

## Production Notes

`B2B_NONCE_CACHE_STORE` and `B2B_RATE_LIMIT_CACHE_STORE` default to `redis` so replay protection and app-level rate limits work across multiple API nodes in production. Test/local environments without Redis must explicitly override them to an isolated non-shared store. Keep the sandbox/private callback exception disabled outside isolated local testing.

Successful HMAC authentication updates `last_used_at` and writes a throttled `api_key.used` audit event with method, path, IP address, request ID, and key ID. It does not store API secrets, signatures, nonce values, or request bodies. Tune the sampling window with `B2B_API_KEY_USAGE_AUDIT_SAMPLE_SECONDS`; set it to `0` only if every authenticated request must be audited.
