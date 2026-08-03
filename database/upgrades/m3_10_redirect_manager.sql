CREATE TABLE IF NOT EXISTS redirects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    target VARCHAR(2048) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 302,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_redirects_source_path (source_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT 'Manage redirects', 'redirects.manage', NOW(), NOW()
FROM (SELECT 1) AS desired
LEFT JOIN permissions ON permissions.slug = 'redirects.manage'
WHERE permissions.id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'redirects.manage'
LEFT JOIN role_permissions
    ON role_permissions.role_id = roles.id
    AND role_permissions.permission_id = permissions.id
WHERE roles.slug = 'admin'
    AND role_permissions.permission_id IS NULL;
