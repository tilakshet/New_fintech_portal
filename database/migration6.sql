CREATE TABLE client_api_credentials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,          -- the business customer this key belongs to
    client_key VARCHAR(40) NOT NULL,        -- public id, shown in the dashboard (e.g. ck_live_xxxx)
    token_hash CHAR(64) NOT NULL,           -- SHA-256 of the actual bearer token — never store it plaintext
    token_last4 VARCHAR(4) NOT NULL,        -- for display ("...a91f") without re-exposing the token
    status ENUM('active','revoked') NOT NULL DEFAULT 'active',
    payin_callback_url VARCHAR(255) NULL,
    payout_callback_url VARCHAR(255) NULL,
    generated_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_client_api_credentials_user (user_id),
    UNIQUE KEY uq_client_api_credentials_key (client_key),
    UNIQUE KEY uq_client_api_credentials_token_hash (token_hash),
    CONSTRAINT fk_client_api_credentials_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE client_whitelisted_ips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credential_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    UNIQUE KEY uq_client_whitelisted_ips (credential_id, ip_address),
    CONSTRAINT fk_client_whitelisted_ips_credential FOREIGN KEY (credential_id) REFERENCES client_api_credentials(id) ON DELETE CASCADE
);