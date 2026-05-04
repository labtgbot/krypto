(function($){

  $(document).ready(function(){
    var $shell = $('.kr-public-swap-shell');
    if($shell.length === 0) return;

    var actionUrl = $shell.attr('data-action-url');
    var statusToken = $shell.attr('data-status-token');
    var userLogged = $shell.attr('data-user-logged') === '1';
    var signupAllowed = $shell.attr('data-signup-allowed') === '1';
    var $form = $shell.find('.kr-public-swap-form');
    var $message = $shell.find('.kr-public-swap-message');
    var $quotePanel = $shell.find('.kr-public-quote-panel');
    var $resultPanel = $shell.find('.kr-public-result-panel');
    var currentLookupToken = statusToken || '';
    var currentStatusUrl = currentLookupToken ? window.location.href : '';

    syncAllAssetFields();

    $shell.on('change', '.kr-public-asset-select', function(){
      syncAssetSelect($(this));
    });

    $shell.on('input change', '[data-sync-field]', function(){
      syncTextField($(this));
    });

    $shell.on('change', 'select[name="fromAsset"], select[name="flow"]', function(){
      syncAllAssetFields();
      clearQuote();
      refreshDestinationAssets();
    });

    $shell.on('input change', 'input, select', function(){
      if($.inArray($(this).attr('name'), ['rateId', 'validUntil']) === -1) clearQuote();
    });

    $shell.on('click', '.kr-public-validate-address', function(){
      var $button = $(this);
      setBusy($button, 'Checking');
      request('validate', formPayload()).done(function(response){
        var validation = response.validation || {};
        if(validation.result === true) setMessage('success', 'Destination address is valid.');
        else setMessage('error', validation.message || 'Destination address is not valid.');
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      }).always(function(){
        clearBusy($button);
      });
    });

    $shell.on('click', '.kr-public-get-quote', function(){
      var $button = $(this);
      setBusy($button, 'Quoting');
      request('quote', formPayload()).done(function(response){
        applyQuote(response.quote || {});
        setMessage('success', 'Quote ready.');
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      }).always(function(){
        clearBusy($button);
      });
    });

    $(document).on('submit', '.kr-public-swap-form', function(e){
      e.preventDefault();
      var $button = $shell.find('.kr-public-create-swap');
      setBusy($button, 'Creating');
      request('create', formPayload()).done(function(response){
        applyCreatedSwap(response.swap || {});
        setMessage('success', 'Swap created.');
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      }).always(function(){
        clearBusy($button);
      });
      return false;
    });

    $shell.on('click', '.kr-public-copy-payin', function(){
      copyText($(this).attr('data-copy'));
      setMessage('success', 'Pay-in address copied.');
    });

    $shell.on('click', '.kr-public-refresh-status', function(){
      var $button = $(this);
      if(!currentLookupToken) return;
      setBusy($button, 'Refreshing');
      request('status', {lookupToken: currentLookupToken}).done(function(response){
        applyStatus(response.status || {});
        setMessage('success', 'Status refreshed.');
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      }).always(function(){
        clearBusy($button);
      });
    });

    $shell.on('click', '.kr-public-refund-swap', function(){
      var $button = $(this);
      if(!currentLookupToken) return;
      setBusy($button, 'Requesting');
      request('refund', {
        lookupToken: currentLookupToken,
        refundAddress: $form.find('input[name="refundAddress"]').val(),
        refundExtraId: $form.find('input[name="refundExtraId"]').val()
      }).done(function(response){
        applyStatus(response.status || {});
        setMessage('success', 'Refund requested.');
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      }).always(function(){
        clearBusy($button);
      });
    });

    $shell.on('click', '.kr-public-continue-swap', function(){
      var $button = $(this);
      if(!currentLookupToken) return;
      setBusy($button, 'Continuing');
      request('continue', {lookupToken: currentLookupToken}).done(function(response){
        applyStatus(response.status || {});
        setMessage('success', 'Continue requested.');
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      }).always(function(){
        clearBusy($button);
      });
    });

    $shell.on('click', '.kr-public-create-account, .kr-public-auth-jump', function(e){
      var $accountAccess = $('#kr-account-access');
      if($accountAccess.length === 0) return;
      if($(this).hasClass('kr-public-create-account') && (!signupAllowed || userLogged)) return;
      e.preventDefault();
      $('body').addClass('kr-public-account-access-visible');
      if(typeof showLoginView === 'function' && $(this).hasClass('kr-public-create-account')) showLoginView('signup');
      $('html, body').animate({scrollTop: $accountAccess.offset().top - 20}, 220);
    });

    if(statusToken && statusToken.length > 0){
      request('status', {lookupToken: statusToken}).done(function(response){
        applyStatus(response.status || {});
      }).fail(function(response){
        setMessage('error', responseMessage(response));
      });
    }

    function formPayload(){
      syncAllAssetFields();
      var data = {};
      $.each($form.serializeArray(), function(_, field){
        data[field.name] = field.value;
      });
      return data;
    }

    function request(action, data){
      var payload = $.extend({}, data || {}, {action: action});
      return $.ajax({
        url: actionUrl,
        method: 'POST',
        data: payload,
        dataType: 'json'
      }).then(function(response){
        if(typeof response === 'string') response = $.parseJSON(response);
        if(!response || parseInt(response.error, 10) !== 0){
          var failed = $.Deferred();
          failed.reject(response || {msg: 'ChangeNOW request failed.'});
          return failed.promise();
        }
        return response;
      }, function(){
        var failed = $.Deferred();
        failed.reject({msg: 'ChangeNOW request failed.'});
        return failed.promise();
      });
    }

    function syncAllAssetFields(){
      $shell.find('.kr-public-asset-select').each(function(){
        syncAssetSelect($(this));
      });
      $shell.find('[data-sync-field]').each(function(){
        syncTextField($(this));
      });
    }

    function syncAssetSelect($select){
      var prefix = $select.attr('data-asset-prefix');
      var $option = $select.find('option:selected');
      var valueParts = String($select.val() || '').split(':');
      var currency = $option.attr('data-currency') || valueParts[0] || '';
      var network = $option.attr('data-network') || valueParts[1] || currency;

      $form.find('input[name="' + prefix + 'Currency"]').val(currency);
      $form.find('input[name="' + prefix + 'Network"]').val(network);
    }

    function syncTextField($field){
      $form.find('input[name="' + $field.attr('data-sync-field') + '"]').val($field.val());
    }

    function refreshDestinationAssets(){
      var $toSelect = $form.find('select[name="toAsset"]');
      if($toSelect.length === 0) return;

      var previous = $toSelect.val();
      request('destinations', formPayload()).done(function(response){
        var assets = response.assets || [];
        if(assets.length === 0) return;

        var options = '';
        var hasPrevious = false;
        $.each(assets, function(_, asset){
          var ticker = asset.ticker || '';
          var network = asset.network || ticker;
          var value = ticker + ':' + network;
          if(value === previous) hasPrevious = true;
          options += '<option value="' + escapeHtml(value) + '" data-currency="' + escapeHtml(ticker) + '" data-network="' + escapeHtml(network) + '">' +
                     escapeHtml(assetLabel(asset)) + '</option>';
        });
        $toSelect.html(options);
        if(hasPrevious) $toSelect.val(previous);
        syncAssetSelect($toSelect);
      });
    }

    function clearQuote(){
      $form.find('input[name="rateId"]').val('');
      $form.find('input[name="validUntil"]').val('');
      $shell.find('.kr-public-create-swap').prop('disabled', true);
      $quotePanel.removeClass('kr-public-panel-visible').html('');
    }

    function applyQuote(quote){
      $form.find('input[name="rateId"]').val(quote.rateId || '');
      $form.find('input[name="validUntil"]').val(quote.validUntil || '');
      $shell.find('.kr-public-create-swap').prop('disabled', false);

      var receiveAmount = quote.estimatedReceiveAmount || quote.toAmount || '';
      var details = [
        detail('You send', amountWithAsset(quote.fromAmount || quote.amount, quote.fromCurrency, quote.fromNetwork)),
        detail('You receive', amountWithAsset(receiveAmount, quote.toCurrency, quote.toNetwork)),
        detail('Minimum', amountWithAsset(quote.minAmount, quote.fromCurrency, quote.fromNetwork)),
        detail('Network fee', quote.networkFee),
        detail('Deposit fee', quote.depositFee),
        detail('Withdrawal fee', quote.withdrawalFee),
        detail('Quote expires', formatDate(quote.validUntil))
      ].join('');

      $quotePanel.html('<h2>Quote</h2><div class="kr-public-detail-grid">' + details + '</div>').addClass('kr-public-panel-visible');
    }

    function applyCreatedSwap(swap){
      var transaction = swap.transaction || {};
      currentLookupToken = swap.lookupToken || currentLookupToken;
      currentStatusUrl = swap.statusUrl || currentStatusUrl;
      renderTransaction(transaction, swap.lookupToken, swap.statusUrl, swap.supportEmail, null);
      if(swap.statusUrl && window.history && window.history.replaceState){
        try {
          window.history.replaceState(null, document.title, swap.statusUrl);
        } catch (e) {}
      }
    }

    function applyStatus(status){
      var transaction = status.transaction || {};
      renderTransaction(transaction, currentLookupToken, currentStatusUrl, status.supportEmail, status.statusWarning || null);
      if(status.statusWarning) setMessage('info', status.statusWarning);
    }

    function renderTransaction(transaction, lookupToken, statusUrl, supportEmail, warning){
      var details = [
        detail('Status', transaction.status),
        detail('Transaction ID', transaction.providerId || transaction.id),
        detail('Pay-in amount', amountWithAsset(transaction.fromAmount, transaction.fromCurrency, transaction.fromNetwork)),
        detail('Pay-in address', transaction.payinAddress),
        detail('Pay-in memo/tag', transaction.payinExtraId),
        detail('Expected receive', amountWithAsset(transaction.toAmount, transaction.toCurrency, transaction.toNetwork)),
        detail('Destination address', transaction.payoutAddress),
        detail('Destination memo/tag', transaction.payoutExtraId),
        detail('Refund address', transaction.refundAddress),
        detail('Updated', formatDate(transaction.updatedAt))
      ].join('');

      var actions = '<div class="kr-public-result-actions">';
      var availableActions = transaction.availableActions || {};
      if(transaction.payinAddress) actions += '<button type="button" class="kr-public-copy-payin" data-copy="' + escapeHtml(transaction.payinAddress) + '">Copy pay-in address</button>';
      if(lookupToken) actions += '<button type="button" class="kr-public-refresh-status">Refresh status</button>';
      if(lookupToken && availableActions.refund === true) actions += '<button type="button" class="kr-public-refund-swap">Request refund</button>';
      if(lookupToken && availableActions.continue === true) actions += '<button type="button" class="kr-public-continue-swap">Continue swap</button>';
      if(statusUrl) actions += '<a href="' + escapeHtml(statusUrl) + '">Status link</a>';
      if(signupAllowed && !userLogged && $('#kr-account-access').length > 0) actions += '<button type="button" class="kr-public-create-account">Create account</button>';
      if(supportEmail) actions += '<a href="mailto:' + escapeHtml(supportEmail) + '">Support</a>';
      actions += '</div>';

      var warningHtml = warning ? '<div class="kr-public-swap-message kr-public-message-info">' + escapeHtml(warning) + '</div>' : '';
      var tokenHtml = lookupToken ? detail('Lookup token', lookupToken) : '';
      $resultPanel.html('<h2>Swap status</h2>' + warningHtml + '<div class="kr-public-detail-grid">' + details + tokenHtml + '</div>' + actions).addClass('kr-public-panel-visible');
    }

    function detail(label, value){
      if(value === null || typeof value === 'undefined' || String(value).length === 0) return '';
      return '<div class="kr-public-detail"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
    }

    function amountWithAsset(amount, currency, network){
      if(amount === null || typeof amount === 'undefined' || String(amount).length === 0) return '';
      return String(amount) + ' ' + assetName(currency, network);
    }

    function assetName(currency, network){
      currency = String(currency || '').toUpperCase();
      network = String(network || '').toUpperCase();
      if(currency === '') return '';
      return currency + (network !== '' ? ' / ' + network : '');
    }

    function assetLabel(asset){
      return assetName(asset.ticker, asset.network) + (asset.name ? ' - ' + asset.name : '');
    }

    function formatDate(value){
      if(!value) return '';
      var parsed = new Date(value);
      if(isNaN(parsed.getTime())) return value;
      return parsed.toLocaleString();
    }

    function setMessage(type, text){
      $message.removeClass('kr-public-message-error kr-public-message-success kr-public-message-info');
      if(!text){
        $message.html('');
        return;
      }
      $message.addClass('kr-public-message-' + type).html(escapeHtml(text));
    }

    function responseMessage(response){
      return (response && response.msg ? response.msg : 'ChangeNOW request failed.');
    }

    function setBusy($button, text){
      $button.data('original-text', $button.text());
      $button.prop('disabled', true).text(text);
    }

    function clearBusy($button){
      $button.text($button.data('original-text') || $button.text());
      if($button.hasClass('kr-public-create-swap') && $form.find('input[name="rateId"]').val() === '' && $form.find('select[name="flow"]').val() === 'fixed-rate') return;
      $button.prop('disabled', false);
    }

    function copyText(text){
      text = String(text || '');
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text);
        return;
      }
      var $field = $('<input type="text">').val(text).appendTo('body');
      $field.select();
      document.execCommand('copy');
      $field.remove();
    }

    function escapeHtml(value){
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
  });

})(jQuery);
