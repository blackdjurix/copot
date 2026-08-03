CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, created_at, updated_at) VALUES
    ('Administrator', 'admin', NOW(), NOW()),
    ('User', 'user', NOW(), NOW());

INSERT INTO permissions (name, slug, created_at, updated_at) VALUES
    ('Access protected area', 'protected.access', NOW(), NOW()),
    ('Access admin shell', 'admin.access', NOW(), NOW()),
    ('Read content', 'content.read', NOW(), NOW()),
    ('Create content', 'content.create', NOW(), NOW()),
    ('Update content', 'content.update', NOW(), NOW()),
    ('Archive content', 'content.delete', NOW(), NOW()),
    ('Publish content', 'content.publish', NOW(), NOW()),
    ('Create taxonomy terms', 'taxonomy.create', NOW(), NOW()),
    ('Update taxonomy terms', 'taxonomy.update', NOW(), NOW()),
    ('Delete unused taxonomy terms', 'taxonomy.delete', NOW(), NOW()),
    ('Update site settings', 'settings.update', NOW(), NOW()),
    ('Read users', 'users.read', NOW(), NOW()),
    ('Create users', 'users.create', NOW(), NOW()),
    ('Update users', 'users.update', NOW(), NOW()),
    ('Manage user passwords', 'users.password.manage', NOW(), NOW()),
    ('Manage user status', 'users.status.manage', NOW(), NOW()),
    ('Read roles and permissions', 'roles.read', NOW(), NOW()),
    ('Manage roles', 'roles.manage', NOW(), NOW()),
    ('Manage user roles', 'users.roles.manage', NOW(), NOW()),
    ('Manage role permissions', 'roles.permissions.manage', NOW(), NOW()),
    ('Manage modules', 'modules.manage', NOW(), NOW()),
    ('Manage navigation', 'navigation.manage', NOW(), NOW()),
    ('Manage themes', 'themes.manage', NOW(), NOW()),
    ('Manage redirects', 'redirects.manage', NOW(), NOW());

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'protected.access'
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'admin.access'
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug IN (
    'content.read',
    'content.create',
    'content.update',
    'content.delete',
    'content.publish'
)
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug IN (
    'taxonomy.create',
    'taxonomy.update',
    'taxonomy.delete'
)
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'settings.update'
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug IN (
    'users.read',
    'users.create',
    'users.update',
    'users.password.manage',
    'users.status.manage',
    'roles.read',
    'roles.manage',
    'users.roles.manage',
    'roles.permissions.manage'
)
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'modules.manage'
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'navigation.manage'
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'themes.manage'
WHERE roles.slug = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'redirects.manage'
WHERE roles.slug = 'admin';

CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value MEDIUMTEXT NOT NULL,
    value_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_settings_namespace_key (namespace, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL,
    path VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'disabled',
    installed_at DATETIME NOT NULL,
    enabled_at DATETIME NULL,
    disabled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE module_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL,
    permission_slug VARCHAR(150) NOT NULL,
    permission_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_module_permission (module_name, permission_slug),
    CONSTRAINT fk_module_permissions_module
        FOREIGN KEY (module_name) REFERENCES modules(name)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_id VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL,
    type VARCHAR(30) NOT NULL,
    path VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    metadata TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_themes_theme_id (theme_id),
    INDEX idx_themes_type_active (type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE navigation_menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE navigation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    label VARCHAR(190) NOT NULL,
    target_kind VARCHAR(100) NOT NULL,
    target_reference VARCHAR(255) NULL,
    custom_url VARCHAR(2048) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_navigation_items_menu_id (menu_id, id),
    INDEX idx_navigation_items_menu_parent_order (menu_id, parent_id, sort_order),
    CONSTRAINT fk_navigation_items_menu
        FOREIGN KEY (menu_id) REFERENCES navigation_menus(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_navigation_items_parent
        FOREIGN KEY (menu_id, parent_id) REFERENCES navigation_items(menu_id, id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE navigation_menu_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_id VARCHAR(100) NOT NULL,
    location_key VARCHAR(100) NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_navigation_assignment_theme_location (theme_id, location_key),
    INDEX idx_navigation_assignment_menu (menu_id),
    CONSTRAINT fk_navigation_assignment_theme
        FOREIGN KEY (theme_id) REFERENCES themes(theme_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_navigation_assignment_menu
        FOREIGN KEY (menu_id) REFERENCES navigation_menus(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    excerpt TEXT NULL,
    body MEDIUMTEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    archived_at DATETIME NULL,
    featured_media_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_slug (slug),
    INDEX idx_content_status_published (status, published_at),
    INDEX idx_content_type_status (type, status),
    INDEX idx_content_featured_media (featured_media_id),
    CONSTRAINT fk_content_author
        FOREIGN KEY (author_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE taxonomy_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_hierarchical TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO taxonomy_types (slug, name, description, is_hierarchical, created_at, updated_at) VALUES
    ('category', 'Category', 'Default hierarchical content classification type.', 1, NOW(), NOW()),
    ('tag', 'Tag', 'Default flat content classification type.', 0, NOW(), NOW());

CREATE TABLE taxonomy_terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    taxonomy_type_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_taxonomy_terms_type_slug (taxonomy_type_id, slug),
    INDEX idx_taxonomy_terms_type_parent (taxonomy_type_id, parent_id),
    CONSTRAINT fk_taxonomy_terms_type
        FOREIGN KEY (taxonomy_type_id) REFERENCES taxonomy_types(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_taxonomy_terms_parent
        FOREIGN KEY (parent_id) REFERENCES taxonomy_terms(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE taxonomy_assignments (
    taxonomy_term_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (taxonomy_term_id, entity_type, entity_id),
    INDEX idx_taxonomy_assignments_entity (entity_type, entity_id),
    CONSTRAINT fk_taxonomy_assignments_term
        FOREIGN KEY (taxonomy_term_id) REFERENCES taxonomy_terms(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(30) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    title VARCHAR(190) NOT NULL,
    storage_key VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(190) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_media_kind_updated (kind, updated_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id BIGINT UNSIGNED NOT NULL,
    variant_key VARCHAR(100) NOT NULL,
    storage_key VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(190) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_media_variants_media_key (media_id, variant_key),
    INDEX idx_media_variants_media_updated (media_id, updated_at, id),
    CONSTRAINT fk_media_variants_media
        FOREIGN KEY (media_id) REFERENCES media(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_usages (
    media_id BIGINT UNSIGNED NOT NULL,
    consumer_type VARCHAR(100) NOT NULL,
    consumer_id BIGINT UNSIGNED NOT NULL,
    usage_key VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (media_id, consumer_type, consumer_id, usage_key),
    INDEX idx_media_usages_consumer (consumer_type, consumer_id, usage_key),
    CONSTRAINT fk_media_usages_media
        FOREIGN KEY (media_id) REFERENCES media(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    target VARCHAR(2048) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 302,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_redirects_source_path (source_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT desired.name, desired.slug, NOW(), NOW()
FROM (
    SELECT 'View media' AS name, 'media.view' AS slug
    UNION ALL SELECT 'Upload media', 'media.upload'
    UNION ALL SELECT 'Use media', 'media.use'
    UNION ALL SELECT 'Edit media', 'media.edit'
    UNION ALL SELECT 'Delete media', 'media.delete'
) AS desired
LEFT JOIN permissions ON permissions.slug = desired.slug
WHERE permissions.id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug IN ('media.view', 'media.upload', 'media.use', 'media.edit', 'media.delete')
LEFT JOIN role_permissions ON role_permissions.role_id = roles.id AND role_permissions.permission_id = permissions.id
WHERE roles.slug = 'admin' AND role_permissions.permission_id IS NULL;

CREATE TABLE forms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_forms_status_updated (status, updated_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_fields (
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

CREATE TABLE form_field_options (
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

CREATE TABLE form_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_form_submissions_form_status_updated (form_id, status, updated_at, id),
    CONSTRAINT fk_form_submissions_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_submission_values (
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
