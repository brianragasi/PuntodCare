CREATE TABLE IF NOT EXISTS cemeteries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    city VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY cemetery_location_unique (name, city, province),
    CONSTRAINT cemetery_creator_fk FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT cemetery_updater_fk FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cemetery_id BIGINT UNSIGNED NOT NULL,
    section_code VARCHAR(80) NOT NULL,
    block_code VARCHAR(80) NOT NULL DEFAULT '',
    row_code VARCHAR(80) NOT NULL DEFAULT '',
    lot_code VARCHAR(80) NOT NULL,
    landmark VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY plot_reference_unique (cemetery_id, section_code, block_code, row_code, lot_code),
    KEY plots_cemetery_status_index (cemetery_id, status),
    CONSTRAINT plot_cemetery_fk FOREIGN KEY (cemetery_id) REFERENCES cemeteries(id),
    CONSTRAINT plot_creator_fk FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT plot_updater_fk FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_offerings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cemetery_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    price_centavos INT UNSIGNED NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY service_cemetery_name_unique (cemetery_id, name),
    KEY services_cemetery_status_index (cemetery_id, status),
    CONSTRAINT service_cemetery_fk FOREIGN KEY (cemetery_id) REFERENCES cemeteries(id),
    CONSTRAINT service_creator_fk FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT service_updater_fk FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_price_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,
    price_centavos INT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY price_history_service_index (service_id, changed_at),
    CONSTRAINT price_history_service_fk FOREIGN KEY (service_id) REFERENCES service_offerings(id),
    CONSTRAINT price_history_actor_fk FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caretaker_cemeteries (
    caretaker_id BIGINT UNSIGNED NOT NULL,
    cemetery_id BIGINT UNSIGNED NOT NULL,
    authorized_by BIGINT UNSIGNED NOT NULL,
    authorized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (caretaker_id, cemetery_id),
    CONSTRAINT caretaker_access_user_fk FOREIGN KEY (caretaker_id) REFERENCES users(id),
    CONSTRAINT caretaker_access_cemetery_fk FOREIGN KEY (cemetery_id) REFERENCES cemeteries(id),
    CONSTRAINT caretaker_access_actor_fk FOREIGN KEY (authorized_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY admin_events_time_index (created_at),
    CONSTRAINT admin_events_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
