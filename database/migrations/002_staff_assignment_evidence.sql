-- Migration: Internal staff assignment + resolution evidence tracking
-- Run this once against your existing SCCSv2 database.
-- Safe to re-run: each statement checks whether the change already exists first.

-- 1. Track who delegated a complaint and when, plus an internal workflow
--    status separate from the public-facing complaint status.
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'complaints' AND COLUMN_NAME = 'assigned_by'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE complaints ADD COLUMN assigned_by INT NULL DEFAULT NULL AFTER assigned_to',
  'SELECT "assigned_by already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'complaints' AND COLUMN_NAME = 'assigned_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE complaints ADD COLUMN assigned_at DATETIME NULL DEFAULT NULL AFTER assigned_by',
  'SELECT "assigned_at already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'complaints' AND COLUMN_NAME = 'assignment_status'
);
SET @sql = IF(@col_exists = 0,
  "ALTER TABLE complaints ADD COLUMN assignment_status ENUM('unassigned','assigned','awaiting_confirmation','resolved') NOT NULL DEFAULT 'unassigned' AFTER assigned_at",
  'SELECT "assignment_status already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Helpful index for "complaints assigned to me" / "complaints I assigned" lookups
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'complaints' AND INDEX_NAME = 'idx_assigned_by'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE complaints ADD INDEX idx_assigned_by (assigned_by)',
  'SELECT "idx_assigned_by already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Resolution evidence (before/after photos, or general supporting files)
--    uploaded by whichever staff member is actively working the complaint.
CREATE TABLE IF NOT EXISTS `complaint_evidence` (
  `evidence_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `evidence_type` enum('before','after','general') NOT NULL DEFAULT 'general',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`evidence_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Backfill: any complaint that already has an `assigned_to` value from
--    before this migration is treated as 'assigned' rather than 'unassigned'.
UPDATE complaints SET assignment_status = 'assigned'
  WHERE assigned_to IS NOT NULL AND assignment_status = 'unassigned';
