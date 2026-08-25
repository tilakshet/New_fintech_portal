CREATE TABLE platform_api_settings (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    client_key VARCHAR(40) NOT NULL,
    secret_key_hash VARCHAR(255) NOT NULL,
    secret_key_last4 VARCHAR(4) NOT NULL,
    bearer_token TEXT NULL,
    bearer_token_generated_at DATETIME NULL,
    primary_whitelist_ip VARCHAR(45) NULL,
    payout_callback_url VARCHAR(255) NULL,
    payin_callback_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_platform_api_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE platform_whitelisted_ips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_whitelisted_ips_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
