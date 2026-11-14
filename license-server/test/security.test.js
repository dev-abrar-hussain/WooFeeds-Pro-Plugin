import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import test from 'node:test';
import { decryptLicenseKey, encryptLicenseKey, generateLicenseKey, hashLicenseKey, normalizeDomain, verifyLemonSignature, verifyPaddleSignature } from '../src/security.js';

test('license keys have the expected format and stable hash', () => {
  const key = generateLicenseKey();
  assert.match(key, /^WFP(?:-[A-Z2-9]{5}){5}$/);
  assert.equal(hashLicenseKey(key.toLowerCase()), hashLicenseKey(key));
});

test('license key encryption round-trips', () => {
  const secret = 'a-production-secret-that-is-long-enough';
  const key = generateLicenseKey();
  assert.equal(decryptLicenseKey(encryptLicenseKey(key, secret), secret), key);
});

test('domains are normalized consistently', () => {
  assert.equal(normalizeDomain('https://WWW.Example.com/shop'), 'example.com');
  assert.equal(normalizeDomain('example.com'), 'example.com');
});

test('Lemon Squeezy signatures verify against raw body', () => {
  const body = Buffer.from('{"event":"test"}');
  const secret = 'lemon-secret';
  const signature = crypto.createHmac('sha256', secret).update(body).digest('hex');
  assert.equal(verifyLemonSignature(body, signature, secret), true);
  assert.equal(verifyLemonSignature(body, 'bad', secret), false);
});

test('Paddle signatures verify timestamp and raw body', () => {
  const now = 1700000000;
  const body = Buffer.from('{"event_id":"evt_1"}');
  const secret = 'paddle-secret';
  const signature = crypto.createHmac('sha256', secret).update(`${now}:${body}`).digest('hex');
  assert.equal(verifyPaddleSignature(body, `ts=${now};h1=${signature}`, secret, now), true);
  assert.equal(verifyPaddleSignature(body, `ts=${now - 1000};h1=${signature}`, secret, now), false);
});

