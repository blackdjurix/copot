DROP PROCEDURE IF EXISTS m3_3_module_manager_permission_provision;

DELIMITER $$

CREATE PROCEDURE m3_3_module_manager_permission_provision()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF (SELECT COUNT(*) FROM roles WHERE slug = 'admin') <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'M3.3 permission provisioning requires exactly one admin role.';
    END IF;

    SET @m3_3_admin_role_id := (
        SELECT id
        FROM roles
        WHERE slug = 'admin'
        LIMIT 1
    );

    INSERT INTO permissions (name, slug, created_at, updated_at)
    SELECT 'Manage modules', 'modules.manage', NOW(), NOW()
    FROM (SELECT 1) AS desired
    LEFT JOIN permissions ON permissions.slug = 'modules.manage'
    WHERE permissions.id IS NULL;

    INSERT INTO role_permissions (role_id, permission_id)
    SELECT @m3_3_admin_role_id, permissions.id
    FROM permissions
    LEFT JOIN role_permissions
        ON role_permissions.role_id = @m3_3_admin_role_id
        AND role_permissions.permission_id = permissions.id
    WHERE permissions.slug = 'modules.manage'
    AND role_permissions.permission_id IS NULL;

    IF (
        (SELECT COUNT(*) FROM permissions WHERE slug = 'modules.manage') <> 1
        OR (
            SELECT COUNT(*)
            FROM role_permissions
            INNER JOIN roles ON roles.id = role_permissions.role_id
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE roles.slug = 'admin'
                AND permissions.slug = 'modules.manage'
        ) <> 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'M3.3 permission provisioning postcondition failed.';
    END IF;

    COMMIT;
END$$

DELIMITER ;

CALL m3_3_module_manager_permission_provision();
DROP PROCEDURE IF EXISTS m3_3_module_manager_permission_provision;
