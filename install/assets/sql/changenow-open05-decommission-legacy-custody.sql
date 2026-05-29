-- OPEN-05 ChangeNOW legacy custody decommission.
--
-- Archive first: export these tables from the production database backup
-- before running this script on an existing installation. The manifest records
-- the intended archive/drop path, but it is not a replacement for a database
-- dump, encrypted cold backup, or site-specific retention approval.

CREATE TABLE IF NOT EXISTS `legacy_custody_archive_manifest_krypto` (
  `id_legacy_custody_archive_manifest` int(11) NOT NULL AUTO_INCREMENT,
  `legacy_table_name` varchar(128) NOT NULL,
  `archive_note` text NOT NULL,
  `archived_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dropped_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id_legacy_custody_archive_manifest`),
  UNIQUE KEY `legacy_custody_archive_manifest_table` (`legacy_table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `legacy_custody_archive_manifest_krypto`
  (`legacy_table_name`, `archive_note`, `archived_at`, `dropped_at`)
VALUES
  ('balance_krypto', 'Archive first, then remove legacy custodial balance storage after ChangeNOW migration.', NOW(), NOW()),
  ('binance_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('bitbank_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('bitmex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('bittrex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('btcmarket_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('cex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('coinex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('coinspot_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('ethfinex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('exchanges_withdraw_krypto', 'Archive first, then remove legacy withdraw provider configuration after ChangeNOW migration.', NOW(), NOW()),
  ('exmo_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('gateio_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('gdax_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('gemini_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('hitbtc2_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('internal_order_krypto', 'Archive first, then remove legacy internal order storage after ChangeNOW migration.', NOW(), NOW()),
  ('kraken_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('kucoin_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('leader_board_krypto', 'Archive first, then remove legacy trading leaderboard storage after ChangeNOW migration.', NOW(), NOW()),
  ('livecoin_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('luno_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('okcoinusd_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('okex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('order_krypto', 'Archive first, then remove legacy exchange order storage after ChangeNOW migration.', NOW(), NOW()),
  ('poloniex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('quoinex_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW()),
  ('thirdparty_crypto_krypto', 'Archive first, then remove legacy exchange market availability storage after ChangeNOW migration.', NOW(), NOW()),
  ('user_thirdparty_selected_krypto', 'Archive first, then remove legacy selected exchange storage after ChangeNOW migration.', NOW(), NOW()),
  ('user_widthdraw_krypto', 'Archive first, then remove legacy user withdraw destination storage after ChangeNOW migration.', NOW(), NOW()),
  ('widthdraw_history_krypto', 'Archive first, then remove legacy withdraw history storage after ChangeNOW migration.', NOW(), NOW()),
  ('yobit_krypto', 'Archive first, then remove legacy exchange credential storage after ChangeNOW migration.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `archive_note` = VALUES(`archive_note`),
  `dropped_at` = NOW();

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `balance_krypto`;
DROP TABLE IF EXISTS `binance_krypto`;
DROP TABLE IF EXISTS `bitbank_krypto`;
DROP TABLE IF EXISTS `bitmex_krypto`;
DROP TABLE IF EXISTS `bittrex_krypto`;
DROP TABLE IF EXISTS `btcmarket_krypto`;
DROP TABLE IF EXISTS `cex_krypto`;
DROP TABLE IF EXISTS `coinex_krypto`;
DROP TABLE IF EXISTS `coinspot_krypto`;
DROP TABLE IF EXISTS `ethfinex_krypto`;
DROP TABLE IF EXISTS `exchanges_withdraw_krypto`;
DROP TABLE IF EXISTS `exmo_krypto`;
DROP TABLE IF EXISTS `gateio_krypto`;
DROP TABLE IF EXISTS `gdax_krypto`;
DROP TABLE IF EXISTS `gemini_krypto`;
DROP TABLE IF EXISTS `hitbtc2_krypto`;
DROP TABLE IF EXISTS `internal_order_krypto`;
DROP TABLE IF EXISTS `kraken_krypto`;
DROP TABLE IF EXISTS `kucoin_krypto`;
DROP TABLE IF EXISTS `leader_board_krypto`;
DROP TABLE IF EXISTS `livecoin_krypto`;
DROP TABLE IF EXISTS `luno_krypto`;
DROP TABLE IF EXISTS `okcoinusd_krypto`;
DROP TABLE IF EXISTS `okex_krypto`;
DROP TABLE IF EXISTS `order_krypto`;
DROP TABLE IF EXISTS `poloniex_krypto`;
DROP TABLE IF EXISTS `quoinex_krypto`;
DROP TABLE IF EXISTS `thirdparty_crypto_krypto`;
DROP TABLE IF EXISTS `user_thirdparty_selected_krypto`;
DROP TABLE IF EXISTS `user_widthdraw_krypto`;
DROP TABLE IF EXISTS `widthdraw_history_krypto`;
DROP TABLE IF EXISTS `yobit_krypto`;

SET FOREIGN_KEY_CHECKS = 1;
