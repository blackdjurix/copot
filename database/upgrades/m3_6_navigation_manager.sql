CREATE TABLE IF NOT EXISTS navigation_menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS navigation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    label VARCHAR(190) NOT NULL,
    target_kind VARCHAR(100) NOT NULL,
    target_reference VARCHAR(255) NOT NULL,
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

CREATE TABLE IF NOT EXISTS navigation_menu_assignments (
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

INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT 'Manage navigation', 'navigation.manage', NOW(), NOW()
FROM (SELECT 1) AS desired
LEFT JOIN permissions ON permissions.slug = 'navigation.manage'
WHERE permissions.id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'navigation.manage'
LEFT JOIN role_permissions
    ON role_permissions.role_id = roles.id
    AND role_permissions.permission_id = permissions.id
WHERE roles.slug = 'admin'
    AND role_permissions.permission_id IS NULL;
