-- SCHOOP security upgrade. Run AFTER 00_schoop_base.sql (the Mar 14, 2025 dump). Safe to re-run.
-- Needs MariaDB 10.4+ / MySQL 8 style syntax as shipped with XAMPP.

START TRANSACTION;

-- 1. Temporary-password workflow columns (students and staff)
ALTER TABLE `information`
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `temp_password_expires_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;

ALTER TABLE `teachers`
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `temp_password_expires_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;

-- Empty-string passwords (the old add-students.php wrote '') are treated as "no password": normalise to NULL.
UPDATE `information` SET `Password` = NULL WHERE `Password` = '';
UPDATE `teachers`    SET `Password` = NULL WHERE `Password` = '';

-- 2. Login throttling
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ident_time` (`identifier`, `attempted_at`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Audit trail (grade edits, account creation, password resets, logins)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` ENUM('student','teacher','admin','system') NOT NULL,
  `actor_id` VARCHAR(20) DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `target` VARCHAR(100) DEFAULT NULL,
  `detail` TEXT DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_id`, `created_at`),
  KEY `idx_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Fix: adding a student always failed. The after_student_insert trigger tried to create grade rows for ALL four quarters
--    of every subject, and the calculate_grades trigger rejects quarters that don't match a subject's semester
--    ("Invalid quarter for this subject semester"), rolling back the whole student insert. It also joined every teacher of a
--    subject, not just the student's section. The app creates a grade row the first time a teacher enters a score (and every
--    page uses LEFT JOINs), so pre-created blank rows are not needed. The grade-calculation triggers are kept unchanged.
DROP TRIGGER IF EXISTS `after_student_insert`;

COMMIT;
