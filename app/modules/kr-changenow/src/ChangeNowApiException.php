<?php

/**
 * Predictable exception types for ChangeNOW API failures.
 *
 * @package Krypto
 */
class ChangeNowApiException extends Exception {

  private $Type = 'api_error';
  private $UserMessage = 'ChangeNOW request failed.';
  private $AdminMessage = '';
  private $HttpStatus = null;
  private $DebugContext = [];

  public function __construct($type, $userMessage, $adminMessage = '', $httpStatus = null, $debugContext = [], $previous = null){
    $this->Type = $type;
    $this->UserMessage = $userMessage;
    $this->AdminMessage = ($adminMessage == '' ? $userMessage : $adminMessage);
    $this->HttpStatus = $httpStatus;
    $this->DebugContext = $debugContext;

    parent::__construct($this->AdminMessage, 1, $previous);
  }

  public function _getType(){
    return $this->Type;
  }

  public function _getUserMessage(){
    return $this->UserMessage;
  }

  public function _getAdminMessage(){
    return $this->AdminMessage;
  }

  public function _getHttpStatus(){
    return $this->HttpStatus;
  }

  public function _getDebugContext(){
    return $this->DebugContext;
  }

}

class ChangeNowApiConfigurationException extends ChangeNowApiException {

  public function __construct($adminMessage, $debugContext = [], $previous = null){
    parent::__construct('configuration', 'ChangeNOW is not configured for this operation.', $adminMessage, null, $debugContext, $previous);
  }

}

class ChangeNowApiNetworkException extends ChangeNowApiException {

  public function __construct($adminMessage, $debugContext = [], $previous = null){
    parent::__construct('network', 'ChangeNOW is temporarily unavailable. Please retry later.', $adminMessage, null, $debugContext, $previous);
  }

}

class ChangeNowApiValidationException extends ChangeNowApiException {

  public function __construct($userMessage, $adminMessage, $httpStatus = 400, $debugContext = [], $previous = null){
    parent::__construct('validation', $userMessage, $adminMessage, $httpStatus, $debugContext, $previous);
  }

}

class ChangeNowApiAuthException extends ChangeNowApiException {

  public function __construct($adminMessage, $httpStatus = 401, $debugContext = [], $previous = null){
    parent::__construct('auth', 'ChangeNOW authentication failed. Please check provider credentials.', $adminMessage, $httpStatus, $debugContext, $previous);
  }

}

class ChangeNowApiRateLimitException extends ChangeNowApiException {

  public function __construct($adminMessage, $httpStatus = 429, $debugContext = [], $previous = null){
    parent::__construct('rate_limit', 'ChangeNOW rate limit exceeded. Please retry later.', $adminMessage, $httpStatus, $debugContext, $previous);
  }

}

class ChangeNowApiNotFoundException extends ChangeNowApiException {

  public function __construct($adminMessage, $httpStatus = 404, $debugContext = [], $previous = null){
    parent::__construct('not_found', 'The ChangeNOW transaction was not found.', $adminMessage, $httpStatus, $debugContext, $previous);
  }

}

class ChangeNowApiServerException extends ChangeNowApiException {

  public function __construct($adminMessage, $httpStatus = 500, $debugContext = [], $previous = null){
    parent::__construct('server', 'ChangeNOW is temporarily unavailable. Please retry later.', $adminMessage, $httpStatus, $debugContext, $previous);
  }

}

class ChangeNowApiMalformedResponseException extends ChangeNowApiException {

  public function __construct($adminMessage, $httpStatus = null, $debugContext = [], $previous = null){
    parent::__construct('malformed_response', 'ChangeNOW returned an unreadable response. Please retry later.', $adminMessage, $httpStatus, $debugContext, $previous);
  }

}

?>
