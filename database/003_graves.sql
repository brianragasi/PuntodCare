CREATE TABLE IF NOT EXISTS grave_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    family_user_id BIGINT UNSIGNED NOT NULL,
    cemetery_id BIGINT UNSIGNED NOT NULL,
    deceased_name VARCHAR(120) NOT NULL,
    headstone_name VARCHAR(120) NOT NULL,
    birth_date DATE DEFAULT NULL,
    death_date DATE DEFAULT NULL,
    section_code VARCHAR(80) NOT NULL,
    block_code VARCHAR(80) NOT NULL DEFAULT '',
    row_code VARCHAR(80) NOT NULL DEFAULT '',
    lot_code VARCHAR(80) NOT NULL,
    location_note VARCHAR(500) NOT NULL DEFAULT '',
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY grave_family_updated_index (family_user_id, updated_at),
    KEY grave_cemetery_location_index (cemetery_id, section_code, lot_code),
    CONSTRAINT grave_family_fk FOREIGN KEY (family_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT grave_cemetery_fk FOREIGN KEY (cemetery_id) REFERENCES cemeteries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grave_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grave_id BIGINT UNSIGNED NOT NULL,
    storage_name CHAR(48) NOT NULL,
    mime_type VARCHAR(30) NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    caption VARCHAR(200) NOT NULL DEFAULT '',
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY grave_photo_storage_unique (storage_name),
    KEY grave_photo_order_index (grave_id, id),
    CONSTRAINT grave_photo_grave_fk FOREIGN KEY (grave_id) REFERENCES grave_profiles(id) ON DELETE CASCADE,
    CONSTRAINT grave_photo_user_fk FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grave_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grave_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    summary VARCHAR(200) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY grave_event_order_index (grave_id, occurred_at, id),
    CONSTRAINT grave_event_grave_fk FOREIGN KEY (grave_id) REFERENCES grave_profiles(id) ON DELETE CASCADE,
    CONSTRAINT grave_event_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
