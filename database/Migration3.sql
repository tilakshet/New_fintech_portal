-- Case A: Multi-Gateway Payment Wallet
-- Adds routing/eligibility fields to payment_gateways, and the new tables
-- needed for partner-facing payment orchestration: PaymentIntent,
-- PaymentAttempt, WebhookEvent. Routing decisions and admin actions
-- continue to use the existing audit_logs table (no new table needed).

-- ---------------------------------------------------------------------
-- 1. Extend payment_gateways with routing/eligibility metadata
-- ---------------------------------------------------------------------
ALTER TABLE payment_gateways
    ADD COLUMN health_status ENUM('healthy', 'degraded', 'down') NOT NULL DEFAULT 'healthy' AFTER status,
    ADD COLUMN routing_priority INT UNSIGNED NOT NULL DEFAULT 100 AFTER health_status,
    ADD COLUMN routing_weight INT UNSIGNED NOT NULL DEFAULT 1 AFTER routing_priority,
    ADD COLUMN daily_capacity_limit DECIMAL(18,2) NULL AFTER routing_weight,
    ADD COLUMN daily_capacity_used DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER daily_capacity_limit,
    ADD COLUMN capacity_reset_on DATE NULL AFTER daily_capacity_used;

-- routing_priority: lower number = tried first (matches how humans read "priority 1, 2, 3...")
-- routing_weight: only used when routing_strategy = 'weighted' (see platform_api_settings below)
-- daily_capacity_limit: NULL = unlimited; daily_capacity_used resets to 0 whenever capacity_reset_on != CURDATE()

-- ---------------------------------------------------------------------
-- 2. Routing strategy is a platform-wide setting, so it lives on the
--    existing platform_api_settings singleton row rather than a new table.
-- ---------------------------------------------------------------------
ALTER TABLE platform_api_settings
    ADD COLUMN routing_strategy ENUM('priority', 'round_robin', 'weighted', 'capacity_aware') NOT NULL DEFAULT 'priority' AFTER payin_callback_url,
    ADD COLUMN round_robin_cursor INT UNSIGNED NOT NULL DEFAULT 0 AFTER routing_strategy;

-- ---------------------------------------------------------------------
-- 3. PaymentIntent — the business-level payment request from the partner
-- ---------------------------------------------------------------------
CREATE TABLE payment_intents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(120) NOT NULL,
    partner_order_reference VARCHAR(120) NOT NULL,
    customer_reference VARCHAR(120) NULL,
    amount DECIMAL(18,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    status ENUM('created', 'routing', 'processing', 'success', 'failed', 'unknown', 'verifying') NOT NULL DEFAULT 'created',
    selected_gateway_id INT UNSIGNED NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_partner_callback_status VARCHAR(20) NULL,
    last_partner_callback_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_intents_idempotency (idempotency_key),
    KEY idx_payment_intents_partner_ref (partner_order_reference),
    KEY idx_payment_intents_status (status, created_at),
    CONSTRAINT fk_payment_intents_gateway FOREIGN KEY (selected_gateway_id) REFERENCES payment_gateways(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. PaymentAttempt — every provider-level attempt, tracked independently
--    (FR-008). A PaymentIntent can have more than one attempt only when
--    a prior attempt was a *confirmed* failure, never on an unknown/
--    timeout outcome (FR-010, NFR-008).
-- ---------------------------------------------------------------------
CREATE TABLE payment_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_intent_id INT UNSIGNED NOT NULL,
    gateway_id INT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL,
    provider_transaction_id VARCHAR(100) NULL,
    status ENUM('created', 'processing', 'success', 'failed', 'unknown') NOT NULL DEFAULT 'created',
    failure_reason VARCHAR(255) NULL,
    routing_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_attempts_intent_number (payment_intent_id, attempt_number),
    KEY idx_payment_attempts_gateway (gateway_id, status),
    KEY idx_payment_attempts_provider_txn (provider_transaction_id),
    CONSTRAINT fk_payment_attempts_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_attempts_gateway FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. WebhookEvent — every inbound webhook (provider -> wallet), stored
--    for idempotent processing + audit/replay protection (FR-009).
-- ---------------------------------------------------------------------
CREATE TABLE webhook_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_id INT UNSIGNED NOT NULL,
    payment_attempt_id INT UNSIGNED NULL,
    provider_event_id VARCHAR(150) NOT NULL,
    payload JSON NULL,
    processing_result ENUM('pending', 'applied', 'ignored_duplicate', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_webhook_events_gateway_event (gateway_id, provider_event_id),
    KEY idx_webhook_events_attempt (payment_attempt_id),
    CONSTRAINT fk_webhook_events_gateway FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id),
    CONSTRAINT fk_webhook_events_attempt FOREIGN KEY (payment_attempt_id) REFERENCES payment_attempts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;