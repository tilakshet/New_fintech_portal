-- Circuit breaker for gateway auto-pause (includes/gateway_selector.php:
-- record_gateway_outcome(), GATEWAY_AUTO_PAUSE_FAILURE_THRESHOLD). This
-- was referenced by committed code with no migration ever creating it -
-- added here to match what the code actually needs. Apply to any
-- database that predates this.

ALTER TABLE payment_gateways
    ADD COLUMN consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0 AFTER sandbox_mode,
    ADD COLUMN auto_paused_until DATETIME NULL AFTER consecutive_failures;
