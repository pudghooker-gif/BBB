import crypto from 'k6/crypto';
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    public_readiness: {
      executor: 'constant-vus',
      vus: Number(__ENV.K6_PUBLIC_VUS || 2),
      duration: __ENV.K6_PUBLIC_DURATION || '1m',
      exec: 'publicReadiness',
    },
    signed_operator_reads: {
      executor: 'constant-vus',
      vus: Number(__ENV.K6_SIGNED_VUS || 1),
      duration: __ENV.K6_SIGNED_DURATION || '1m',
      exec: 'signedOperatorReads',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<750'],
  },
};

const baseUrl = (__ENV.BASE_URL || 'https://b2b.example.com').replace(/\/$/, '');
const operatorId = __ENV.B2B_OPERATOR_ID || '';
const apiKey = __ENV.B2B_API_KEY || '';
const apiSecret = __ENV.B2B_API_SECRET || '';

export function publicReadiness() {
  const readiness = http.get(`${baseUrl}/api/b2b/v1/readiness`, {
    headers: { Accept: 'application/json' },
    tags: { endpoint: 'readiness' },
  });
  check(readiness, {
    'readiness status is 200': (r) => r.status === 200,
    'readiness body is ready': (r) => r.body.indexOf('"status":"ready"') !== -1,
  });

  const metrics = http.get(`${baseUrl}/api/b2b/v1/metrics`, {
    headers: { Accept: 'text/plain' },
    tags: { endpoint: 'metrics' },
  });
  check(metrics, {
    'metrics status is 200': (r) => r.status === 200,
    'metrics exposes b2b info': (r) => r.body.indexOf('bbb_b2b_info') !== -1,
  });

  sleep(1);
}

export function signedOperatorReads() {
  if (!operatorId || !apiKey || !apiSecret) {
    sleep(1);
    return;
  }

  const operator = signedGet('/api/b2b/v1/operator/me', '');
  check(operator, {
    'operator/me status is 200': (r) => r.status === 200,
    'operator/me success envelope': (r) => r.body.indexOf('"success":true') !== -1,
  });

  const portal = signedGet('/api/b2b/v1/portal/overview', 'limit=1');
  check(portal, {
    'portal overview status is 200': (r) => r.status === 200,
    'portal overview success envelope': (r) => r.body.indexOf('"success":true') !== -1,
  });

  sleep(1);
}

function signedGet(path, query) {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = `k6-${__VU}-${__ITER}-${timestamp}`;
  const bodyHash = crypto.sha256('', 'hex');
  const canonical = ['GET', path, query, bodyHash.toLowerCase(), timestamp, nonce].join('\n');
  const signature = crypto.hmac('sha256', apiSecret, canonical, 'hex');
  const requestPath = query ? `${path}?${query}` : path;

  return http.get(`${baseUrl}${requestPath}`, {
    headers: {
      Accept: 'application/json',
      'X-Operator-Id': operatorId,
      'X-Api-Key': apiKey,
      'X-Timestamp': timestamp,
      'X-Nonce': nonce,
      'X-Body-Hash': bodyHash,
      'X-Signature': signature,
    },
    tags: { endpoint: query ? `${path}?${query}` : path },
  });
}
