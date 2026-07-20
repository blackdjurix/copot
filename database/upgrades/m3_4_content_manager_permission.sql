INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT 'Read content', 'content.read', NOW(), NOW()
FROM (SELECT 1) AS desired
LEFT JOIN permissions ON permissions.slug = 'content.read'
WHERE permissions.id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'content.read'
LEFT JOIN role_permissions
    ON role_permissions.role_id = roles.id
    AND role_permissions.permission_id = permissions.id
WHERE roles.slug = 'admin'
    AND role_permissions.permission_id IS NULL;
