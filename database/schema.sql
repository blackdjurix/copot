CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

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
);

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
);

INSERT INTO roles (name, slug, created_at, updated_at) VALUES
    ('Administrator', 'admin', NOW(), NOW()),
    ('User', 'user', NOW(), NOW());

INSERT INTO permissions (name, slug, created_at, updated_at) VALUES
    ('Access protected area', 'protected.access', NOW(), NOW()),
    ('Access admin shell', 'admin.access', NOW(), NOW()),
    ('Create content', 'content.create', NOW(), NOW()),
    ('Update content', 'content.update', NOW(), NOW()),
    ('Archive content', 'content.delete', NOW(), NOW()),
    ('Publish content', 'content.publish', NOW(), NOW()),
    ('Create taxonomy terms', 'taxonomy.create', NOW(), NOW()),
    ('Update taxonomy terms', 'taxonomy.update', NOW(), NOW()),
    ('Delete unused taxonomy terms', 'taxonomy.delete', NOW(), NOW()),
    ('Update site settings', 'settings.update', NOW(), NOW());

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

CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value MEDIUMTEXT NOT NULL,
    value_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_settings_namespace_key (namespace, setting_key)
);

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
);

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
);

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
);

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
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_slug (slug),
    INDEX idx_content_status_published (status, published_at),
    INDEX idx_content_type_status (type, status),
    CONSTRAINT fk_content_author
        FOREIGN KEY (author_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE taxonomy_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_hierarchical TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

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
);

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
);
