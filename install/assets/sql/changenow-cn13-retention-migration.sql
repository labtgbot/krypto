INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_retention_anonymous_days', '30', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_retention_anonymous_days'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_retention_completed_days', '365', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_retention_completed_days'
);
