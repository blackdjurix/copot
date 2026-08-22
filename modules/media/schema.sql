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
