CREATE TABLE IF NOT EXISTS merchant_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    legal_company_name VARCHAR(160) NULL,
    company_type VARCHAR(60) NULL,
    mobile_number VARCHAR(20) NULL,
    whatsapp_number VARCHAR(20) NULL,
    pan_number VARCHAR(20) NULL,
    gstin VARCHAR(20) NULL,
    aadhar_number VARCHAR(20) NULL,
    office_address VARCHAR(255) NULL,
    kyc_locked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_merchant_profiles_user (user_id),
    CONSTRAINT fk_merchant_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settlement_banks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    account_holder VARCHAR(120) NOT NULL,
    account_number VARCHAR(40) NOT NULL,
    ifsc_code VARCHAR(20) NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settlement_banks_user (user_id),
    CONSTRAINT fk_settlement_banks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kyc_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    document_type ENUM(
        'aadhar_card', 'pan_card', 'gst_certificate', 'board_resolution',
        'certificate_of_incorporation', 'passport_photo', 'service_agreement'
    ) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kyc_documents_user (user_id),
    UNIQUE KEY uq_kyc_documents_user_type (user_id, document_type),
    CONSTRAINT fk_kyc_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;