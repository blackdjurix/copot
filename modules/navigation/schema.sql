CREATE TABLE IF NOT EXISTS navigation_menu_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_id VARCHAR(100) NOT NULL,
    location_key VARCHAR(100) NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_navigation_assignment_theme_location (theme_id, location_key),
    INDEX idx_navigation_assignment_menu (menu_id),
    CONSTRAINT fk_navigation_assignment_theme FOREIGN KEY (theme_id) REFERENCES themes(theme_id) ON DELETE CASCADE,
    CONSTRAINT fk_navigation_assignment_menu FOREIGN KEY (menu_id) REFERENCES navigation_menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
