-- User Profile System Database Migration
-- Add profile fields to the users table

-- Add new profile columns to users table
ALTER TABLE `users` 
ADD COLUMN `display_name` varchar(100) DEFAULT NULL AFTER `username`,
ADD COLUMN `email` varchar(100) DEFAULT NULL AFTER `display_name`,
ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `email`,
ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `phone`,
ADD COLUMN `position` varchar(100) DEFAULT NULL AFTER `department`,
ADD COLUMN `employee_id` varchar(50) DEFAULT NULL AFTER `position`,
ADD COLUMN `avatar_path` varchar(255) DEFAULT NULL AFTER `employee_id`,
ADD COLUMN `timezone` varchar(50) DEFAULT 'Asia/Jakarta' AFTER `avatar_path`,
ADD COLUMN `language` varchar(10) DEFAULT 'en' AFTER `timezone`,
ADD COLUMN `theme` enum('light','dark','auto') DEFAULT 'light' AFTER `language`,
ADD COLUMN `notifications_email` tinyint(1) DEFAULT 1 AFTER `theme`,
ADD COLUMN `notifications_sms` tinyint(1) DEFAULT 0 AFTER `notifications_email`,
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;

-- Update existing users with default display names
UPDATE `users` SET 
    `display_name` = CASE 
        WHEN `username` = 'supervisor' THEN 'System Supervisor'
        WHEN `username` = 'warehouse' THEN 'Warehouse Manager'
        WHEN `username` = 'operator1' THEN 'Machine Operator 1'
        WHEN `username` = 'operator2' THEN 'Machine Operator 2'
        ELSE CONCAT(UCASE(LEFT(`username`, 1)), LOWER(SUBSTRING(`username`, 2)))
    END,
    `email` = CONCAT(`username`, '@company.com'),
    `department` = CASE 
        WHEN `role` = 'supervisor' THEN 'Production Management'
        WHEN `role` = 'warehouse_manager' THEN 'Warehouse & Logistics'
        WHEN `role` = 'machine_operator' THEN 'Production Operations'
    END,
    `position` = CASE 
        WHEN `role` = 'supervisor' THEN 'Production Supervisor'
        WHEN `role` = 'warehouse_manager' THEN 'Warehouse Manager'
        WHEN `role` = 'machine_operator' THEN 'Machine Operator'
    END,
    `employee_id` = CONCAT('EMP', LPAD(`id`, 4, '0'));

-- Create user preferences table for additional settings
CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_preference` (`user_id`, `preference_key`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default preferences for existing users
INSERT INTO `user_preferences` (`user_id`, `preference_key`, `preference_value`) VALUES
(1, 'dashboard_widgets', 'recent_jobs,statistics,notifications'),
(1, 'default_job_priority', 'normal'),
(1, 'auto_refresh_interval', '30'),
(2, 'dashboard_widgets', 'inventory_alerts,recent_requests,statistics'),
(2, 'default_job_priority', 'normal'),
(2, 'auto_refresh_interval', '60'),
(3, 'dashboard_widgets', 'my_jobs,recent_activities'),
(3, 'default_job_priority', 'normal'),
(3, 'auto_refresh_interval', '45'),
(4, 'dashboard_widgets', 'my_jobs,recent_activities'),
(4, 'default_job_priority', 'normal'),
(4, 'auto_refresh_interval', '45');

-- Create user activity log table
CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `activity_type` (`activity_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample activity log entries
INSERT INTO `user_activity_log` (`user_id`, `activity_type`, `description`, `ip_address`) VALUES
(1, 'login', 'User logged in successfully', '192.168.1.100'),
(2, 'profile_update', 'Updated display name and email', '192.168.1.101'),
(3, 'job_created', 'Created new job: Machine Maintenance', '192.168.1.102'),
(4, 'material_request', 'Submitted material request for Job #5', '192.168.1.103');

-- Create indexes for better performance
CREATE INDEX `idx_users_display_name` ON `users` (`display_name`);
CREATE INDEX `idx_users_email` ON `users` (`email`);
CREATE INDEX `idx_users_department` ON `users` (`department`);
CREATE INDEX `idx_user_activity_log_user_created` ON `user_activity_log` (`user_id`, `created_at`); 