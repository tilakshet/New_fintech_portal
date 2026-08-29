-- Per-customer API credentials (one row per user) replacing the
-- single platform-wide credential set in platform_api_settings, plus an
-- admin-managed per-customer IP whitelist. See schema.sql for the design
-- rationale on each table. Apply to any database that predates this.

CREATE TABLE IF NOT EXISTS customer_api_credentials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    client_key VARCHAR(40) NOT NULL,
    secret_key_hash VARCHAR(255) NOT NULL,
    secret_key_last4 VARCHAR(4) NOT NULL,
    bearer_token TEXT NULL,
    bearer_token_generated_at DATETIME NULL,
    payout_callback_url VARCHAR(255) NULL,
    payin_callback_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_api_credentials_user (user_id),
    UNIQUE KEY uq_customer_api_credentials_client_key (client_key),
    CONSTRAINT fk_customer_api_credentials_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_whitelisted_ips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    added_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_whitelisted_ips (user_id, ip_address),
    CONSTRAINT fk_customer_whitelisted_ips_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_whitelisted_ips_admin FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
