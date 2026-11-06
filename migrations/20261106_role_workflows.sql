-- Apply once to the existing login_system database.
ALTER TABLE users
    ADD COLUMN staff_category ENUM(
        'discipline_committee',
        'guidance_counselor',
        'school_head'
    ) NULL DEFAULT NULL AFTER role;

UPDATE users
SET staff_category = CASE
    WHEN role = 'admin' THEN 'school_head'
    WHEN role = 'staff' THEN 'discipline_committee'
    ELSE NULL
END
WHERE staff_category IS NULL;

ALTER TABLE conduct_cases
    ADD COLUMN consequence TEXT NULL AFTER description,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE guidance_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    counselor_id INT UNSIGNED NOT NULL,
    notes TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY guidance_notes_case_counselor_unique (case_id, counselor_id),
    CONSTRAINT guidance_notes_case_fk
        FOREIGN KEY (case_id) REFERENCES conduct_cases (id) ON DELETE CASCADE,
    CONSTRAINT guidance_notes_counselor_fk
        FOREIGN KEY (counselor_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suspension_signatures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    signed_by INT UNSIGNED NOT NULL,
    signed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY suspension_signatures_case_unique (case_id),
    CONSTRAINT suspension_signatures_case_fk
        FOREIGN KEY (case_id) REFERENCES conduct_cases (id) ON DELETE CASCADE,
    CONSTRAINT suspension_signatures_principal_fk
        FOREIGN KEY (signed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY audit_logs_created_at_idx (created_at),
    KEY audit_logs_actor_created_idx (actor_id, created_at),
    CONSTRAINT audit_logs_actor_fk
        FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
