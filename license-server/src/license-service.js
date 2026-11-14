import crypto from 'node:crypto';
import { transaction } from './db.js';
import { decryptLicenseKey, encryptLicenseKey, generateLicenseKey, hashLicenseKey, normalizeDomain, normalizeKey } from './security.js';

export class LicenseError extends Error {
  constructor(code, message, status = 400) {
    super(message);
    this.code = code;
    this.status = status;
  }
}

export class LicenseService {
  constructor(pool, config) {
    this.pool = pool;
    this.config = config;
  }

  async issue({ email = null, provider = 'manual', providerOrderId = null, metadata = {} }) {
    return transaction(this.pool, async (client) => {
      if (providerOrderId) {
        const existing = await client.query('SELECT * FROM licenses WHERE provider = $1 AND provider_order_id = $2 FOR UPDATE', [provider, providerOrderId]);
        if (existing.rowCount) return this.present(existing.rows[0], true);
      }

      const key = generateLicenseKey();
      const id = crypto.randomUUID();
      const normalizedEmail = email ? String(email).trim().toLowerCase() : null;
      const result = await client.query(
        `INSERT INTO licenses (id, key_hash, key_prefix, key_last4, key_encrypted, email, provider, provider_order_id, metadata)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING *`,
        [id, hashLicenseKey(key), key.slice(0, 9), key.slice(-4), encryptLicenseKey(key, this.config.encryptionKey), normalizedEmail, provider, providerOrderId, metadata]
      );
      await this.audit(client, result.rows[0], 'issued', null, null, {});
      return { ...this.present(result.rows[0]), license_key: key };
    });
  }

  async activate({ licenseKey, domain, siteUrl, context = {} }) {
    return transaction(this.pool, async (client) => {
      const row = await this.lockByKey(client, licenseKey);
      this.assertUsable(row);
      const normalized = normalizeDomain(domain);
      if (row.domain && row.domain !== normalized) {
        await this.audit(client, row, 'activation_rejected', normalized, siteUrl, context);
        throw new LicenseError('domain_locked', 'This license is already active on another domain', 409);
      }
      const result = await client.query(
        `UPDATE licenses SET domain=$1, site_url=$2, activated_at=COALESCE(activated_at,NOW()), last_validated_at=NOW(), updated_at=NOW()
         WHERE id=$3 RETURNING *`,
        [normalized, siteUrl, row.id]
      );
      await this.audit(client, row, row.domain ? 'reactivated' : 'activated', normalized, siteUrl, context);
      return this.present(result.rows[0]);
    });
  }

  async validate({ licenseKey, domain, siteUrl, context = {} }) {
    return transaction(this.pool, async (client) => {
      const row = await this.lockByKey(client, licenseKey);
      this.assertUsable(row);
      const normalized = normalizeDomain(domain);
      if (!row.domain) throw new LicenseError('not_activated', 'Activate this license before validating it.', 409);
      if (row.domain !== normalized) {
        await this.audit(client, row, 'validation_rejected', normalized, siteUrl, context);
        throw new LicenseError('domain_mismatch', 'This license is already active on another domain', 409);
      }
      const result = await client.query('UPDATE licenses SET last_validated_at=NOW(), site_url=$1, updated_at=NOW() WHERE id=$2 RETURNING *', [siteUrl, row.id]);
      await this.audit(client, row, 'validated', normalized, siteUrl, context);
      return this.present(result.rows[0]);
    });
  }

  async deactivate({ licenseKey, domain, siteUrl, context = {} }) {
    return transaction(this.pool, async (client) => {
      const row = await this.lockByKey(client, licenseKey);
      this.assertUsable(row);
      const normalized = normalizeDomain(domain);
      if (row.domain && row.domain !== normalized) throw new LicenseError('domain_mismatch', 'This license is active on another domain.', 409);
      const result = await client.query('UPDATE licenses SET domain=NULL, site_url=NULL, deactivated_at=NOW(), updated_at=NOW() WHERE id=$1 RETURNING *', [row.id]);
      await this.audit(client, row, 'deactivated', normalized, siteUrl, context);
      return this.present(result.rows[0]);
    });
  }

  async markProviderOrderStatus(provider, providerOrderId, status) {
    if (!['disabled', 'refunded'].includes(status)) throw new Error('Unsupported license status.');
    const result = await this.pool.query(
      'UPDATE licenses SET status=$1, updated_at=NOW() WHERE provider=$2 AND provider_order_id=$3 RETURNING *',
      [status, provider, providerOrderId]
    );
    return result.rowCount ? this.present(result.rows[0]) : null;
  }

  async lockByKey(client, key) {
    const normalized = normalizeKey(key);
    if (!/^WFP(?:-[A-Z2-9]{5}){5}$/.test(normalized)) throw new LicenseError('invalid_license', 'The license key is invalid.', 404);
    const result = await client.query('SELECT * FROM licenses WHERE key_hash=$1 FOR UPDATE', [hashLicenseKey(normalized)]);
    if (!result.rowCount) throw new LicenseError('invalid_license', 'The license key is invalid.', 404);
    return result.rows[0];
  }

  assertUsable(row) {
    if (row.status !== 'active') throw new LicenseError('license_inactive', 'This license is not active.', 403);
    if (row.expires_at && new Date(row.expires_at) <= new Date()) throw new LicenseError('license_expired', 'This license has expired.', 403);
  }

  present(row, includeDecrypted = false) {
    const result = {
      id: row.id,
      key: `${row.key_prefix}...${row.key_last4}`,
      email: row.email,
      status: row.status,
      domain: row.domain,
      provider: row.provider,
      activated_at: row.activated_at,
      last_validated_at: row.last_validated_at,
      expires_at: row.expires_at,
      created_at: row.created_at
    };
    if (includeDecrypted) result.license_key = decryptLicenseKey(row.key_encrypted, this.config.encryptionKey);
    return result;
  }

  async audit(client, row, type, domain, siteUrl, context) {
    await client.query(
      'INSERT INTO license_events (license_id,event_type,domain,site_url,ip_address,user_agent,details) VALUES ($1,$2,$3,$4,$5,$6,$7)',
      [row.id, type, domain, siteUrl || null, context.ip || null, context.userAgent || null, context.details || {}]
    );
  }
}

