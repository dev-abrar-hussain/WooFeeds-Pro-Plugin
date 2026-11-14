import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { newDb } from 'pg-mem';
import { LicenseError, LicenseService } from '../src/license-service.js';

async function serviceFixture() {
  const memory = newDb();
  const { Pool } = memory.adapters.createPg();
  const pool = new Pool();
  const directory = path.dirname(fileURLToPath(import.meta.url));
  const rawSql = await fs.readFile(path.resolve(directory, '../sql/001_initial.sql'), 'utf8');
  await pool.query(rawSql.replace('ip_address INET', 'ip_address TEXT'));
  const config = { encryptionKey: 'test-encryption-key-with-at-least-32-characters' };
  return { pool, service: new LicenseService(pool, config) };
}

test('a license locks to its first domain and can be deliberately moved', async () => {
  const { pool, service } = await serviceFixture();
  try {
    const issued = await service.issue({ email: 'buyer@example.com', provider: 'manual', providerOrderId: 'order-1' });
    assert.match(issued.license_key, /^WFP-/);

    const activated = await service.activate({ licenseKey: issued.license_key, domain: 'https://www.example.com/shop', siteUrl: 'https://example.com/' });
    assert.equal(activated.domain, 'example.com');

    const valid = await service.validate({ licenseKey: issued.license_key, domain: 'example.com', siteUrl: 'https://example.com/' });
    assert.equal(valid.status, 'active');

    await assert.rejects(
      () => service.activate({ licenseKey: issued.license_key, domain: 'other.example', siteUrl: 'https://other.example/' }),
      (error) => error instanceof LicenseError && error.code === 'domain_locked' && error.status === 409
    );

    await service.deactivate({ licenseKey: issued.license_key, domain: 'example.com', siteUrl: 'https://example.com/' });
    const moved = await service.activate({ licenseKey: issued.license_key, domain: 'other.example', siteUrl: 'https://other.example/' });
    assert.equal(moved.domain, 'other.example');
  } finally {
    await pool.end();
  }
});

test('provider order issuance is idempotent and full refunds revoke validation', async () => {
  const { pool, service } = await serviceFixture();
  try {
    const first = await service.issue({ email: 'buyer@example.com', provider: 'paddle', providerOrderId: 'txn_1' });
    const retry = await service.issue({ email: 'buyer@example.com', provider: 'paddle', providerOrderId: 'txn_1' });
    assert.equal(retry.license_key, first.license_key);

    await service.activate({ licenseKey: first.license_key, domain: 'example.com', siteUrl: 'https://example.com/' });
    const refunded = await service.markProviderOrderStatus('paddle', 'txn_1', 'refunded');
    assert.equal(refunded.status, 'refunded');
    await assert.rejects(
      () => service.validate({ licenseKey: first.license_key, domain: 'example.com', siteUrl: 'https://example.com/' }),
      (error) => error instanceof LicenseError && error.code === 'license_inactive' && error.status === 403
    );
  } finally {
    await pool.end();
  }
});

