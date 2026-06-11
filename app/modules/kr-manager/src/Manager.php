<?php

class Manager extends MySQL {

  private $App = null;

  public function __construct($App){
    $this->App = $App;
  }

  public function _getApp(){
    return $this->App;
  }

  public function _getListSection(){
    $s = ['Statistics', 'Bank transferts', 'Payments', 'Subscriptions', 'Identity', 'Users', 'ChangeNOW swaps'];
    if(!$this->_getApp()->_getIdentityEnabled()){
      unset($s[4]);
    }
    return $s;
  }

  public function _getPaymentStatus($status){
    if($this->_getApp()->_getPaymentApproveNeeded()){
      if($status == "1") return 'Need to be approved';
      if($status == "2") return 'Paid';
    } else {
      if($status == "1") return 'Paid';
    }
    if($status == "0") return 'Not paid';
    return 'Unknown';
  }

  private $UserFetchedList = [];

  public function _getUserFetched($user_id){
    if(!array_key_exists($user_id, $this->UserFetchedList)) $this->UserFetchedList[$user_id] = new User($user_id);
    return $this->UserFetchedList[$user_id];
  }

  public function _fetchPayments($user = null){

    if(!is_null($user)){
      return parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE id_user=:id_user ORDER BY payment_status_deposit_history, date_deposit_history DESC",
                                    [
                                      'id_user' => $user->_getUserID()
                                    ]);
    }

    $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto ORDER BY payment_status_deposit_history, date_deposit_history DESC");

    return $r;

  }

  public function _getPaymentFilters(){
    return [
      'default' => 'Default',
      'needapp' => 'Need to be approved',
      'paid' => 'Paid',
      'npaid' => 'Not paid',
      'allpay' => 'All payments'
    ];
  }

  public function _getWidthdrawFilters(){
    return [
      'default' => 'Default',
      'pending' => 'In pending',
      'not_confirmed' => 'Not confirmed',
      'done' => 'Done',
      'canceled' => 'Canceled',
      'all' => 'All widthdraw'
    ];
  }

  public function _getPaymentInfos($id_deposit){
    $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE id_deposit_history=:id_deposit_history",
                                [
                                  'id_deposit_history' => $id_deposit
                                ]);
    if(count($r) == 0) throw new Exception("Error : Deposit unknown", 1);
    return $r[0];

  }

  public function _askPaymentProof($id_deposit, $msg){

    $infosPayment = $this->_getPaymentInfos($id_deposit);

    $r = parent::execSqlRequest("INSERT INTO deposit_history_proof_krypto (id_deposit_history, id_user, date_deposit_history_proof, url_deposit_history_proof, reason__deposit_history_proof)
                                VALUES (:id_deposit_history, :id_user, :date_deposit_history_proof, :url_deposit_history_proof, :reason__deposit_history_proof)",
                                [
                                  'id_deposit_history' => $id_deposit,
                                  'id_user' => $infosPayment['id_user'],
                                  'date_deposit_history_proof' => time(),
                                  'url_deposit_history_proof' => '',
                                  'reason__deposit_history_proof' => $msg
                                ]);

    if(!$r) throw new Exception("Error : Fail to ask a proof", 1);

    $infosBankproof = parent::querySqlRequest("SELECT * FROM deposit_history_proof_krypto WHERE id_deposit_history=:id_deposit_history ORDER BY id_deposit_history_proof DESC", ['id_deposit_history' => $id_deposit]);
    if(count($infosBankproof) == 0) throw new Exception("Error : Fail to get informations", 1);
    $infosBankproof = $infosBankproof[0];

    $NotificationCenter = new NotificationCenter(new User($infosPayment['id_user']));

    $ProofLink = APP_URL.'/app/modules/kr-payment/views/proofSending.php?s='.App::encrypt_decrypt('encrypt', 'proof-'.$infosBankproof['id_deposit_history_proof']);

    $NotificationCenter->_sendNotification('Proof required for payment #'.$infosPayment['ref_deposit_history'],
                                           'Click here, for send the payment proof', '',
                                           true,
                                           "window.open('".$ProofLink."', '_blank')");

  }

  public function _getPaymentProofInfos($id_proof_asking){
    $r = parent::querySqlRequest("SELECT * FROM deposit_history_proof_krypto WHERE id_deposit_history_proof=:id_deposit_history_proof",
                                  [
                                    'id_deposit_history_proof' => $id_proof_asking
                                  ]);
    if(count($r) == 0) throw new Exception("Error : Proof unknown", 1);
    return $r[0];
  }

  public function _sendProof($id_proof_asking, $User, $file){

    $infosProof = explode('-', $id_proof_asking);
    if(count($infosProof) != 2 || $infosProof[0] != "proof") throw new Exception("Permission denied", 1);

    $id_proof_asking = $infosProof[1];

    $infosProofPayment = $this->_getPaymentProofInfos($id_proof_asking);
    if($infosProofPayment['id_user'] != $User->_getUserID()) throw new Exception("Permission denied", 1);

    $proofDirectory = App::encrypt_decrypt('encrypt', $id_proof_asking);
    if(!file_exists($_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/public/proof')) mkdir($_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/public/proof', 0777);
    if(!file_exists($_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/public/proof/'.$proofDirectory)) mkdir($_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/public/proof/'.$proofDirectory, 0777);

    App::_assertUploadedFileIsSafe($file, ['pdf', 'jpg', 'jpeg', 'png'], 'Payment proof');
    $fileName = App::_getSafeUploadedFileName($file, uniqid());

    move_uploaded_file($file['tmp_name'], $_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/public/proof/'.$proofDirectory.'/'.$fileName);

    $r = parent::execSqlRequest("UPDATE deposit_history_proof_krypto SET url_deposit_history_proof=:url_deposit_history_proof, sended_deposit_history_proof=:sended_deposit_history_proof WHERE id_deposit_history_proof=:id_deposit_history_proof",
                                [
                                  'url_deposit_history_proof' => '/public/proof/'.$proofDirectory.'/'.$fileName,
                                  'sended_deposit_history_proof' => time(),
                                  'id_deposit_history_proof' => $id_proof_asking
                                ]);

    if(!$r) throw new Exception("Error : Fail to update proof", 1);

    return true;

  }

  public function _getProofPaymentAssociated($id_payment){

    $r = parent::querySqlRequest("SELECT * FROM deposit_history_proof_krypto WHERE id_deposit_history=:id_deposit_history",
                                [
                                  'id_deposit_history' => $id_payment
                                ]);

    return $r;

  }

  public function _submitActionPayment($action, $idpayment, $args = null){
    $infosPayment = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE id_deposit_history=:id_deposit_history",
                                [
                                  'id_deposit_history' => $idpayment
                                ]);

    if(count($infosPayment) == 0) throw new Exception("Error : Payment not found", 1);
    $infosPayment = $infosPayment[0];

    if($action == "askproof"){

      $this->_askPaymentProof($idpayment, $args);

    } elseif($action == "accept_payment"){

      $r = parent::execSqlRequest("UPDATE deposit_history_krypto SET payment_status_deposit_history=:payment_status_deposit_history WHERE id_deposit_history=:id_deposit_history",
                                  [
                                    'id_deposit_history' => $idpayment,
                                    'payment_status_deposit_history' => 2
                                  ]);

      if(!$r) throw new Exception("Error : Fail to change payment status (SQL Error)", 1);

      $NotificationCenter = new NotificationCenter(new User($infosPayment['id_user']));

      $NotificationCenter->_sendNotification('Payment approved #'.$infosPayment['ref_deposit_history'], 'Your payment #'.$infosPayment['ref_deposit_history'].' has been approved.', '');


    } elseif($action == "cancel_payment"){

      $r = parent::execSqlRequest("UPDATE deposit_history_krypto SET payment_status_deposit_history=:payment_status_deposit_history WHERE id_deposit_history=:id_deposit_history",
                                  [
                                    'id_deposit_history' => $idpayment,
                                    'payment_status_deposit_history' => 0
                                  ]);

      if(!$r) throw new Exception("Error : Fail to change payment status (SQL Error)", 1);

      $NotificationCenter = new NotificationCenter(new User($infosPayment['id_user']));

      $NotificationCenter->_sendNotification('Payment canceled #'.$infosPayment['ref_deposit_history'], 'Your payment #'.$infosPayment['ref_deposit_history'].' has been canceled. Reason : '.$args, '');

    } else {
      throw new Exception("Error : Permission denied", 1);
    }


  }

  public function _getUsersList($query = null){
    $listUser = [];

    if(!is_null($query)){
      foreach (parent::querySqlRequest("SELECT * FROM user_krypto WHERE
                                        email_user LIKE :query_search OR
                                        name_user LIKE :query_search OR
                                        id_user LIKE :query_search
                                        ORDER BY id_user DESC", ['query_search' => '%'.$query.'%']) as $key => $dataUser) {
        $listUser[] = new User($dataUser['id_user']);
      }
      return $listUser;
    }

    foreach (parent::querySqlRequest("SELECT * FROM user_krypto ORDER BY id_user DESC", []) as $key => $dataUser) {
      $listUser[] = new User($dataUser['id_user']);
    }
    return $listUser;
  }

  public function _getUserByManager($idu){
    $infosUser = explode('-', App::encrypt_decrypt('decrypt', $idu));
    if(count($infosUser) != 2) throw new Exception("Permission denied", 1);
    return new User($infosUser[1]);
  }

  public function _getInternalOrderList($user = null, $Query = null, $StartDate = null, $EndDate = null){
    return [];
  }

  public function _modifiyUserBalance($userid, $value, $type, $symbol){
    throw new Exception("Legacy custody balances are retired", 1);
  }

  public function _getNumberManagerNotification($type = 'all'){

    $nNotification = 0;
    if($this->_getApp()->_getPaymentApproveNeeded() && ($type == "all" || $type == "payments")){
      $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE payment_status_deposit_history=:payment_status_deposit_history
                                    AND payment_type_deposit_history != 'Initial'", ['payment_status_deposit_history' => 1]);
      $nNotification += count($r);
    }

    if($type == "all" || $type == "identity"){
      $r = parent::querySqlRequest("SELECT * FROM identity_krypto WHERE status_identity=:status_identity", ['status_identity' => 0]);
      $nNotification += count($r);
    }

    if($type == "all" || $type == "banktransferts"){
      $r = parent::querySqlRequest("SELECT * FROM banktransfert_krypto WHERE status_banktransfert=:status_banktransfert OR status_banktransfert=:status_banktransfert_se ",
                                    ['status_banktransfert' => 0, 'status_banktransfert_se' => 1]);
      $nNotification += count($r);
    }

    if($type == "all" || $type == "changenowswaps"){
      try {
        $r = parent::querySqlRequest("SELECT * FROM changenow_transactions_krypto
                                      WHERE refund_available_changenow_transaction=:refund_available
                                      OR continue_available_changenow_transaction=:continue_available",
                                      [
                                        'refund_available' => 1,
                                        'continue_available' => 1
                                      ]);
        $nNotification += count($r);
      } catch (Exception $e) { }
    }

    return $nNotification;


  }

  public function _getSubscriptions(){
    return parent::querySqlRequest("SELECT * FROM charges_krypto ORDER BY date_charges DESC");
  }


}

?>
