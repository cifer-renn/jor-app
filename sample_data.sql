-- Sample data for Job Order Request System
-- Run this after creating the database structure

USE job_order_db;

-- Insert sample inventory items
INSERT INTO inventory (name, quantity) VALUES
('Steel Plates', 150),
('Aluminum Sheets', 75),
('Copper Wire', 200),
('Plastic Pipes', 120),
('Rubber Gaskets', 300),
('Electronic Components', 500),
('Welding Rods', 80),
('Paint Cans', 45),
('Safety Equipment', 25),
('Machine Parts', 60);

-- Insert sample jobs
INSERT INTO jobs (title, description, priority, status, supervisor_id, operator_id) VALUES
('Machine Maintenance - Line A', 'Perform routine maintenance on production line A. Check belts, lubricate moving parts, and inspect safety systems.', 'important', 'in_progress', 1, 3),
('Quality Control Check', 'Conduct quality control inspection on batch #2024-001. Check dimensions, weight, and surface finish.', 'normal', 'pending', 1, 4),
('Equipment Calibration', 'Calibrate measuring instruments and sensors. Update calibration certificates.', 'normal', 'completed', 1, 3),
('Safety Training Session', 'Conduct monthly safety training for new operators. Cover emergency procedures and PPE usage.', 'low', 'pending', 1, NULL),
('Production Line Setup', 'Set up production line for new product model. Install new molds and configure settings.', 'important', 'in_progress', 1, 4),
('Inventory Audit', 'Perform physical inventory count and reconcile with system records.', 'normal', 'pending', 1, NULL),
('Equipment Repair', 'Repair hydraulic pump on machine #3. Replace seals and test pressure.', 'important', 'pending', 1, 3),
('Cleanup Operation', 'Clean production area and organize tools. Dispose of waste materials properly.', 'low', 'completed', 1, 4);

-- Insert sample inventory movements
INSERT INTO inventory_movements (inventory_id, job_id, quantity_change, movement_type, moved_by_id) VALUES
(1, 1, 10, 'out', 2),  -- Steel plates used for maintenance
(3, 1, 5, 'out', 2),   -- Copper wire used for repairs
(7, 1, 15, 'out', 2),  -- Welding rods used
(2, 5, 20, 'out', 2),  -- Aluminum sheets for new setup
(4, 5, 30, 'out', 2),  -- Plastic pipes for setup
(1, NULL, 50, 'in', 2), -- New steel plates received
(3, NULL, 100, 'in', 2), -- New copper wire received
(6, 2, 25, 'out', 2),  -- Electronic components for QC
(8, 3, 10, 'out', 2),  -- Paint for calibration marks
(9, 4, 5, 'out', 2);   -- Safety equipment for training 