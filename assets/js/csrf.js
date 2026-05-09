(function($){
  if(typeof $ === 'undefined') return;

  var fieldName = 'krypto_csrf_token';

  function csrfToken(){
    return $('meta[name="krypto-csrf-token"]').attr('content') || window.KRYPTO_CSRF_TOKEN || '';
  }

  function sameOrigin(url){
    if(!url) return true;
    var link = document.createElement('a');
    link.href = url;
    return link.protocol === window.location.protocol && link.host === window.location.host;
  }

  function unsafeMethod(method){
    method = String(method || 'GET').toUpperCase();
    return $.inArray(method, ['POST', 'PUT', 'PATCH', 'DELETE']) !== -1;
  }

  function ensureFormToken(form){
    var $form = $(form);
    var method = String($form.attr('method') || 'GET').toUpperCase();
    var action = $form.attr('action') || window.location.href;
    var token = csrfToken();

    if(!unsafeMethod(method) || !sameOrigin(action) || token === '') return;

    var $field = $form.find('input[name="' + fieldName + '"]');
    if($field.length === 0){
      $field = $('<input>', {type: 'hidden', name: fieldName});
      $form.append($field);
    }
    $field.val(token);
  }

  function appendToUrl(url){
    var token = csrfToken();
    if(token === '') return url;
    var separator = (String(url).indexOf('?') === -1 ? '?' : '&');
    return url + separator + encodeURIComponent(fieldName) + '=' + encodeURIComponent(token);
  }

  $.ajaxPrefilter(function(options, originalOptions, jqXHR){
    var method = options.type || options.method || 'GET';
    var token = csrfToken();

    if(token === '' || !unsafeMethod(method) || !sameOrigin(options.url)) return;
    jqXHR.setRequestHeader('X-CSRF-Token', token);
  });

  $(document).on('submit', 'form', function(){
    ensureFormToken(this);
  });

  $(function(){
    $('form').each(function(){
      ensureFormToken(this);
    });
  });

  window.KryptoCsrf = {
    token: csrfToken,
    fieldName: function(){ return fieldName; },
    appendToFormData: function(formData){
      if(formData && typeof formData.append === 'function') formData.append(fieldName, csrfToken());
      return formData;
    },
    appendToUrl: appendToUrl
  };
})(jQuery);
