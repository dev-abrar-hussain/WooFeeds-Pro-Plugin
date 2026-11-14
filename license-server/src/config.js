import 'dotenv/config';

function required(name, minLength = 1) {
  const value = process.env[name]?.trim();
  if (!value || value.length < minLength) {
    throw new Error(`${name} is required and must contain at least ${minLength} characters.`);
  }
  return value;
}

function csv(name) {
  return (process.env[name] || '').split(',').map((value) => value.trim()).filter(Boolean);
}

export function loadConfig({ allowIncomplete = false } = {}) {
  const optionalRequired = (name, min) => allowIncomplete ? (process.env[name]?.trim() || '') : required(name, min);
  return {
    env: process.env.NODE_ENV || 'development',
    port: Number(process.env.PORT || 8080),
    databaseUrl: optionalRequired('DATABASE_URL', 8),
    databaseSsl: process.env.DATABASE_SSL === 'true',
    adminToken: optionalRequired('ADMIN_API_TOKEN', 32),
    encryptionKey: optionalRequired('LICENSE_ENCRYPTION_KEY', 32),
    publicAppUrl: process.env.PUBLIC_APP_URL || '',
    lemon: {
      secret: process.env.LEMONSQUEEZY_WEBHOOK_SECRET || '',
      variantIds: csv('LEMONSQUEEZY_VARIANT_IDS')
    },
    paddle: {
      secret: process.env.PADDLE_WEBHOOK_SECRET || '',
      apiKey: process.env.PADDLE_API_KEY || '',
      environment: process.env.PADDLE_ENVIRONMENT === 'sandbox' ? 'sandbox' : 'production',
      productIds: csv('PADDLE_PRODUCT_IDS')
    },
    smtp: {
      host: process.env.SMTP_HOST || '',
      port: Number(process.env.SMTP_PORT || 587),
      secure: process.env.SMTP_SECURE === 'true',
      user: process.env.SMTP_USER || '',
      pass: process.env.SMTP_PASS || '',
      from: process.env.SMTP_FROM || 'WooFeeds Pro <licenses@example.com>'
    }
  };
}

