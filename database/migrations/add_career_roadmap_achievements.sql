-- Migration: Add achievements to career roadmaps
-- Safe to run multiple times (no-op if column exists)

USE placement_portal_v2;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'career_roadmaps'
      AND COLUMN_NAME = 'achievements'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE career_roadmaps ADD COLUMN achievements JSON NULL AFTER current_skills',
    'SELECT \"career_roadmaps.achievements already exists\" AS Status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

