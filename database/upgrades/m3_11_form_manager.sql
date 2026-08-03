CREATE TABLE IF NOT EXISTS forms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_forms_status_updated (status, updated_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    label VARCHAR(150) NOT NULL,
    field_type VARCHAR(30) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    min_length INT UNSIGNED NULL,
    max_length INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_form_fields_form_key (form_id, field_key),
    UNIQUE KEY uq_form_fields_form_order (form_id, sort_order),
    INDEX idx_form_fields_form_order (form_id, sort_order, id),
    CONSTRAINT fk_form_fields_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_field_options (
    form_field_id BIGINT UNSIGNED NOT NULL,
    option_value VARCHAR(100) NOT NULL,
    option_label VARCHAR(150) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (form_field_id, option_value),
    UNIQUE KEY uq_form_field_options_order (form_field_id, sort_order),
    INDEX idx_form_field_options_order (form_field_id, sort_order, option_value),
    CONSTRAINT fk_form_field_options_field FOREIGN KEY (form_field_id) REFERENCES form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_form_submissions_form_status_updated (form_id, status, updated_at, id),
    CONSTRAINT fk_form_submissions_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_submission_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    form_field_id BIGINT UNSIGNED NULL,
    field_key VARCHAR(100) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type VARCHAR(30) NOT NULL,
    value_text TEXT NOT NULL,
    value_label VARCHAR(150) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_form_submission_values_submission_key (submission_id, field_key),
    INDEX idx_form_submission_values_submission (submission_id, id),
    INDEX idx_form_submission_values_field (form_field_id),
    CONSTRAINT fk_form_submission_values_submission FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_submission_values_field FOREIGN KEY (form_field_id) REFERENCES form_fields(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT desired.name, desired.slug, NOW(), NOW()
FROM (
    SELECT 'View forms' AS name, 'forms.view' AS slug
    UNION ALL SELECT 'Manage forms', 'forms.manage'
    UNION ALL SELECT 'View form submissions', 'forms.submissions.view'
    UNION ALL SELECT 'Delete form submissions', 'forms.submissions.delete'
) AS desired
LEFT JOIN permissions ON permissions.slug = desired.slug
WHERE permissions.id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug IN ('forms.view', 'forms.manage', 'forms.submissions.view', 'forms.submissions.delete')
LEFT JOIN role_permissions ON role_permissions.role_id = roles.id AND role_permissions.permission_id = permissions.id
WHERE roles.slug = 'admin' AND role_permissions.permission_id IS NULL;
