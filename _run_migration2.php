<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS `livemap_assigned_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `route_date` date NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_date` (`staff_id`, `route_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `livemap_assigned_route_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius_meters` int(11) DEFAULT 50,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_route` (`route_id`),
  CONSTRAINT `fk_route_points_route` FOREIGN KEY (`route_id`) REFERENCES `livemap_assigned_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `livemap_geo_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lat_round` decimal(10,4) NOT NULL,
  `lng_round` decimal(11,4) NOT NULL,
  `address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_lat_lng` (`lat_round`, `lng_round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES 
('livemap.assign', 'Assign Routes to Staff', 'livemap');

SET @livemap_parent_id = (SELECT `id` FROM `menu_items` WHERE `slug` = 'livemap-parent' LIMIT 1);

INSERT IGNORE INTO `menu_items` (`name`, `slug`, `icon`, `url`, `parent_id`, `sort_order`, `is_active`) VALUES
('Assign Route', 'route-assign', 'fas fa-tasks', '/modules/hr/route-assign.php', @livemap_parent_id, 24, 1);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`, `menu_item_id`)
SELECT 1, p.id, m.id 
FROM `permissions` p
CROSS JOIN `menu_items` m 
WHERE p.name = 'livemap.assign' AND m.slug = 'route-assign';
";

try {
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $conn->exec($sql);
    echo "Assigned Routes migration successful.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
