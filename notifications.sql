-- Notifications System Database Setup
-- Create notifications table

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample notifications for testing
INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 'system_alert', 'Welcome to JOR System', 'Welcome to the Job Order Request system! You can now manage jobs and track progress.', NULL, 0, NOW()),
(2, 'material_request', 'New Material Request', 'A new material request has been submitted and requires your attention.', 'pages/manage_materials.php', 0, NOW()),
(3, 'job_assigned', 'New Job Assigned', 'You have been assigned a new job: Machine Maintenance #001.', 'pages/view_operator_job.php?id=1', 0, NOW()),
(4, 'job_assigned', 'New Job Assigned', 'You have been assigned a new job: Quality Check #002.', 'pages/view_operator_job.php?id=2', 0, NOW());

-- Create indexes for better performance
CREATE INDEX `idx_notifications_user_read` ON `notifications` (`user_id`, `is_read`);
CREATE INDEX `idx_notifications_created` ON `notifications` (`created_at`);
CREATE INDEX `idx_notifications_type` ON `notifications` (`type`); 