ALTER TABLE `changenow_transactions_krypto`
  ADD COLUMN `payout_address_fingerprint_changenow_transaction` char(64) DEFAULT NULL AFTER `payout_extra_id_changenow_transaction`,
  ADD COLUMN `refund_available_changenow_transaction` tinyint(1) NOT NULL DEFAULT '0' AFTER `status_changenow_transaction`,
  ADD COLUMN `continue_available_changenow_transaction` tinyint(1) NOT NULL DEFAULT '0' AFTER `refund_available_changenow_transaction`,
  ADD COLUMN `referral_attribution_changenow_transaction` longtext AFTER `continue_available_changenow_transaction`,
  ADD COLUMN `raw_actions_changenow_transaction` longtext AFTER `raw_status_changenow_transaction`,
  ADD COLUMN `support_note_changenow_transaction` text AFTER `raw_actions_changenow_transaction`,
  ADD KEY `action_changenow_transaction` (`refund_available_changenow_transaction`, `continue_available_changenow_transaction`);

CREATE TABLE IF NOT EXISTS `changenow_transaction_events_krypto` (
  `id_changenow_transaction_event` int(11) NOT NULL AUTO_INCREMENT,
  `id_changenow_transaction` int(11) DEFAULT NULL,
  `provider_id_changenow_transaction` varchar(120) NOT NULL,
  `actor_user_id_changenow_transaction_event` int(11) DEFAULT NULL,
  `actor_type_changenow_transaction_event` varchar(30) NOT NULL DEFAULT 'system',
  `event_type_changenow_transaction_event` varchar(40) NOT NULL,
  `event_status_changenow_transaction_event` varchar(40) NOT NULL,
  `event_note_changenow_transaction_event` text,
  `raw_event_changenow_transaction_event` longtext,
  `created_at_changenow_transaction_event` varchar(15) NOT NULL,
  PRIMARY KEY (`id_changenow_transaction_event`),
  KEY `transaction_changenow_transaction_event` (`id_changenow_transaction`),
  KEY `provider_changenow_transaction_event` (`provider_id_changenow_transaction`),
  KEY `actor_changenow_transaction_event` (`actor_user_id_changenow_transaction_event`, `actor_type_changenow_transaction_event`),
  KEY `type_changenow_transaction_event` (`event_type_changenow_transaction_event`, `event_status_changenow_transaction_event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
