(function($) {
  if (!$) {
    return;
  }

  function close() {
    $('.kr-zoombox-overlay').remove();
    $('body').removeClass('kr-zoombox-open');
  }

  function show(content) {
    close();

    var overlay = $('<div class="kr-zoombox-overlay" role="dialog" aria-modal="true"></div>');
    var box = $('<div class="kr-zoombox-dialog"></div>');
    var button = $('<button type="button" class="kr-zoombox-close" aria-label="Close">&times;</button>');
    var body = $('<div class="kr-zoombox-body"></div>').append(content);

    box.append(button).append(body);
    overlay.append(box);
    $('body').append(overlay).addClass('kr-zoombox-open');
  }

  function imageContent(link) {
    var image = $('<img class="kr-zoombox-image" alt="">');
    image.attr('src', link.attr('href'));
    image.attr('alt', link.attr('data-title') || link.attr('title') || '');
    return image;
  }

  $(document).on('click', '.kr-zoombox-overlay', function(event) {
    if ($(event.target).is('.kr-zoombox-overlay, .kr-zoombox-close')) {
      close();
    }
  });

  $(document).on('keydown', function(event) {
    if (event.key === 'Escape') {
      close();
    }
  });

  $.zoombox = {
    html: function(html) {
      show($(html));
    },
    close: close
  };

  $.fn.zoombox = function() {
    return this.each(function() {
      var link = $(this);

      link.off('click.krZoombox').on('click.krZoombox', function(event) {
        event.preventDefault();
        show(imageContent(link));
      });
    });
  };
})(window.jQuery);
