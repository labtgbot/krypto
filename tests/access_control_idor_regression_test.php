<?php

/**
 * Regression coverage for issue #91 (SEC-04): IDOR/Broken Access Control.
 *
 * The affected legacy flows rely on opaque ids in the UI, but the server-side
 * model/action layer must enforce the authorization boundary before destructive
 * or data-reading operations run.
 */

$root = dirname(__DIR__);

function assert_idor($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function read_idor_source($root, $relativePath) {
    $source = @file_get_contents($root.'/'.$relativePath);
    assert_idor($source !== false && trim($source) !== '', 'Cannot read '.$relativePath);
    return $source;
}

function assert_idor_contains($source, $needle, $message) {
    assert_idor(strpos($source, $needle) !== false, $message.' Missing: '.$needle);
}

function assert_idor_not_contains($source, $needle, $message) {
    assert_idor(strpos($source, $needle) === false, $message.' Forbidden: '.$needle);
}

function assert_idor_before($source, $firstNeedle, $secondNeedle, $message) {
    $firstPosition = strpos($source, $firstNeedle);
    $secondPosition = strpos($source, $secondNeedle);

    assert_idor(
        $firstPosition !== false && $secondPosition !== false && $firstPosition < $secondPosition,
        $message.' Expected '.$firstNeedle.' before '.$secondNeedle
    );
}

$deleteUser = read_idor_source($root, 'app/modules/kr-admin/src/actions/deleteUser.php');
assert_idor_contains(
    $deleteUser,
    '$UserFetched->_isAdmin()',
    'deleteUser.php must inspect privileged target users before deletion.'
);
assert_idor_contains(
    $deleteUser,
    '$UserFetched->_isManager()',
    'deleteUser.php must inspect manager target users before deletion.'
);
assert_idor_before(
    $deleteUser,
    '$UserFetched->_isManager()',
    '$UserFetched->_delete();',
    'deleteUser.php must reject unauthorized privileged-target deletion before _delete().'
);

$bankTransfert = read_idor_source($root, 'app/modules/kr-payment/src/Banktransfert.php');
assert_idor_contains(
    $bankTransfert,
    'function _requireOwnedBankTransfert',
    'Banktransfert.php must centralize bank-transfer ownership checks.'
);
assert_idor_before(
    $bankTransfert,
    '$this->_requireOwnedBankTransfert($id_banktransfert);',
    'move_uploaded_file(',
    'Bank transfer proof upload must verify ownership before moving a file.'
);
assert_idor_before(
    $bankTransfert,
    '$this->_requireOwnedBankTransfert($id_banktransfert);',
    '$this->_updateBankTransfertStatus($id_banktransfert, 1);',
    'Bank transfer proof upload must verify ownership before changing status.'
);

$bankTransfertView = read_idor_source($root, 'app/modules/kr-payment/views/banktransfert.php');
assert_idor_contains(
    $bankTransfertView,
    '_getOwnedInfosBankTransfert($BankTransfertID[1])',
    'banktransfert.php must load user-facing transfer details through an ownership-gated method.'
);

$manager = read_idor_source($root, 'app/modules/kr-manager/src/Manager.php');
assert_idor_not_contains(
    $manager,
    '//if($infosProofPayment',
    'Manager::_sendProof() must not leave the payment-proof ownership check commented out.'
);
assert_idor_contains(
    $manager,
    '$infosProofPayment[\'id_user\'] != $User->_getUserID()',
    'Manager::_sendProof() must compare proof owner with the current user.'
);
assert_idor_before(
    $manager,
    '$infosProofPayment[\'id_user\'] != $User->_getUserID()',
    'move_uploaded_file(',
    'Payment proof upload must verify ownership before moving a file.'
);

$chatRoom = read_idor_source($root, 'app/modules/kr-chat/src/ChatRoom.php');
assert_idor_contains(
    $chatRoom,
    'function _userCanAccess($User)',
    'ChatRoom.php must expose a room-membership check.'
);
assert_idor_contains(
    $chatRoom,
    'id_room_chat=:id_room_chat AND id_user=:id_user',
    'ChatRoom membership check must bind both room id and user id.'
);
assert_idor_contains(
    $chatRoom,
    'function _requireUserAccess($User)',
    'ChatRoom.php must expose a throwing room-membership guard.'
);
assert_idor_contains(
    $chatRoom,
    '$this->_requireUserAccess($User);',
    'ChatRoom::_sendMessage() must enforce membership before inserting messages.'
);
assert_idor_contains(
    $chatRoom,
    'function _resolveAttachedFile',
    'ChatRoom.php must resolve chat attachments through a bounded helper.'
);
assert_idor_contains(
    $chatRoom,
    "realpath(",
    'Chat attachment download helper must canonicalize local paths.'
);
assert_idor_contains(
    $chatRoom,
    "'/public/chat/'",
    'Chat attachment download helper must restrict files to public/chat.'
);

$loadRoom = read_idor_source($root, 'app/modules/kr-chat/src/actions/loadRoom.php');
assert_idor_contains(
    $loadRoom,
    '$ChatRoom->_requireUserAccess($User);',
    'loadRoom.php must verify room membership before rendering messages.'
);

$roomSendMessage = read_idor_source($root, 'app/modules/kr-chat/src/actions/roomSendMessage.php');
assert_idor_contains(
    $roomSendMessage,
    '$Room->_requireUserAccess($User);',
    'roomSendMessage.php must verify room membership before accepting messages or files.'
);

$downloadAttachedFile = read_idor_source($root, 'app/modules/kr-chat/src/actions/downloadAttachedFile.php');
assert_idor_contains(
    $downloadAttachedFile,
    'ChatRoom::_resolveAttachedFile($file_url)',
    'downloadAttachedFile.php must resolve encrypted attachment URLs through the bounded helper.'
);
assert_idor_contains(
    $downloadAttachedFile,
    '$ChatRoom->_requireUserAccess($User);',
    'downloadAttachedFile.php must verify room membership before streaming attachments.'
);
assert_idor_contains(
    $downloadAttachedFile,
    'readfile($downloadFile[\'path\'])',
    'downloadAttachedFile.php must stream the canonical local file path.'
);
assert_idor_not_contains(
    $downloadAttachedFile,
    'readfile($file_url)',
    'downloadAttachedFile.php must not stream a decrypted arbitrary URL/path directly.'
);

echo "Access-control IDOR regression checks passed.\n";

?>
