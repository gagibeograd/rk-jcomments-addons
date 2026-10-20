CREATE TABLE IF NOT EXISTS `#__jcomments_rkavatar` (
  `comment_id` INT UNSIGNED NOT NULL,
  `avatar` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
