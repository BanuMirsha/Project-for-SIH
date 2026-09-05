-- ============================================================
-- BIS Standards AI Recommendation Engine — Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS `app_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `app_db`;

-- ------------------------------------------------------------
-- Users (single table, role column drives permissions)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `fullname`   VARCHAR(150) NOT NULL,
    `username`   VARCHAR(100) NOT NULL UNIQUE,
    `email`      VARCHAR(255) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','government','private') NOT NULL DEFAULT 'private',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Standards (loaded from data/standards_verified.csv)
-- This is the knowledge base the AI service embeds and searches.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `standards` (
    `id`                     INT AUTO_INCREMENT PRIMARY KEY,
    `is_code`                VARCHAR(50)  NOT NULL UNIQUE,
    `title`                  VARCHAR(255) NOT NULL,
    `scope_text`             TEXT,
    `sector`                 VARCHAR(100),
    `status`                 ENUM('Active','Withdrawn','Reaffirmed','Draft') NOT NULL DEFAULT 'Active',
    `current_edition_detail` VARCHAR(255),
    `superseded_by_or_notes` TEXT,
    `verification_source`    VARCHAR(255),
    `created_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CSV import (run from the MySQL client on the server that
-- can see the file, adjust the path, and keep local_infile on):
--
--   LOAD DATA LOCAL INFILE 'data/standards_verified.csv'
--   INTO TABLE standards
--   FIELDS TERMINATED BY ',' ENCLOSED BY '"'
--   LINES TERMINATED BY '\n'
--   IGNORE 1 ROWS
--   (is_code, title, scope_text, sector, status, current_edition_detail,
--    superseded_by_or_notes, verification_source);
--
-- Or use php/import_standards.php (included) which reads the CSV
-- with PHP's fgetcsv() and inserts via a prepared statement —
-- more portable and doesn't require LOCAL INFILE to be enabled.
-- ------------------------------------------------------------

-- ------------------------------------------------------------
-- Search logs — feeds the "what are people actually asking for"
-- feedback loop mentioned in the pipeline; also handy for your demo.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `search_logs` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT NULL,
    `query_text`    VARCHAR(500) NOT NULL,
    `top_result`    VARCHAR(50)  NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed one admin account.
-- Generate your own hash first — never ship a real password in a
-- committed file. Run this on the command line:
--
--   php -r "echo password_hash('choose-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
--
-- then paste the output ($2y$10$....) below in place of the placeholder.
-- ------------------------------------------------------------
INSERT INTO `users` (`fullname`, `username`, `email`, `password`, `role`)
VALUES ('System Admin', 'admin', 'admin@example.com', '$2y$10$REPLACE_WITH_YOUR_OWN_HASH', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;
