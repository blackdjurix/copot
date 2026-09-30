DROP TEMPORARY TABLE IF EXISTS wu3_s4_email_permission_guard;
CREATE TEMPORARY TABLE wu3_s4_email_permission_guard (
    sentinel TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO wu3_s4_email_permission_guard (sentinel) VALUES (1);

START TRANSACTION;

INSERT INTO wu3_s4_email_permission_guard (sentinel)
SELECT CASE WHEN COUNT(*) = 1 THEN 2 ELSE 1 END
FROM roles
WHERE slug = 'admin';

SET @wu3_s4_admin_role_id := (
    SELECT id FROM roles WHERE slug = 'admin' LIMIT 1
);

INSERT INTO permissions (name, slug, created_at, updated_at)
SELECT 'Manage Email', 'email.manage', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE slug = 'email.manage'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT @wu3_s4_admin_role_id, permissions.id
FROM permissions
LEFT JOIN role_permissions
    ON role_permissions.role_id = @wu3_s4_admin_role_id
    AND role_permissions.permission_id = permissions.id
WHERE permissions.slug = 'email.manage'
  AND role_permissions.permission_id IS NULL;

INSERT INTO wu3_s4_email_permission_guard (sentinel)
SELECT CASE WHEN (
    (SELECT COUNT(*) FROM permissions WHERE slug = 'email.manage') = 1
    AND (
        SELECT COUNT(*)
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin' AND permissions.slug = 'email.manage'
    ) = 1
) THEN 3 ELSE 1 END;

DROP TEMPORARY TABLE wu3_s4_email_permission_guard;

COMMIT;
