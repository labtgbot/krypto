<?php

/**
 * ChangeNOW product modes used by the migration boundary.
 *
 * @package Krypto
 */
class ChangeNowProviderMode {

  const PUBLIC_SWAP = 'public_swap';
  const OPTIONAL_ACCOUNT_HISTORY = 'optional_account_history';
  const ADMIN_OPERATIONS = 'admin_operations';
  const LEGACY_DISABLED = 'legacy_disabled';

  public static function _list(){
    return [
      self::PUBLIC_SWAP,
      self::OPTIONAL_ACCOUNT_HISTORY,
      self::ADMIN_OPERATIONS,
      self::LEGACY_DISABLED
    ];
  }

  public static function _isValid($mode){
    return in_array($mode, self::_list(), true);
  }

}

?>
