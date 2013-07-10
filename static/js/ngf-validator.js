(function($){

  $.fn.directText=function(delim) {
    if (!delim) delim = '';
    return this.contents().map(function() { return this.nodeType == 3 ? this.nodeValue : undefined}).get().join(delim);
  };

  function renderError(messages){
    if (messages.length){
      var html = '<div class="error"><h3>Encontramos um problema.</h3><p>Você precisa preencher o(s) seguinte(s) campo(s):<ul>';
      for (var i = 0; i < messages.length; i++){
        html += '<li>'+ $.trim(messages[i]) +'</li>';
      }

      html += '</p></div>';

      $('form').before(html);
    }
  }

  $('form').submit(function(){
    var messages = [];

    $('.ss-item-required').each(function(){
      var jqThis = $(this);

      var aria = jqThis.find('[aria-required="true"]');
      if ( aria.size() ){
        if ( !aria.val() ){
          messages.push( jqThis.find('.ss-q-title').directText() );
        }
      } else {

        if ( ! jqThis.find('input:checked').size() ){
          messages.push( jqThis.find('.ss-q-title').directText() );
        }
      }
    });

    renderError(messages);
    return (messages.length == 0);
  });
})(jQuery);