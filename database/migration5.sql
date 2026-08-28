-- Cashfree integration: sandbox/production toggle, generic to any provider
-- that splits its API across separate sandbox and live base URLs (Cashfree
-- does, Razorpay doesn't need this - same endpoint for test and live keys).
-- Apply to any database that predates this migration.

ALTER TABLE payment_gateways
    ADD COLUMN sandbox_mode TINYINT(1) NOT NULL DEFAULT 1 AFTER public_key;
