import { createApp } from './app.js';
import { loadConfig } from './config.js';
import { createPool } from './db.js';
import { LicenseService } from './license-service.js';
import { LicenseMailer } from './mailer.js';

const config = loadConfig();
const pool = createPool(config);
const licenseService = new LicenseService(pool, config);
const mailer = new LicenseMailer(config);
const app = createApp({ config, pool, licenseService, mailer });

const server = app.listen(config.port, () => {
  console.log(`WooFeeds license server listening on port ${config.port}`);
});

async function shutdown(signal) {
  console.log(`${signal} received; closing license server.`);
  server.close(async () => {
    await pool.end();
    process.exit(0);
  });
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

