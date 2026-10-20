CREATE TABLE IF NOT EXISTS `#__jcomments_rkpick` (
  `comment_id` INT UNSIGNED NOT NULL,
  `picked_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `date` DATETIME NOT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
