import express from 'express';
import helmet from 'helmet';
import { rateLimit } from 'express-rate-limit';
import { LicenseError } from './license-service.js';
import { secureEqual } from './security.js';
import { registerWebhookRoutes } from './webhooks.js';

export function createApp(dependencies) {
  const { config, licenseService, mailer } = dependencies;
  const app = express();
  app.disable('x-powered-by');
  app.set('trust proxy', 1);
  app.use(helmet());

  registerWebhookRoutes(app, dependencies);
  app.use(express.json({ limit: '64kb' }));
  app.use('/v1/licenses', rateLimit({ windowMs: 15 * 60 * 1000, limit: 120, standardHeaders: 'draft-8', legacyHeaders: false }));

  app.get('/health', async (req, res) => {
    try {
      await dependencies.pool.query('SELECT 1');
      res.json({ ok: true, service: 'woofeeds-license-server' });
    } catch {
      res.status(503).json({ ok: false });
    }
  });

  app.post('/v1/licenses/issue', requireAdmin(config), asyncHandler(async (req, res) => {
    if (req.body.send_email !== false && !isEmail(req.body.email)) {
      return res.status(400).json({ success: false, code: 'invalid_email', message: 'A valid buyer email is required for automatic delivery.' });
    }
    const license = await licenseService.issue({
      email: req.body.email,
      provider: req.body.provider || 'manual',
      providerOrderId: req.body.provider_order_id || null,
      metadata: req.body.metadata || {}
    });
    if (req.body.send_email !== false) await mailer.sendLicense(license.email, license.license_key);
    res.status(201).json({ success: true, message: 'License issued.', license });
  }));

  app.post('/v1/licenses/activate', asyncHandler(async (req, res) => {
    const license = await licenseService.activate(requestData(req));
    res.json({ success: true, message: 'License activated.', license });
  }));

  app.post('/v1/licenses/validate', asyncHandler(async (req, res) => {
    const license = await licenseService.validate(requestData(req));
    res.json({ success: true, message: 'License is valid.', license });
  }));

  app.post('/v1/licenses/deactivate', asyncHandler(async (req, res) => {
    const license = await licenseService.deactivate(requestData(req));
    res.json({ success: true, message: 'License deactivated.', license });
  }));

  app.use((req, res) => res.status(404).json({ success: false, code: 'not_found', message: 'Endpoint not found.' }));
  app.use((error, req, res, next) => { // eslint-disable-line no-unused-vars
    const status = error instanceof LicenseError ? error.status : 500;
    const code = error instanceof LicenseError ? error.code : 'server_error';
    if (status >= 500) console.error(error);
    res.status(status).json({ success: false, code, message: status >= 500 ? 'The license server could not complete the request.' : error.message });
  });
  return app;
}

function requestData(req) {
  return {
    licenseKey: req.body.license_key,
    domain: req.body.domain,
    siteUrl: req.body.site_url,
    context: { ip: req.ip, userAgent: req.get('user-agent') || '', details: { plugin_version: req.body.plugin_version || '' } }
  };
}

function requireAdmin(config) {
  return (req, res, next) => {
    const header = req.get('authorization') || '';
    const token = header.startsWith('Bearer ') ? header.slice(7) : '';
    if (!secureEqual(token, config.adminToken)) return res.status(401).json({ success: false, code: 'unauthorized', message: 'Invalid admin token.' });
    next();
  };
}

function asyncHandler(handler) {
  return (req, res, next) => Promise.resolve(handler(req, res, next)).catch(next);
}

function isEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || ''));
}

