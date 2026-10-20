CREATE TABLE IF NOT EXISTS `#__jcomments_rkvoice` (
  `comment_id` INT UNSIGNED NOT NULL,
  `file` VARCHAR(64) NOT NULL,
  `mime` VARCHAR(32) NOT NULL DEFAULT '',
  `duration` DECIMAL(6,1) NOT NULL DEFAULT 0,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `transcript` TEXT NULL,
  `date` DATETIME NOT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
