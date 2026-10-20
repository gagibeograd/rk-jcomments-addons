CREATE TABLE IF NOT EXISTS `#__jcomments_rkreactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id` INT UNSIGNED NOT NULL,
  `reaction` VARCHAR(16) NOT NULL,
  `visitor` VARCHAR(64) NOT NULL,
  `userid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `date` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comment_visitor` (`comment_id`, `visitor`),
  KEY `idx_comment` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
