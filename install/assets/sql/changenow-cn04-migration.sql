CREATE TABLE IF NOT EXISTS `changenow_assets_krypto` (
  `id_changenow_asset` int(11) NOT NULL AUTO_INCREMENT,
  `ticker_changenow_asset` varchar(32) NOT NULL,
  `network_changenow_asset` varchar(32) NOT NULL,
  `legacy_ticker_changenow_asset` varchar(32) DEFAULT NULL,
  `name_changenow_asset` varchar(120) NOT NULL,
  `image_changenow_asset` text,
  `token_contract_changenow_asset` varchar(255) DEFAULT NULL,
  `buy_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `sell_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `fiat_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `stable_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `featured_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `fixed_rate_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `provider_active_changenow_asset` tinyint(1) NOT NULL DEFAULT '1',
  `admin_enabled_changenow_asset` tinyint(1) NOT NULL DEFAULT '1',
  `raw_changenow_asset` longtext,
  `synced_at_changenow_asset` varchar(15) NOT NULL DEFAULT '0',
  `updated_at_changenow_asset` varchar(15) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_changenow_asset`),
  UNIQUE KEY `ticker_network_changenow_asset` (`ticker_changenow_asset`, `network_changenow_asset`),
  KEY `active_changenow_asset` (`provider_active_changenow_asset`, `admin_enabled_changenow_asset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `changenow_pairs_krypto` (
  `id_changenow_pair` int(11) NOT NULL AUTO_INCREMENT,
  `from_currency_changenow_pair` varchar(32) NOT NULL,
  `from_network_changenow_pair` varchar(32) NOT NULL,
  `to_currency_changenow_pair` varchar(32) NOT NULL,
  `to_network_changenow_pair` varchar(32) NOT NULL,
  `flow_changenow_pair` varchar(20) NOT NULL DEFAULT 'standard',
  `provider_active_changenow_pair` tinyint(1) NOT NULL DEFAULT '1',
  `admin_enabled_changenow_pair` tinyint(1) NOT NULL DEFAULT '1',
  `min_amount_changenow_pair` varchar(40) NOT NULL DEFAULT '',
  `max_amount_changenow_pair` varchar(40) NOT NULL DEFAULT '',
  `last_limits_update_changenow_pair` varchar(15) NOT NULL DEFAULT '0',
  `raw_changenow_pair` longtext,
  `synced_at_changenow_pair` varchar(15) NOT NULL DEFAULT '0',
  `updated_at_changenow_pair` varchar(15) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_changenow_pair`),
  UNIQUE KEY `pair_flow_changenow_pair` (`from_currency_changenow_pair`, `from_network_changenow_pair`, `to_currency_changenow_pair`, `to_network_changenow_pair`, `flow_changenow_pair`),
  KEY `from_asset_changenow_pair` (`from_currency_changenow_pair`, `from_network_changenow_pair`, `flow_changenow_pair`),
  KEY `to_asset_changenow_pair` (`to_currency_changenow_pair`, `to_network_changenow_pair`, `flow_changenow_pair`),
  KEY `active_changenow_pair` (`provider_active_changenow_pair`, `admin_enabled_changenow_pair`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `changenow_quote_cache_krypto` (
  `id_changenow_quote_cache` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key_changenow_quote_cache` char(64) NOT NULL,
  `from_currency_changenow_quote_cache` varchar(32) NOT NULL,
  `from_network_changenow_quote_cache` varchar(32) NOT NULL,
  `to_currency_changenow_quote_cache` varchar(32) NOT NULL,
  `to_network_changenow_quote_cache` varchar(32) NOT NULL,
  `flow_changenow_quote_cache` varchar(20) NOT NULL DEFAULT 'standard',
  `amount_changenow_quote_cache` varchar(40) NOT NULL DEFAULT '',
  `request_changenow_quote_cache` longtext NOT NULL,
  `response_changenow_quote_cache` longtext NOT NULL,
  `expires_at_changenow_quote_cache` varchar(15) NOT NULL,
  `created_at_changenow_quote_cache` varchar(15) NOT NULL,
  PRIMARY KEY (`id_changenow_quote_cache`),
  UNIQUE KEY `cache_key_changenow_quote_cache` (`cache_key_changenow_quote_cache`),
  KEY `expires_at_changenow_quote_cache` (`expires_at_changenow_quote_cache`),
  KEY `pair_changenow_quote_cache` (`from_currency_changenow_quote_cache`, `from_network_changenow_quote_cache`, `to_currency_changenow_quote_cache`, `to_network_changenow_quote_cache`, `flow_changenow_quote_cache`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `changenow_sync_status_krypto` (
  `id_changenow_sync` int(11) NOT NULL AUTO_INCREMENT,
  `sync_key_changenow_sync` varchar(64) NOT NULL,
  `status_changenow_sync` varchar(20) NOT NULL,
  `message_changenow_sync` text,
  `assets_count_changenow_sync` int(11) NOT NULL DEFAULT '0',
  `pairs_count_changenow_sync` int(11) NOT NULL DEFAULT '0',
  `started_at_changenow_sync` varchar(15) NOT NULL DEFAULT '0',
  `finished_at_changenow_sync` varchar(15) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_changenow_sync`),
  UNIQUE KEY `sync_key_changenow_sync` (`sync_key_changenow_sync`),
  KEY `status_changenow_sync` (`status_changenow_sync`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_quote_cache_ttl', '30', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_quote_cache_ttl'
);
