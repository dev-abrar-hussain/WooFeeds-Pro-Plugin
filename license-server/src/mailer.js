import nodemailer from 'nodemailer';

export class LicenseMailer {
  constructor(config) {
    this.config = config;
    this.transport = config.smtp.host ? nodemailer.createTransport({
      host: config.smtp.host,
      port: config.smtp.port,
      secure: config.smtp.secure,
      auth: config.smtp.user ? { user: config.smtp.user, pass: config.smtp.pass } : undefined
    }) : null;
  }

  async sendLicense(email, key) {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '')) || String(email).length > 320) {
      throw new Error('The purchase did not include a valid customer email address.');
    }
    if (!this.transport) throw new Error('SMTP is not configured; the license cannot be delivered automatically.');
    await this.transport.sendMail({
      from: this.config.smtp.from,
      to: email,
      subject: 'Your WooFeeds Pro license',
      text: `Thank you for purchasing WooFeeds Pro.\n\nYour license key is:\n${key}\n\nEnter this key in WooCommerce > WooFeeds Pro > License. The first activation locks the key to that domain. Deactivate it before moving to another domain.\n`,
      html: `<p>Thank you for purchasing WooFeeds Pro.</p><p>Your license key is:</p><p><strong>${escapeHtml(key)}</strong></p><p>Enter it in <strong>WooCommerce &gt; WooFeeds Pro &gt; License</strong>. The first activation locks the key to that domain. Deactivate it before moving to another domain.</p>`
    });
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
}

