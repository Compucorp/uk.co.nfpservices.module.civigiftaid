-- /*******************************************************
-- * civicrm_civigiftaid_batchsettings
-- *******************************************************/

CREATE TABLE IF NOT EXISTS `civicrm_civigiftaid_batchsettings` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key.',
  `batch_id`                INT UNSIGNED COMMENT 'Foreign key for the batch in `civicrm_batch`.',
  `financial_types_enabled` TEXT COMMENT 'Only enabled for selected financial types for the batch.',
  `globally_enabled`        TINYINT COMMENT 'Enabled for all financial types for the batch.',
  `basic_rate_tax`          DECIMAL(4,2) NOT NULL COMMENT 'Basic rate tax for the batch.',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicrm_civigiftaid_batchsettings_batch_id FOREIGN KEY (`batch_id`) REFERENCES `civicrm_batch` (`id`) ON DELETE CASCADE
)
  ENGINE = InnoDB;
