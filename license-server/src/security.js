import crypto from 'node:crypto';

const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

export function generateLicenseKey() {
  const bytes = crypto.randomBytes(25);
  let body = '';
  for (const byte of bytes) body += ALPHABET[byte % ALPHABET.length];
  return `WFP-${body.match(/.{1,5}/g).join('-')}`;
}

export function hashLicenseKey(key) {
  return crypto.createHash('sha256').update(normalizeKey(key)).digest('hex');
}

export function normalizeKey(key) {
  return String(key || '').trim().toUpperCase();
}

export function normalizeDomain(value) {
  let raw = String(value || '').trim().toLowerCase();
  if (!raw) throw new Error('A domain is required.');
  if (!raw.includes('://')) raw = `https://${raw}`;
  const host = new URL(raw).hostname.replace(/^www\./, '').replace(/\.$/, '');
  if (!host || host.length > 253 || host.includes('..')) throw new Error('The domain is invalid.');
  return host;
}

function encryptionKey(secret) {
  return crypto.createHash('sha256').update(secret).digest();
}

export function encryptLicenseKey(key, secret) {
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', encryptionKey(secret), iv);
  const encrypted = Buffer.concat([cipher.update(normalizeKey(key), 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return [iv, tag, encrypted].map((value) => value.toString('base64url')).join('.');
}

export function decryptLicenseKey(payload, secret) {
  const [iv, tag, encrypted] = String(payload).split('.').map((value) => Buffer.from(value, 'base64url'));
  if (!iv || !tag || !encrypted) throw new Error('Stored license key cannot be decrypted.');
  const decipher = crypto.createDecipheriv('aes-256-gcm', encryptionKey(secret), iv);
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(encrypted), decipher.final()]).toString('utf8');
}

export function secureEqual(left, right) {
  const a = Buffer.from(String(left || ''));
  const b = Buffer.from(String(right || ''));
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

export function verifyLemonSignature(rawBody, signature, secret) {
  if (!secret || !signature) return false;
  const expected = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
  return secureEqual(expected, signature);
}

export function verifyPaddleSignature(rawBody, signatureHeader, secret, nowSeconds = Math.floor(Date.now() / 1000), tolerance = 300) {
  if (!secret || !signatureHeader) return false;
  const values = Object.create(null);
  for (const part of signatureHeader.split(';')) {
    const [key, value] = part.split('=', 2);
    if (!values[key]) values[key] = [];
    values[key].push(value);
  }
  const timestamp = Number(values.ts?.[0]);
  if (!Number.isFinite(timestamp) || Math.abs(nowSeconds - timestamp) > tolerance) return false;
  const expected = crypto.createHmac('sha256', secret).update(`${timestamp}:${rawBody.toString('utf8')}`).digest('hex');
  return (values.h1 || []).some((signature) => secureEqual(expected, signature));
}

