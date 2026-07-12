DROP TEMPORARY TABLE IF EXISTS m3_1_users_access_permission_guard;
CREATE TEMPORARY TABLE m3_1_users_access_permission_guard (
    sentinel TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO m3_1_users_access_permission_guard (sentinel) VALUES (1);

START TRANSACTION;

INSERT INTO m3_1_users_access_permission_guard (sentinel)
SELECT CASE WHEN COUNT(*) = 1 THEN 2 ELSE 1 END
FROM roles
WHERE slug = 'admin';

SET @m3_1_admin_role_id := (
    SELECT id
    FROM roles
    WHERE slug = 'admin'
    LIMIT 1
);

INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT desired.name, desired.slug, NOW(), NOW()
FROM (
    SELECT 'Read users' AS name, 'users.read' AS slug
    UNION ALL SELECT 'Create users', 'users.create'
    UNION ALL SELECT 'Update users', 'users.update'
    UNION ALL SELECT 'Manage user passwords', 'users.password.manage'
    UNION ALL SELECT 'Manage user status', 'users.status.manage'
    UNION ALL SELECT 'Read roles and permissions', 'roles.read'
    UNION ALL SELECT 'Manage roles', 'roles.manage'
    UNION ALL SELECT 'Manage user roles', 'users.roles.manage'
    UNION ALL SELECT 'Manage role permissions', 'roles.permissions.manage'
) AS desired
LEFT JOIN permissions ON permissions.slug = desired.slug
WHERE permissions.id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT @m3_1_admin_role_id, permissions.id
FROM permissions
LEFT JOIN role_permissions
    ON role_permissions.role_id = @m3_1_admin_role_id
    AND role_permissions.permission_id = permissions.id
WHERE permissions.slug IN (
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
AND role_permissions.permission_id IS NULL;

INSERT INTO m3_1_users_access_permission_guard (sentinel)
SELECT CASE WHEN (
    (
        SELECT COUNT(*)
        FROM permissions
        WHERE slug IN (
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
    ) = 9
    AND (
        SELECT COUNT(*)
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin'
            AND permissions.slug IN (
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
    ) = 9
) THEN 3 ELSE 1 END;

DROP TEMPORARY TABLE m3_1_users_access_permission_guard;

COMMIT;
