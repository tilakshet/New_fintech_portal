-- Gateway limit / rotation / usage foundation. Apply to any database that
-- was provisioned before this migration (i.e. already has migration.sql
-- and migration2.sql applied). A fresh install from schema.sql already
-- includes everything below and does not need this file.

ALTER TABLE payment_gateways
    ADD COLUMN api_key_encrypted TEXT NULL AFTER api_key_hash,
    ADD COLUMN webhook_secret_encrypted TEXT NULL AFTER api_key_encrypted,
    ADD COLUMN priority INT UNSIGNED NOT NULL DEFAULT 100 AFTER is_default,
    ADD COLUMN daily_limit_amount DECIMAL(18,2) NULL AFTER priority,
    ADD KEY idx_payment_gateways_priority (status, priority);

CREATE TABLE IF NOT EXISTS gateway_daily_usage (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_id INT UNSIGNED NOT NULL,
    usage_date DATE NOT NULL,
    used_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    transaction_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gateway_daily_usage (gateway_id, usage_date),
    CONSTRAINT fk_gateway_daily_usage_gateway FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_id INT UNSIGNED NOT NULL,
    event_id VARCHAR(120) NOT NULL,
    gateway_txn_id VARCHAR(120) NULL,
    payload JSON NOT NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_webhook_events_gateway_event (gateway_id, event_id),
    KEY idx_webhook_events_gateway_txn (gateway_txn_id),
    CONSTRAINT fk_webhook_events_gateway FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE transactions
    ADD COLUMN gateway_id INT UNSIGNED NULL AFTER destination,
    ADD COLUMN gateway_txn_id VARCHAR(120) NULL AFTER gateway_id,
    ADD COLUMN idempotency_key VARCHAR(64) NULL AFTER gateway_txn_id,
    ADD UNIQUE KEY uq_transactions_idempotency_key (idempotency_key),
    ADD UNIQUE KEY uq_transactions_gateway_txn (gateway_id, gateway_txn_id),
    ADD CONSTRAINT fk_transactions_gateway FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE SET NULL;
