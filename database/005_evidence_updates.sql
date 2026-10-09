CREATE TABLE IF NOT EXISTS request_evidence (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    round_no INT UNSIGNED NOT NULL DEFAULT 1,
    stage ENUM('before', 'after') NOT NULL,
    storage_name CHAR(48) NOT NULL,
    mime_type VARCHAR(30) NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    caption VARCHAR(200) NOT NULL DEFAULT '',
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY request_evidence_storage_unique (storage_name),
    KEY request_evidence_round_index (request_id, round_no, stage, id),
    CONSTRAINT request_evidence_request_fk FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE CASCADE,
    CONSTRAINT request_evidence_uploader_fk FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(240) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    KEY request_update_inbox_index (recipient_user_id, read_at, created_at),
    CONSTRAINT request_update_recipient_fk FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT request_update_request_fk FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
