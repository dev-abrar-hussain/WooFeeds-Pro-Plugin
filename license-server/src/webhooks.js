import { verifyLemonSignature, verifyPaddleSignature } from './security.js';

export function registerWebhookRoutes(app, { config, pool, licenseService, mailer }) {
  app.post('/v1/webhooks/lemonsqueezy', expressRaw(), async (req, res, next) => {
    try {
      const raw = req.body;
      if (!verifyLemonSignature(raw, req.get('X-Signature') || '', config.lemon.secret)) {
        return res.status(401).json({ success: false, code: 'invalid_signature', message: 'Invalid webhook signature.' });
      }
      const payload = JSON.parse(raw.toString('utf8'));
      const eventType = payload?.meta?.event_name || 'unknown';
      const orderId = String(payload?.data?.id || '');
      const eventId = `${eventType}:${orderId}`;
      if (!orderId) return res.status(400).json({ success: false, code: 'invalid_payload', message: 'Order ID is missing.' });
      if (await alreadyProcessed(pool, 'lemonsqueezy', eventId)) return res.json({ success: true, duplicate: true });

      if (eventType === 'order_refunded') {
        const attributes = payload?.data?.attributes || {};
        if (attributes.refunded === true || attributes.status === 'refunded') {
          await licenseService.markProviderOrderStatus('lemonsqueezy', orderId, 'refunded');
        }
        await recordWebhook(pool, 'lemonsqueezy', eventId, eventType, payload, true, false);
        return res.json({ success: true });
      }
      if (eventType !== 'order_created') {
        await recordWebhook(pool, 'lemonsqueezy', eventId, eventType, payload, true, false);
        return res.json({ success: true, ignored: true });
      }

      const attributes = payload?.data?.attributes || {};
      const variantId = String(attributes?.first_order_item?.variant_id || attributes?.variant_id || '');
      if (config.lemon.variantIds.length && !config.lemon.variantIds.includes(variantId)) {
        await recordWebhook(pool, 'lemonsqueezy', eventId, eventType, payload, true, false);
        return res.json({ success: true, ignored: true });
      }
      const license = await licenseService.issue({
        email: attributes.user_email,
        provider: 'lemonsqueezy',
        providerOrderId: orderId,
        metadata: { variant_id: variantId, order_number: attributes.order_number || null }
      });
      await mailer.sendLicense(license.email, license.license_key);
      await recordWebhook(pool, 'lemonsqueezy', eventId, eventType, payload, true, true);
      return res.json({ success: true });
    } catch (error) {
      next(error);
    }
  });

  app.post('/v1/webhooks/paddle', expressRaw(), async (req, res, next) => {
    try {
      const raw = req.body;
      if (!verifyPaddleSignature(raw, req.get('Paddle-Signature') || '', config.paddle.secret)) {
        return res.status(401).json({ success: false, code: 'invalid_signature', message: 'Invalid webhook signature.' });
      }
      const payload = JSON.parse(raw.toString('utf8'));
      const eventId = String(payload.event_id || '');
      const eventType = String(payload.event_type || 'unknown');
      const transactionId = String(payload?.data?.id || '');
      if (!eventId) return res.status(400).json({ success: false, code: 'invalid_payload', message: 'Event ID is missing.' });
      if (await alreadyProcessed(pool, 'paddle', eventId)) return res.json({ success: true, duplicate: true });

      if (['adjustment.created', 'adjustment.updated'].includes(eventType)) {
        const adjustment = payload.data || {};
        if (adjustment.action === 'refund' && adjustment.type === 'full' && adjustment.status === 'approved' && adjustment.transaction_id) {
          await licenseService.markProviderOrderStatus('paddle', String(adjustment.transaction_id), 'refunded');
        }
        await recordWebhook(pool, 'paddle', eventId, eventType, payload, true, false);
        return res.json({ success: true });
      }
      if (eventType !== 'transaction.completed') {
        await recordWebhook(pool, 'paddle', eventId, eventType, payload, true, false);
        return res.json({ success: true, ignored: true });
      }
      if (!transactionId) return res.status(400).json({ success: false, code: 'invalid_payload', message: 'Transaction ID is missing.' });

      const productIds = (payload?.data?.items || []).map((item) => String(item?.price?.product_id || '')).filter(Boolean);
      if (config.paddle.productIds.length && !productIds.some((id) => config.paddle.productIds.includes(id))) {
        await recordWebhook(pool, 'paddle', eventId, eventType, payload, true, false);
        return res.json({ success: true, ignored: true });
      }
      const email = await paddleCustomerEmail(payload.data, config);
      const license = await licenseService.issue({
        email,
        provider: 'paddle',
        providerOrderId: transactionId,
        metadata: { customer_id: payload?.data?.customer_id || null, product_ids: productIds }
      });
      await mailer.sendLicense(license.email, license.license_key);
      await recordWebhook(pool, 'paddle', eventId, eventType, payload, true, true);
      return res.json({ success: true });
    } catch (error) {
      next(error);
    }
  });
}

function expressRaw() {
  return async (req, res, next) => {
    const chunks = [];
    let size = 0;
    let rejected = false;
    req.on('data', (chunk) => {
	  if (rejected) return;
      size += chunk.length;
      if (size > 1_048_576) {
		rejected = true;
        res.status(413).end();
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => { if (!rejected) { req.body = Buffer.concat(chunks); next(); } });
    req.on('error', (error) => { if (!rejected) next(error); });
  };
}

async function alreadyProcessed(pool, provider, eventId) {
  const result = await pool.query('SELECT processed_at, emailed_at FROM webhook_events WHERE provider=$1 AND event_id=$2', [provider, eventId]);
  return result.rowCount > 0 && Boolean(result.rows[0].processed_at) && Boolean(result.rows[0].emailed_at || result.rows[0].processed_at);
}

async function recordWebhook(pool, provider, eventId, eventType, payload, processed, emailed) {
  await pool.query(
    `INSERT INTO webhook_events (provider,event_id,event_type,payload,processed_at,emailed_at)
     VALUES ($1,$2,$3,$4,$5,$6)
     ON CONFLICT (provider,event_id) DO UPDATE SET payload=EXCLUDED.payload, processed_at=EXCLUDED.processed_at, emailed_at=COALESCE(EXCLUDED.emailed_at,webhook_events.emailed_at)`,
    [provider, eventId, eventType, payload, processed ? new Date() : null, emailed ? new Date() : null]
  );
}

async function paddleCustomerEmail(transaction, config) {
  const direct = transaction?.custom_data?.customer_email || transaction?.billing_details?.email || transaction?.customer?.email;
  if (direct) return direct;
  if (!transaction?.customer_id || !config.paddle.apiKey) return null;
  const base = config.paddle.environment === 'sandbox' ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
  const response = await fetch(`${base}/customers/${encodeURIComponent(transaction.customer_id)}`, {
    headers: { Authorization: `Bearer ${config.paddle.apiKey}`, Accept: 'application/json' }
  });
  if (!response.ok) throw new Error(`Paddle customer lookup failed with HTTP ${response.status}.`);
  const body = await response.json();
  return body?.data?.email || null;
}

