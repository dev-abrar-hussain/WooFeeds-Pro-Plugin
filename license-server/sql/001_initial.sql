CREATE TABLE IF NOT EXISTS licenses (
    id UUID PRIMARY KEY,
    key_hash CHAR(64) NOT NULL UNIQUE,
    key_prefix VARCHAR(12) NOT NULL,
    key_last4 CHAR(4) NOT NULL,
    key_encrypted TEXT NOT NULL,
    email VARCHAR(320),
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled', 'refunded')),
    domain VARCHAR(253),
    site_url TEXT,
    provider VARCHAR(32),
    provider_order_id VARCHAR(191),
    activated_at TIMESTAMPTZ,
    last_validated_at TIMESTAMPTZ,
    deactivated_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider, provider_order_id)
);

CREATE INDEX IF NOT EXISTS licenses_domain_idx ON licenses (domain);
CREATE INDEX IF NOT EXISTS licenses_email_idx ON licenses (email);

CREATE TABLE IF NOT EXISTS license_events (
    id BIGSERIAL PRIMARY KEY,
    license_id UUID REFERENCES licenses(id) ON DELETE SET NULL,
    event_type VARCHAR(40) NOT NULL,
    domain VARCHAR(253),
    site_url TEXT,
    ip_address INET,
    user_agent TEXT,
    details JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGSERIAL PRIMARY KEY,
    provider VARCHAR(32) NOT NULL,
    event_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    payload JSONB NOT NULL,
    processed_at TIMESTAMPTZ,
    emailed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider, event_id)
);

