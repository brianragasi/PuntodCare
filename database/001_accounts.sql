CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'family', 'caretaker') NOT NULL,
    status ENUM('active', 'pending', 'verified', 'rejected', 'suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email),
    KEY users_role_status_index (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caretaker_applications (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    service_area VARCHAR(160) NOT NULL,
    experience TEXT DEFAULT NULL,
    review_note TEXT DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    CONSTRAINT caretaker_application_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT caretaker_application_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subject_user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    note VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY account_events_subject_index (subject_user_id, created_at),
    CONSTRAINT account_events_subject_fk FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT account_events_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY login_attempts_email_time_index (email_hash, attempted_at),
    KEY login_attempts_ip_time_index (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
