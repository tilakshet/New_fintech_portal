-- Automatic gateway health tracking (circuit breaker). A gateway that
-- fails several times in a row is temporarily excluded from selection
-- without any admin action — it self-heals after the cooldown, but every
-- trip and recovery is audited so it stays explainable, not silent magic.

ALTER TABLE payment_gateways
    ADD COLUMN consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0 AFTER daily_limit_amount,
    ADD COLUMN auto_paused_until DATETIME NULL AFTER consecutive_failures;