<?php

/**
 * Explicit module controller/action routing policy.
 *
 * Controllers are PHP classes loaded into the application shell. Actions are
 * direct module endpoints that may be requested through the web server.
 */
return [
  'controllers' => [
    'kr-admin' => [
      'src/Admin.php'
    ],
    'kr-api' => [
      'src/Api.php',
      'src/TechnicalIndicator.php'
    ],
    'kr-blockfolio' => [
      'src/Blockfolio.php',
      'src/Holding.php'
    ],
    'kr-blocksexplorer' => [
      'src/BitcoinExplorer.php',
      'src/BlockExplorer.php',
      'src/ChainSo.php',
      'src/DepositAddress.php',
      'src/Etherblock.php',
      'src/LitecoinExplorer.php'
    ],
    'kr-calculator' => [
      'src/Calculator.php'
    ],
    'kr-changenow' => [
      'src/ChangeNowAdminPanel.php',
      'src/ChangeNowAdminRepository.php',
      'src/ChangeNowApiClient.php',
      'src/ChangeNowApiException.php',
      'src/ChangeNowMarketData.php',
      'src/ChangeNowMarketRepository.php',
      'src/ChangeNowProviderMode.php',
      'src/ChangeNowPublicRateLimit.php',
      'src/ChangeNowPublicSwapFlow.php',
      'src/ChangeNowPublicSwapRepository.php',
      'src/ChangeNowReferralAttribution.php',
      'src/ChangeNowRetention.php',
      'src/ChangeNowSettings.php',
      'src/ChangeNowSwapProviderInterface.php',
      'src/ChangeNowUnavailableProvider.php',
      'src/ChangeNowWidget.php'
    ],
    'kr-chat' => [
      'src/Chat.php',
      'src/ChatMessage.php',
      'src/ChatRoom.php'
    ],
    'kr-coin' => [],
    'kr-dashboard' => [
      'src/Dashboard.php',
      'src/DashboardGraph.php',
      'src/DashboardToolbox.php',
      'src/DashboardTopList.php',
      'src/OrderBookRequest.php'
    ],
    'kr-facebookoauth' => [
      'src/FacebookOauth.php'
    ],
    'kr-googleoauth' => [
      'src/GoogleOauth.php'
    ],
    'kr-identity' => [
      'src/Identity.php'
    ],
    'kr-manager' => [
      'src/Manager.php',
      'src/Statistics.php'
    ],
    'kr-marketanalysis' => [],
    'kr-news' => [
      'src/Calendar.php',
      'src/Feed.php',
      'src/News.php',
      'src/RssFeed.php',
      'src/RssFeedArticle.php',
      'src/Social.php'
    ],
    'kr-notifications' => [
      'src/Notification.php',
      'src/NotificationCenter.php'
    ],
    'kr-payment' => [
      'src/Banktransfert.php',
      'src/Blockonomics.php',
      'src/CoinGate.php',
      'src/CoinbaseCommerce.php',
      'src/Coinpayments.php',
      'src/CreditCard.php',
      'src/Fortumo.php',
      'src/Mollie.php',
      'src/Payeer.php',
      'src/PaymentObject.php',
      'src/Paypal.php',
      'src/Paystack.php',
      'src/PerfectMoney.php',
      'src/Polipayments.php',
      'src/RaveFlutterwave.php',
      'src/RaveFlutterwaveHandler.php'
    ],
    'kr-search' => [
      'src/Search.php'
    ],
    'kr-socket' => [],
    'kr-trade' => [
      'src/Balance.php'
    ],
    'kr-user' => [
      'src/Charges.php',
      'src/ChargesPlan.php'
    ],
    'kr-watchinglist' => [
      'src/WatchingList.php'
    ]
  ],
  'actions' => [
    'kr-admin' => [
      'src/actions/addAddtionalPage.php',
      'src/actions/addBankAccount.php',
      'src/actions/addIdentityDocument.php',
      'src/actions/addIdentityStep.php',
      'src/actions/addPlanSubscription.php',
      'src/actions/addRSSFeed.php',
      'src/actions/addSocialFeed.php',
      'src/actions/changeNowSupportAction.php',
      'src/actions/changePositionIdentityStep.php',
      'src/actions/deleteAdditionalPage.php',
      'src/actions/deleteBankaccount.php',
      'src/actions/deleteIdentityDocument.php',
      'src/actions/deleteIdentityStep.php',
      'src/actions/deleteRSSFeed.php',
      'src/actions/deleteSocialFeed.php',
      'src/actions/deleteUser.php',
      'src/actions/removePlanSubscription.php',
      'src/actions/saveCalendarSettings.php',
      'src/actions/saveChangeNow.php',
      'src/actions/saveChangeNowWidget.php',
      'src/actions/saveGeneralsettings.php',
      'src/actions/saveIdentity.php',
      'src/actions/saveIntro.php',
      'src/actions/savePayment.php',
      'src/actions/saveSmtpSettings.php',
      'src/actions/saveSubscription.php',
      'src/actions/saveTemplate.php',
      'src/actions/saveTrading.php',
      'src/actions/toggleCurrency.php'
    ],
    'kr-api' => [
      'src/actions/receive.php'
    ],
    'kr-blockfolio' => [
      'src/actions/addHolding.php',
      'src/actions/addHoldingForm.php',
      'src/actions/addItem.php',
      'src/actions/removeItem.php'
    ],
    'kr-blocksexplorer' => [],
    'kr-calculator' => [
      'src/actions/addCalculatorItem.php',
      'src/actions/getRates.php'
    ],
    'kr-changenow' => [
      'src/actions/publicSwap.php',
      'src/actions/supportAction.php',
      'src/actions/syncMarketData.php'
    ],
    'kr-chat' => [
      'src/actions/clearCron.php',
      'src/actions/createRoom.php',
      'src/actions/downloadAttachedFile.php',
      'src/actions/loadChat.php',
      'src/actions/loadRoom.php',
      'src/actions/roomSendMessage.php',
      'src/actions/searchUser.php',
      'src/actions/syncRightBar.php',
      'src/actions/toggleBlockUser.php'
    ],
    'kr-coin' => [],
    'kr-dashboard' => [
      'src/actions/addIndicator.php',
      'src/actions/addTopList.php',
      'src/actions/changeGraph.php',
      'src/actions/changeTypeGraph.php',
      'src/actions/createAlert.php',
      'src/actions/createNotification.php',
      'src/actions/deleteGraph.php',
      'src/actions/deleteNotification.php',
      'src/actions/deleteTopList.php',
      'src/actions/editIndicator.php',
      'src/actions/exportGraph.php',
      'src/actions/exportGraphAction.php',
      'src/actions/getCoinList.php',
      'src/actions/getIntroList.php',
      'src/actions/getOrderBook.php',
      'src/actions/loadChart.php',
      'src/actions/loadChartContent.php',
      'src/actions/loadLeftInfosCoin.php',
      'src/actions/loadToolbox.php',
      'src/actions/removeIndicator.php',
      'src/actions/saveIndicator.php'
    ],
    'kr-facebookoauth' => [
      'src/actions/callback.php'
    ],
    'kr-googleoauth' => [
      'src/actions/callback.php'
    ],
    'kr-identity' => [
      'src/actions/changeIdentityStatus.php',
      'src/actions/submitAsset.php'
    ],
    'kr-manager' => [
      'src/actions/actionPaymentManager.php',
      'src/actions/askProof.php',
      'src/actions/processBankTransfert.php',
      'src/actions/test.php',
      'src/actions/validateBankTransfert.php',
      'src/actions/wizardValidateBanktransfert.php'
    ],
    'kr-marketanalysis' => [
      'actions/getCoinsList.php'
    ],
    'kr-news' => [
      'src/actions/loadNews.php',
      'src/actions/loadSideCalendar.php',
      'src/actions/loadSideCalendarItem.php',
      'src/actions/loadSideNews.php',
      'src/actions/loadSideSocial.php'
    ],
    'kr-notifications' => [
      'src/actions/getNotificationsList.php',
      'src/actions/getNumNotifNS.php'
    ],
    'kr-payment' => [
      'src/actions/checkCoingate.php',
      'src/actions/checkFortumo.php',
      'src/actions/deposit/checkCoingate.php',
      'src/actions/deposit/checkPaymentStatus.php',
      'src/actions/deposit/processCoinGate.php',
      'src/actions/deposit/processCoinbaseCommerce.php',
      'src/actions/deposit/processCoinpayment.php',
      'src/actions/deposit/processMollie.php',
      'src/actions/deposit/processOther.php',
      'src/actions/deposit/processPaygol.php',
      'src/actions/deposit/processPaymentCard.php',
      'src/actions/deposit/processPaypal.php',
      'src/actions/deposit/processPaystack.php',
      'src/actions/deposit/processPerfectMoney.php',
      'src/actions/deposit/processPolipayments.php',
      'src/actions/deposit/processRave.php',
      'src/actions/processBlockonomics.php',
      'src/actions/processCoinGate.php',
      'src/actions/processFortumo.php',
      'src/actions/processMollie.php',
      'src/actions/processPayeer.php',
      'src/actions/processPaymentCard.php',
      'src/actions/processPaypal.php',
      'src/actions/proof/addProofBanktransfert.php',
      'src/actions/proof/sendProof.php',
      'src/actions/test.php'
    ],
    'kr-search' => [
      'actions/searchQuery.php',
      'src/actions/searchQuery.php'
    ],
    'kr-socket' => [],
    'kr-trade' => [],
    'kr-user' => [
      'src/actions/changeUserPicture.php',
      'src/actions/changeUserSettings.php',
      'src/actions/cronDemo.php',
      'src/actions/initPushbullet.php',
      'src/actions/login.php',
      'src/actions/logout.php',
      'src/actions/removeGoogleTFS.php',
      'src/actions/removePushbullet.php',
      'src/actions/resetPassword.php',
      'src/actions/signup.php',
      'src/actions/updateUserprofile.php',
      'src/actions/validateGoogleTFS.php'
    ],
    'kr-watchinglist' => [
      'src/actions/getWatchingItem.php',
      'src/actions/getWatchingListSymbol.php',
      'src/actions/removeWatchingListItem.php'
    ]
  ]
];

?>
