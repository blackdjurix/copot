CREATE TABLE IF NOT EXISTS media (
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

CREATE TABLE IF NOT EXISTS media_variants (
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
    CONSTRAINT fk_media_variants_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_usages (
    media_id BIGINT UNSIGNED NOT NULL,
    consumer_type VARCHAR(100) NOT NULL,
    consumer_id BIGINT UNSIGNED NOT NULL,
    usage_key VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (media_id, consumer_type, consumer_id, usage_key),
    INDEX idx_media_usages_consumer (consumer_type, consumer_id, usage_key),
    CONSTRAINT fk_media_usages_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE RESTRICT
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
