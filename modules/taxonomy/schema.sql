CREATE TABLE IF NOT EXISTS taxonomy_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_hierarchical TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO taxonomy_types (slug, name, description, is_hierarchical, created_at, updated_at) VALUES
    ('category', 'Category', 'Default hierarchical content classification type.', 1, NOW(), NOW()),
    ('tag', 'Tag', 'Default flat content classification type.', 0, NOW(), NOW());

CREATE TABLE IF NOT EXISTS taxonomy_terms (
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
    CONSTRAINT fk_taxonomy_terms_type FOREIGN KEY (taxonomy_type_id) REFERENCES taxonomy_types(id) ON DELETE CASCADE,
    CONSTRAINT fk_taxonomy_terms_parent FOREIGN KEY (parent_id) REFERENCES taxonomy_terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxonomy_assignments (
    taxonomy_term_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (taxonomy_term_id, entity_type, entity_id),
    INDEX idx_taxonomy_assignments_entity (entity_type, entity_id),
    CONSTRAINT fk_taxonomy_assignments_term FOREIGN KEY (taxonomy_term_id) REFERENCES taxonomy_terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
