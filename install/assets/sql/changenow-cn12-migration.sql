-- CN-12 ChangeNOW schema and installer defaults migration.
-- This migration is additive and intentionally leaves legacy Krypto trading,
-- payment, balance, order, withdrawal, deposit, and referral tables untouched.

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
  `extra_id_changenow_asset` tinyint(1) NOT NULL DEFAULT '0',
  `extra_id_name_changenow_asset` varchar(64) DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `changenow_transactions_krypto` (
  `id_changenow_transaction` int(11) NOT NULL AUTO_INCREMENT,
  `provider_id_changenow_transaction` varchar(120) NOT NULL,
  `lookup_token_hash_changenow_transaction` char(64) NOT NULL,
  `session_key_changenow_transaction` char(64) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `flow_changenow_transaction` varchar(20) NOT NULL DEFAULT 'standard',
  `from_currency_changenow_transaction` varchar(32) NOT NULL,
  `from_network_changenow_transaction` varchar(32) NOT NULL,
  `to_currency_changenow_transaction` varchar(32) NOT NULL,
  `to_network_changenow_transaction` varchar(32) NOT NULL,
  `from_amount_changenow_transaction` varchar(40) NOT NULL,
  `to_amount_changenow_transaction` varchar(40) DEFAULT NULL,
  `payin_address_changenow_transaction` text,
  `payin_extra_id_changenow_transaction` varchar(255) DEFAULT NULL,
  `payout_address_changenow_transaction` text,
  `payout_extra_id_changenow_transaction` varchar(255) DEFAULT NULL,
  `payout_address_fingerprint_changenow_transaction` char(64) DEFAULT NULL,
  `refund_address_changenow_transaction` text,
  `refund_extra_id_changenow_transaction` varchar(255) DEFAULT NULL,
  `status_changenow_transaction` varchar(40) NOT NULL DEFAULT 'waiting',
  `refund_available_changenow_transaction` tinyint(1) NOT NULL DEFAULT '0',
  `continue_available_changenow_transaction` tinyint(1) NOT NULL DEFAULT '0',
  `id_changenow_referral_attribution` int(11) DEFAULT NULL,
  `referral_attribution_changenow_transaction` longtext,
  `raw_create_changenow_transaction` longtext,
  `raw_status_changenow_transaction` longtext,
  `raw_actions_changenow_transaction` longtext,
  `support_note_changenow_transaction` text,
  `provider_created_at_changenow_transaction` varchar(15) NOT NULL DEFAULT '0',
  `created_at_changenow_transaction` varchar(15) NOT NULL,
  `updated_at_changenow_transaction` varchar(15) NOT NULL,
  `expires_at_changenow_transaction` varchar(15) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_changenow_transaction`),
  UNIQUE KEY `provider_id_changenow_transaction` (`provider_id_changenow_transaction`),
  UNIQUE KEY `lookup_token_hash_changenow_transaction` (`lookup_token_hash_changenow_transaction`),
  KEY `session_key_changenow_transaction` (`session_key_changenow_transaction`),
  KEY `user_changenow_transaction` (`id_user`),
  KEY `status_changenow_transaction` (`status_changenow_transaction`),
  KEY `created_at_changenow_transaction` (`created_at_changenow_transaction`),
  KEY `referral_changenow_transaction` (`id_changenow_referral_attribution`),
  KEY `action_changenow_transaction` (`refund_available_changenow_transaction`, `continue_available_changenow_transaction`),
  KEY `pair_changenow_transaction` (`from_currency_changenow_transaction`, `from_network_changenow_transaction`, `to_currency_changenow_transaction`, `to_network_changenow_transaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `changenow_transaction_events_krypto` (
  `id_changenow_transaction_event` int(11) NOT NULL AUTO_INCREMENT,
  `id_changenow_transaction` int(11) DEFAULT NULL,
  `provider_id_changenow_transaction` varchar(120) NOT NULL,
  `actor_user_id_changenow_transaction_event` int(11) DEFAULT NULL,
  `actor_type_changenow_transaction_event` varchar(30) NOT NULL DEFAULT 'system',
  `event_type_changenow_transaction_event` varchar(40) NOT NULL,
  `event_status_changenow_transaction_event` varchar(40) NOT NULL,
  `previous_status_changenow_transaction_event` varchar(40) DEFAULT NULL,
  `event_note_changenow_transaction_event` text,
  `raw_event_changenow_transaction_event` longtext,
  `created_at_changenow_transaction_event` varchar(15) NOT NULL,
  PRIMARY KEY (`id_changenow_transaction_event`),
  KEY `transaction_changenow_transaction_event` (`id_changenow_transaction`),
  KEY `provider_changenow_transaction_event` (`provider_id_changenow_transaction`),
  KEY `actor_changenow_transaction_event` (`actor_user_id_changenow_transaction_event`, `actor_type_changenow_transaction_event`),
  KEY `type_changenow_transaction_event` (`event_type_changenow_transaction_event`, `event_status_changenow_transaction_event`),
  KEY `created_at_changenow_transaction_event` (`created_at_changenow_transaction_event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `changenow_referral_attribution_krypto` (
  `id_changenow_referral_attribution` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `referral_code_changenow_referral` varchar(128) NOT NULL DEFAULT '',
  `provider_user_id_changenow_referral` varchar(120) DEFAULT NULL,
  `provider_payload_changenow_referral` varchar(255) DEFAULT NULL,
  `campaign_changenow_referral` varchar(120) DEFAULT NULL,
  `source_changenow_referral` varchar(120) DEFAULT NULL,
  `landing_url_changenow_referral` text,
  `anonymous_lookup_token_hash_changenow_referral` char(64) DEFAULT NULL,
  `commission_state_changenow_referral` varchar(40) NOT NULL DEFAULT 'pending_provider_confirmation',
  `raw_changenow_referral` longtext,
  `created_at_changenow_referral` varchar(15) NOT NULL,
  `updated_at_changenow_referral` varchar(15) NOT NULL,
  PRIMARY KEY (`id_changenow_referral_attribution`),
  KEY `referral_code_changenow_referral` (`referral_code_changenow_referral`),
  KEY `user_changenow_referral` (`id_user`),
  KEY `provider_user_changenow_referral` (`provider_user_id_changenow_referral`),
  KEY `lookup_token_changenow_referral` (`anonymous_lookup_token_hash_changenow_referral`),
  KEY `commission_state_changenow_referral` (`commission_state_changenow_referral`),
  KEY `created_at_changenow_referral` (`created_at_changenow_referral`)
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

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_provider_enabled', '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_provider_enabled'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'legacy_exchange_connections_enabled', '1', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'legacy_exchange_connections_enabled'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_public_api_key', '', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_public_api_key'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_private_api_key', '', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_private_api_key'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_callback_secret', '', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_callback_secret'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_referral_link_id', '', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_referral_link_id'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_widget_link_id', '', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_widget_link_id'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_enabled_flows', 'standard,fixed-rate', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_enabled_flows'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_default_flow', 'standard', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_default_flow'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_default_from_asset', 'btc', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_default_from_asset'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_default_from_network', 'btc', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_default_from_network'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_default_to_asset', 'eth', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_default_to_asset'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_default_to_network', 'eth', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_default_to_network'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_support_email', '', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_support_email'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_rate_limit_per_second', '30', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_rate_limit_per_second'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_rate_limit_per_minute', '1800', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_rate_limit_per_minute'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_quote_cache_ttl', '30', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_quote_cache_ttl'
);

INSERT INTO `settings_krypto` (`key_settings`, `value_settings`, `encrypted_settings`)
SELECT 'changenow_debug_logging_enabled', '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `settings_krypto` WHERE `key_settings` = 'changenow_debug_logging_enabled'
);
