#!/usr/bin/env node

var fs = require('fs');
var path = require('path');
var http = require('http');
var https = require('https');
var WebSocket = require('ws');

var publicUrl = process.env.WEBSOCKET_PUBLIC_URL || 'wss://b2b.example.com:12096';
var publicOrigin = process.env.WEBSOCKET_PUBLIC_ORIGIN || 'https://b2b.example.com';
var timeoutMs = parseInt(process.env.WEBSOCKET_SMOKE_TIMEOUT_MS || '10000', 10);
var artifactDir = process.env.WEBSOCKET_SMOKE_ARTIFACT_DIR || path.join('..', 'storage', 'logs');
var artifactPath = process.env.WEBSOCKET_SMOKE_ARTIFACT
  || path.join(artifactDir, 'websocket-public-proxy-healthz.log');
var healthPath = process.env.WEBSOCKET_HEALTH_PATH || '/healthz';
var denyOrigin = process.env.WEBSOCKET_SMOKE_DENY_ORIGIN || 'https://invalid-origin.example.invalid';
var authToken = process.env.WEBSOCKET_SMOKE_AUTH_TOKEN || '';
var authHeader = process.env.WEBSOCKET_SMOKE_AUTH_HEADER || 'x-bbb-websocket-token';
var checks = [];

function redactedUrl(rawUrl) {
  try {
    var parsed = new URL(rawUrl);
    parsed.username = '';
    parsed.password = '';
    parsed.search = '';
    parsed.hash = '';
    return parsed.toString();
  } catch (e) {
    return String(rawUrl).split('?')[0];
  }
}

function healthUrlFromWebSocket(rawUrl) {
  var parsed = new URL(rawUrl);
  parsed.protocol = parsed.protocol === 'wss:' ? 'https:' : 'http:';
  parsed.pathname = healthPath;
  parsed.search = '';
  parsed.hash = '';
  return parsed.toString();
}

function record(name, status, details) {
  var safeDetails = details || {};
  checks.push({
    name: name,
    status: status,
    details: safeDetails
  });
}

function requestHealth(rawUrl) {
  return new Promise(function (resolve, reject) {
    var parsed = new URL(rawUrl);
    var client = parsed.protocol === 'https:' ? https : http;
    var req = client.request({
      protocol: parsed.protocol,
      hostname: parsed.hostname,
      port: parsed.port,
      path: parsed.pathname,
      method: 'GET',
      timeout: timeoutMs,
      rejectUnauthorized: process.env.WEBSOCKET_SMOKE_INSECURE_TLS === '1' ? false : true,
      headers: {
        accept: 'application/json',
        origin: publicOrigin
      }
    }, function (res) {
      var body = '';
      res.setEncoding('utf8');
      res.on('data', function (chunk) {
        body += chunk;
      });
      res.on('end', function () {
        if (res.statusCode < 200 || res.statusCode >= 300) {
          reject(new Error('health returned HTTP ' + res.statusCode));
          return;
        }

        try {
          var payload = JSON.parse(body);
          if (payload.service !== 'bbb-websocket' || payload.status !== 'ok') {
            reject(new Error('health payload is not the expected bbb-websocket ok response'));
            return;
          }

          resolve(payload);
        } catch (e) {
          reject(new Error('health response is not JSON: ' + e.message));
        }
      });
    });

    req.on('timeout', function () {
      req.destroy(new Error('health request timed out'));
    });
    req.on('error', reject);
    req.end();
  });
}

function websocketOptions(origin) {
  var headers = {
    origin: origin
  };

  if (authToken !== '') {
    headers[authHeader] = authToken;
  }

  return {
    headers: headers,
    handshakeTimeout: timeoutMs,
    rejectUnauthorized: process.env.WEBSOCKET_SMOKE_INSECURE_TLS === '1' ? false : true
  };
}

function expectAccepted(rawUrl, origin) {
  return new Promise(function (resolve, reject) {
    var socket = new WebSocket(rawUrl, websocketOptions(origin));
    var done = false;

    function finish(error) {
      if (done) {
        return;
      }
      done = true;
      clearTimeout(timer);

      if (socket.readyState === WebSocket.OPEN) {
        socket.close(1000, 'smoke-complete');
      }

      if (error) {
        reject(error);
      } else {
        resolve();
      }
    }

    var timer = setTimeout(function () {
      finish(new Error('websocket upgrade timed out'));
    }, timeoutMs);

    socket.on('open', function () {
      finish();
    });

    socket.on('unexpected-response', function (req, res) {
      finish(new Error('websocket upgrade returned HTTP ' + res.statusCode));
    });

    socket.on('error', finish);
  });
}

function expectDenied(rawUrl, origin) {
  return new Promise(function (resolve, reject) {
    var socket = new WebSocket(rawUrl, websocketOptions(origin));
    var done = false;

    function finish(error) {
      if (done) {
        return;
      }
      done = true;
      clearTimeout(timer);

      if (socket.readyState === WebSocket.OPEN) {
        socket.close(1000, 'smoke-deny-check');
      }

      if (error) {
        reject(error);
      } else {
        resolve();
      }
    }

    var timer = setTimeout(function () {
      finish(new Error('denied-origin websocket check timed out'));
    }, timeoutMs);

    socket.on('open', function () {
      finish(new Error('denied origin was accepted'));
    });

    socket.on('unexpected-response', function (req, res) {
      if ([400, 401, 403].indexOf(res.statusCode) !== -1) {
        finish();
        return;
      }

      finish(new Error('denied-origin check returned HTTP ' + res.statusCode));
    });

    socket.on('error', function () {
      finish();
    });
  });
}

function writeArtifact(status, extra) {
  var payload = {
    status: status,
    generated_at: new Date().toISOString(),
    public_url: redactedUrl(publicUrl),
    health_url: redactedUrl(healthUrlFromWebSocket(publicUrl)),
    origin: publicOrigin,
    deny_origin: denyOrigin,
    auth_token_supplied: authToken !== '',
    checks: checks
  };

  Object.keys(extra || {}).forEach(function (key) {
    payload[key] = extra[key];
  });

  fs.mkdirSync(path.dirname(artifactPath), { recursive: true });
  fs.writeFileSync(artifactPath, JSON.stringify(payload, null, 2) + '\n');
  return path.resolve(artifactPath);
}

(async function run() {
  var healthUrl = process.env.WEBSOCKET_HEALTH_URL || healthUrlFromWebSocket(publicUrl);

  try {
    var health = await requestHealth(healthUrl);
    record('public_healthz', 'passed', {
      url: redactedUrl(healthUrl),
      active_connections: health.active_connections,
      uptime_seconds: health.uptime_seconds
    });

    await expectAccepted(publicUrl, publicOrigin);
    record('websocket_upgrade_allowed_origin', 'passed', {
      url: redactedUrl(publicUrl),
      origin: publicOrigin
    });

    if (process.env.WEBSOCKET_SMOKE_SKIP_DENY_ORIGIN !== '1') {
      await expectDenied(publicUrl, denyOrigin);
      record('websocket_upgrade_denied_origin', 'passed', {
        url: redactedUrl(publicUrl),
        origin: denyOrigin
      });
    }

    var okPath = writeArtifact('passed');
    console.log('WebSocket public proxy smoke passed. Artifact: ' + okPath);
  } catch (e) {
    record('websocket_public_proxy_smoke', 'failed', {
      error: e.message
    });
    var failPath = writeArtifact('failed', {
      error: e.message
    });
    console.error('WebSocket public proxy smoke failed. Artifact: ' + failPath);
    console.error(e.message);
    process.exit(1);
  }
})();
