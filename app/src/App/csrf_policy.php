<?php

/**
 * CSRF exceptions for non-browser callbacks, API, and cron endpoints.
 *
 * Browser-originated first-party actions should not be added here. Each entry
 * must document why a session CSRF token cannot be supplied and what alternate
 * validation constrains the request.
 */
return [
  'allowlist' => [
    'app/modules/kr-api/src/actions/receive.php' => [
      'reason' => 'Public API endpoint for third-party clients, not a browser form/AJAX flow.',
      'validation' => 'Requires the configured API key in the key query parameter before routing.'
    ],
    'app/modules/kr-changenow/src/actions/syncMarketData.php' => [
      'reason' => 'Scheduled ChangeNOW market-data sync, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN before provider sync.'
    ],
    'app/modules/kr-chat/src/actions/clearCron.php' => [
      'reason' => 'Scheduled chat attachment cleanup task, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN before cleanup.'
    ],
    'app/modules/kr-facebookoauth/src/actions/callback.php' => [
      'reason' => 'Facebook OAuth provider callback cannot include a Krypto CSRF field.',
      'validation' => 'Facebook SDK validates the OAuth response and exchanges the provider token.'
    ],
    'app/modules/kr-googleoauth/src/actions/callback.php' => [
      'reason' => 'Google OAuth provider callback cannot include a Krypto CSRF field.',
      'validation' => 'Google OAuth state stored in session must match the callback state before token exchange.'
    ],
    'app/modules/kr-payment/src/actions/processBlockonomics.php' => [
      'reason' => 'Blockonomics payment callback cannot include a Krypto CSRF field.',
      'validation' => 'Callback txid is reloaded from the Blockonomics API and matched to a locally stored deposit address.'
    ],
    'app/modules/kr-payment/src/actions/processCoinGate.php' => [
      'reason' => 'CoinGate subscription callback/return cannot include a Krypto CSRF field.',
      'validation' => 'CoinGate order id is fetched through the authenticated CoinGate API and matched to the callback order_id.'
    ],
    'app/modules/kr-payment/src/actions/processFortumo.php' => [
      'reason' => 'Fortumo subscription callback cannot include a Krypto CSRF field.',
      'validation' => 'Fortumo callback signature is checked with the configured Fortumo secret key.'
    ],
    'app/modules/kr-payment/src/actions/processMollie.php' => [
      'reason' => 'Mollie subscription webhook cannot include a Krypto CSRF field.',
      'validation' => 'Mollie payment id is fetched with the configured Mollie API key and metadata is parsed server-side.'
    ],
    'app/modules/kr-payment/src/actions/processPayeer.php' => [
      'reason' => 'Payeer payment callback cannot include a Krypto CSRF field.',
      'validation' => 'Payeer source IP is restricted and m_sign is checked against the configured order signature.'
    ],
    'app/modules/kr-payment/src/actions/processPaypal.php' => [
      'reason' => 'PayPal subscription return cannot include a Krypto CSRF field.',
      'validation' => 'PayPal SDK validates the provider token/payment for the plan stored in the user session.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processCoinGate.php' => [
      'reason' => 'CoinGate deposit callback/return cannot include a Krypto CSRF field.',
      'validation' => 'CoinGate order id is fetched through the authenticated CoinGate API and matched to the callback order_id.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processCoinbaseCommerce.php' => [
      'reason' => 'Coinbase Commerce webhook cannot include a Krypto CSRF field.',
      'validation' => 'Webhook signature is validated with the configured Coinbase Commerce shared secret.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processMollie.php' => [
      'reason' => 'Mollie deposit webhook cannot include a Krypto CSRF field.',
      'validation' => 'Mollie payment id is fetched with the configured Mollie API key and metadata is parsed server-side.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processPaygol.php' => [
      'reason' => 'Legacy Paygol callback placeholder is not a browser form/AJAX flow.',
      'validation' => 'Current endpoint does not mutate application state; future Paygol processing must add provider validation before mutation.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processPaypal.php' => [
      'reason' => 'PayPal deposit return cannot include a Krypto CSRF field.',
      'validation' => 'PayPal SDK validates the provider token/payment for the amount stored in the user session.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processPaystack.php' => [
      'reason' => 'Paystack webhook cannot include a Krypto CSRF field.',
      'validation' => 'Paystack event owner is discovered with configured live/test private keys before deposit state changes.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processPerfectMoney.php' => [
      'reason' => 'Perfect Money IPN cannot include a Krypto CSRF field.',
      'validation' => 'V2_HASH is recomputed with the configured alternate passphrase before processing.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processPolipayments.php' => [
      'reason' => 'POLi Payments return cannot include a Krypto CSRF field.',
      'validation' => 'Returned token is verified through the POLi Payments transaction lookup API.'
    ],
    'app/modules/kr-payment/src/actions/deposit/processRave.php' => [
      'reason' => 'Flutterwave/Rave return cannot include a Krypto CSRF field.',
      'validation' => 'Callback transaction reference is re-queried through the configured Rave API client.'
    ],
    'app/modules/kr-user/src/actions/cronDemo.php' => [
      'reason' => 'Scheduled demo cleanup task, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN, then demo mode before deleting expired demo users.'
    ],
    'app/src/App/actions/cronCleanCache.php' => [
      'reason' => 'Scheduled cache cleanup task, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN before cache cleanup.'
    ],
    'app/src/CryptoApi/actions/CheckNotification.php' => [
      'reason' => 'Scheduled notification check task, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN before notification checks.'
    ],
    'app/src/CryptoApi/actions/SyncCoin.php' => [
      'reason' => 'Scheduled coin-list sync task, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN before coin sync.'
    ],
    'app/src/CryptoApi/actions/SyncExchanges.php' => [
      'reason' => 'Scheduled exchange-list sync task, not a user browser action.',
      'validation' => 'Requires CLI execution or a matching KRYPTO_CRON_TOKEN before exchange sync.'
    ]
  ]
];

?>
