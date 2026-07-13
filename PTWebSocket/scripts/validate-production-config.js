#!/usr/bin/env node

var fs = require('fs');
var path = require('path');

var configPath = process.argv[2] || path.join('..', 'deploy', 'websocket', 'socket_config2.production.example.json');
var absolutePath = path.resolve(process.cwd(), configPath);
var errors = [];
var warnings = [];

function fail(key, message) {
  errors.push(key + ': ' + message);
}

function warn(key, message) {
  warnings.push(key + ': ' + message);
}

function hasOwn(object, key) {
  return Object.prototype.hasOwnProperty.call(object, key);
}

function integerConfig(config, key, min, max) {
  if (!hasOwn(config, key) || !Number.isInteger(config[key])) {
    fail(key, 'must be an integer');
    return null;
  }

  if (config[key] < min || config[key] > max) {
    fail(key, 'must be between ' + min + ' and ' + max);
  }

  return config[key];
}

function pathConfig(config, key) {
  if (typeof config[key] !== 'string' || config[key].charAt(0) !== '/') {
    fail(key, 'must be an absolute HTTP path');
    return;
  }

  if (config[key].indexOf('?') !== -1 || config[key].indexOf('#') !== -1 || config[key].indexOf('//') !== -1) {
    fail(key, 'must not contain query strings, fragments, or duplicate slashes');
  }
}

function canonicalOrigin(value) {
  try {
    var parsed = new URL(value);
    if (parsed.username || parsed.password || parsed.pathname !== '/' || parsed.search || parsed.hash) {
      return null;
    }

    return parsed.origin;
  } catch (e) {
    return null;
  }
}

function validateOrigins(config) {
  var origins = config.allowed_origins;
  if (!Array.isArray(origins) || origins.length === 0) {
    fail('allowed_origins', 'must be a non-empty array');
    return [];
  }

  var seen = {};
  origins.forEach(function (origin, index) {
    var key = 'allowed_origins[' + index + ']';

    if (typeof origin !== 'string' || origin.trim() !== origin || origin === '') {
      fail(key, 'must be a trimmed HTTPS origin string');
      return;
    }

    if (origin === '*') {
      fail(key, 'wildcard origins are forbidden');
      return;
    }

    var canonical = canonicalOrigin(origin);
    if (!canonical || canonical !== origin || origin.indexOf('https://') !== 0) {
      fail(key, 'must be a canonical HTTPS origin without path, query, or fragment');
      return;
    }

    if (seen[origin]) {
      fail(key, 'duplicates ' + origin);
    }
    seen[origin] = true;
  });

  return origins;
}

function strictRuntimePlaceholderCheck(config) {
  var normalized = absolutePath.replace(/\\/g, '/');
  var strictRuntime = normalized.slice(-'/public/socket_config2.json'.length) === '/public/socket_config2.json'
    || process.env.BBB_WEBSOCKET_CONFIG_STRICT === '1';

  if (!strictRuntime) {
    return;
  }

  ['host', 'host_ws'].forEach(function (key) {
    if (typeof config[key] === 'string' && /(^|\.)example\./i.test(config[key])) {
      fail(key, 'must be replaced with the final production domain');
    }
  });

  (config.allowed_origins || []).forEach(function (origin, index) {
    if (typeof origin === 'string' && /(^https:\/\/|\.)(example\.)/i.test(origin)) {
      fail('allowed_origins[' + index + ']', 'must be replaced with the final production origin');
    }
  });
}

function validate(config) {
  if (config.ssl !== false) {
    fail('ssl', 'Node must run plain HTTP behind the Nginx TLS proxy');
  }

  if (config.listen_host !== '127.0.0.1') {
    fail('listen_host', 'must be 127.0.0.1 so the WebSocket runtime is not public');
  }

  integerConfig(config, 'port', 1, 65535);
  integerConfig(config, 'listen_port', 1, 65535);

  if (hasOwn(config, 'port') && hasOwn(config, 'listen_port') && config.port === config.listen_port) {
    fail('listen_port', 'must be separate from the public TLS port');
  }

  if (config.prefix !== 'https://') {
    fail('prefix', 'must be https:// for production launcher URLs');
  }

  if (config.prefix_ws !== 'wss://') {
    fail('prefix_ws', 'must be wss:// for production WebSocket URLs');
  }

  pathConfig(config, 'health_path');
  pathConfig(config, 'ready_path');
  if (config.health_path && config.ready_path && config.health_path === config.ready_path) {
    fail('ready_path', 'must differ from health_path');
  }

  validateOrigins(config);

  if (config.require_session_cookie !== true) {
    fail('require_session_cookie', 'must be true for legacy Laravel session handshakes');
  }

  if (config.log_json !== true) {
    fail('log_json', 'must be true so host log shipping receives structured events');
  }

  if (hasOwn(config, 'auth_tokens')) {
    fail('auth_tokens', 'inline WebSocket tokens are forbidden; use auth_tokens_env');
  }

  if (typeof config.auth_tokens_env !== 'string' || config.auth_tokens_env.trim() === '') {
    fail('auth_tokens_env', 'must name the host secret-store environment variable');
  }

  ['auth_header', 'auth_query_param'].forEach(function (key) {
    if (hasOwn(config, key) && (typeof config[key] !== 'string' || config[key].trim() === '' || /[\r\n]/.test(config[key]))) {
      fail(key, 'must be a non-empty header/query key without newlines');
    }
  });

  var heartbeat = integerConfig(config, 'heartbeat_interval_ms', 5000, 120000);
  var idle = integerConfig(config, 'idle_timeout_ms', 10000, 600000);
  integerConfig(config, 'max_connections', 1, 1000000);

  if (heartbeat !== null && idle !== null && idle <= heartbeat) {
    fail('idle_timeout_ms', 'must be greater than heartbeat_interval_ms');
  }

  if (config.auth_required !== true) {
    warn('auth_required', 'token auth is optional; require it if the final provider/proxy contract needs URL or header tokens');
  }

  strictRuntimePlaceholderCheck(config);
}

var config = null;
try {
  config = JSON.parse(fs.readFileSync(absolutePath, 'utf8'));
} catch (e) {
  fail('json', 'cannot read or parse ' + absolutePath + ': ' + e.message);
}

if (config && (typeof config !== 'object' || Array.isArray(config))) {
  fail('json', 'top-level value must be an object');
}

if (config && errors.length === 0) {
  validate(config);
}

var result = {
  status: errors.length === 0 ? 'ok' : 'fail',
  config_path: absolutePath,
  errors: errors,
  warnings: warnings
};

console.log(JSON.stringify(result, null, 2));
process.exit(errors.length === 0 ? 0 : 1);
