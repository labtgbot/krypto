(function(){
  var networkLabels = {
    BTC: 'Bitcoin network',
    ETH: 'Ethereum ERC20',
    USDT: 'Tether TRC20',
    XMR: 'Monero network'
  };

  var quoteRates = {
    BTC_ETH: 16.3,
    BTC_USDT: 64000,
    BTC_XMR: 410,
    ETH_BTC: 0.061,
    ETH_USDT: 3920,
    ETH_XMR: 25.4,
    USDT_BTC: 0.0000156,
    USDT_ETH: 0.000255,
    USDT_XMR: 0.0064,
    XMR_BTC: 0.00244,
    XMR_ETH: 0.039,
    XMR_USDT: 156
  };

  function quoteRate(from, to){
    if(from === to) return 0;
    return quoteRates[from + '_' + to] || 1;
  }

  function parseAmount(value){
    var parsed = parseFloat(String(value).replace(',', '.'));
    return isNaN(parsed) || parsed < 0 ? 0 : parsed;
  }

  function formatAmount(value){
    if(value >= 1000) return value.toFixed(2);
    if(value >= 1) return value.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    return value.toFixed(8).replace(/0+$/, '').replace(/\.$/, '');
  }

  function updateQuote($swap){
    var from = $swap.find('[data-kr-swap-from]').val();
    var to = $swap.find('[data-kr-swap-to]').val();
    var amount = parseAmount($swap.find('[data-kr-swap-amount]').val());
    var rate = quoteRate(from, to);
    var receive = amount * rate;

    $swap.find('[data-kr-swap-from-network]').text(networkLabels[from] || from);
    $swap.find('[data-kr-swap-to-network]').text(networkLabels[to] || to);
    $swap.find('[data-kr-swap-receive]').val(rate === 0 ? '' : formatAmount(receive));
    $swap.find('[data-kr-swap-summary-amount]').text(rate === 0 ? 'Select another pair' : formatAmount(receive));
    $swap.find('[data-kr-swap-summary-symbol]').text(rate === 0 ? '' : to);
    $swap.find('[data-kr-swap-state]').text(rate === 0 ? 'Unsupported pair' : 'Ready for preview');
    $swap.find('[data-kr-swap-fee]').text(rate === 0 ? 'Unavailable' : 'Included in quote');
  }

  function setQuoteReady($swap){
    var address = $.trim($swap.find('[data-kr-swap-address]').val());
    if(address.length === 0){
      $swap.find('[data-kr-swap-state]').text('Destination address needed');
      $swap.find('[data-kr-swap-address]').focus();
      return;
    }

    if($swap.hasClass('kr-changenow-has-quote')){
      $swap.addClass('kr-changenow-has-transaction');
      $swap.find('[data-kr-swap-action]').text('Refresh quote');
      $swap.find('[data-kr-swap-state]').text('Waiting for deposit');
      return;
    }

    $swap.addClass('kr-changenow-has-quote');
    $swap.find('[data-kr-swap-action]').text('Create swap request');
    $swap.find('[data-kr-swap-state]').text('Quote valid for 20 min');
  }

  function initChangeNowSwap(){
    $('[data-kr-changenow-swap]').each(function(){
      var $swap = $(this);
      if($swap.data('kr-changenow-bound')) return;
      $swap.data('kr-changenow-bound', true);

      updateQuote($swap);

      $swap.on('input change', '[data-kr-swap-amount], [data-kr-swap-from], [data-kr-swap-to]', function(){
        $swap.removeClass('kr-changenow-has-quote kr-changenow-has-transaction');
        $swap.find('[data-kr-swap-action]').text('Get quote');
        updateQuote($swap);
      });

      $swap.on('click', '[data-kr-rate-mode]', function(){
        $swap.find('[data-kr-rate-mode]').removeClass('kr-changenow-rate-active');
        $(this).addClass('kr-changenow-rate-active');
        $swap.find('[data-kr-swap-state]').text($(this).attr('data-kr-rate-mode') === 'fixed' ? 'Fixed quote preview' : 'Ready for preview');
      });

      $swap.on('click', '[data-kr-swap-switch]', function(){
        var $from = $swap.find('[data-kr-swap-from]');
        var $to = $swap.find('[data-kr-swap-to]');
        var from = $from.val();
        $from.val($to.val());
        $to.val(from);
        $swap.removeClass('kr-changenow-has-quote kr-changenow-has-transaction');
        $swap.find('[data-kr-swap-action]').text('Get quote');
        updateQuote($swap);
      });

      $swap.on('click', '[data-kr-swap-action]', function(){
        setQuoteReady($swap);
      });

      $swap.on('click', '[data-kr-swap-track]', function(){
        var value = $.trim($swap.find('[data-kr-swap-track-input]').val());
        $swap.find('[data-kr-swap-track-result]').text(value.length === 0 ? 'Enter an ID to view the latest provider status.' : value + ': waiting for provider sync.');
      });
    });
  }

  window.initChangeNowSwap = initChangeNowSwap;

  if(window.jQuery){
    $(document).ready(function(){
      initChangeNowSwap();
    });
  }
})();
