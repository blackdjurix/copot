INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT 'Manage Security', 'security.manage', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE slug = 'security.manage'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'security.manage'
LEFT JOIN role_permissions
    ON role_permissions.role_id = roles.id
    AND role_permissions.permission_id = permissions.id
WHERE roles.slug = 'admin'
    AND role_permissions.permission_id IS NULL;
