-- Razorpay live integration: a public-safe identifier (Razorpay's key_id)
-- alongside the existing encrypted secret (key_secret, in api_key_encrypted).
-- Apply to any database that predates this migration.

ALTER TABLE payment_gateways
    ADD COLUMN public_key VARCHAR(190) NULL AFTER webhook_secret_encrypted;
