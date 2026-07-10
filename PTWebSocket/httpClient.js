'use strict';

var http = require('http');
var https = require('https');
var URL = require('url').URL;

function hasHeader(headers, name) {
  var wanted = name.toLowerCase();
  return Object.keys(headers).some(function (key) {
    return key.toLowerCase() === wanted;
  });
}

function parseBody(body, response, shouldParseJson) {
  if (!shouldParseJson) {
    return body;
  }

  var contentType = response && response.headers && response.headers['content-type']
    ? String(response.headers['content-type'])
    : '';

  if (contentType.indexOf('json') === -1 && body.trim().charAt(0) !== '{' && body.trim().charAt(0) !== '[') {
    return body;
  }

  try {
    return JSON.parse(body);
  } catch (e) {
    return body;
  }
}

function request(options, callback) {
  var target;
  try {
    target = new URL(options.url);
  } catch (e) {
    process.nextTick(function () {
      callback(e);
    });
    return;
  }

  var headers = Object.assign({}, options.headers || {});
  var method = (options.method || 'GET').toUpperCase();
  var body = options.body;

  if (options.json) {
    body = body === undefined ? '' : JSON.stringify(body);
    if (!hasHeader(headers, 'Content-Type')) {
      headers['Content-Type'] = 'application/json';
    }
  } else if (body !== undefined && body !== null && !Buffer.isBuffer(body)) {
    body = String(body);
  }

  if (body !== undefined && body !== null && body !== '' && !hasHeader(headers, 'Content-Length')) {
    headers['Content-Length'] = Buffer.byteLength(body);
  }

  var requestOptions = {
    protocol: target.protocol,
    hostname: target.hostname,
    port: target.port || undefined,
    path: target.pathname + target.search,
    method: method,
    headers: headers
  };

  if (target.protocol === 'https:' && options.rejectUnauthorized === false) {
    requestOptions.rejectUnauthorized = false;
  }

  var client = target.protocol === 'https:' ? https : http;
  if (target.protocol !== 'https:' && target.protocol !== 'http:') {
    process.nextTick(function () {
      callback(new Error('Unsupported protocol: ' + target.protocol));
    });
    return;
  }

  var req = client.request(requestOptions, function (response) {
    var chunks = [];

    response.on('data', function (chunk) {
      chunks.push(chunk);
    });

    response.on('end', function () {
      var rawBody = Buffer.concat(chunks).toString('utf8');
      callback(null, response, parseBody(rawBody, response, !!options.json));
    });
  });

  req.on('error', function (error) {
    callback(error);
  });

  if (body !== undefined && body !== null && body !== '') {
    req.write(body);
  }

  req.end();
}

module.exports = request;
