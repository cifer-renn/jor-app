-- Material Management System Merge Script
-- This script helps consolidate material applications and requests into a unified system

-- Optional: Create a unified view for easier querying
CREATE OR REPLACE VIEW unified_materials AS
SELECT 
    'application' as type,
    id,
    applicant_name as title,
    work_unit,
    problem_description as description,
    priority,
    status,
    created_at,
    submitted_by_id as user_id,
    processed_by_id as handler_id,
    processed_at as handled_at,
    NULL as job_id,
    NULL as job_title
FROM material_applications
UNION ALL
SELECT 
    'request' as type,
    id,
    request_notes as title,
    '' as work_unit,
    request_notes as description,
    'normal' as priority,
    status,
    created_at,
    requested_by_id as user_id,
    handled_by_id as handler_id,
    handled_at,
    job_id,
    (SELECT title FROM jobs WHERE id = material_requests.job_id) as job_title
FROM material_requests;

-- Optional: Create indexes for better performance
CREATE INDEX idx_unified_materials_applications ON material_applications (status, priority, created_at);
CREATE INDEX idx_unified_materials_requests ON material_requests (status, created_at);

-- Optional: Create a summary view for statistics
CREATE OR REPLACE VIEW material_statistics AS
SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN type = 'application' THEN 1 ELSE 0 END) as total_applications,
    SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_items,
    SUM(CASE WHEN status IN ('approved', 'resolved') THEN 1 ELSE 0 END) as resolved_items,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_items,
    SUM(CASE WHEN priority = 'urgent' AND status = 'pending' THEN 1 ELSE 0 END) as urgent_pending
FROM unified_materials;

-- Note: The existing tables remain unchanged to maintain data integrity
-- The new unified interface uses UNION queries to combine both data sources
-- This approach preserves all existing data while providing a unified view 