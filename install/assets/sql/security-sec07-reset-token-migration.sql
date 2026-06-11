-- SEC-07 password reset token timestamp migration.
-- Existing reset tokens have no creation timestamp and must not remain valid.

ALTER TABLE `user_krypto`
  ADD COLUMN IF NOT EXISTS `reset_token_created_user` varchar(15) DEFAULT NULL AFTER `reset_token_user`;

UPDATE `user_krypto`
SET `reset_token_user` = NULL,
    `reset_token_created_user` = NULL
WHERE `reset_token_user` IS NOT NULL
  AND `reset_token_user` <> '';
