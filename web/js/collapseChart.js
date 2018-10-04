

$('.chart-header').on('click', function(event) {
    event.stopImmediatePropagation();
    var collapseId = $(this).data('collapse');

    console.log('collapse id: ', collapseId);
    $('#' + collapseId).toggleClass( "collapse" );
    if( $('#' + collapseId).hasClass('collapse') ) {
        $(this).find('.chartsymbol').html('+');
    } else {
        $(this).find('.chartsymbol').html('-');
    }
});

