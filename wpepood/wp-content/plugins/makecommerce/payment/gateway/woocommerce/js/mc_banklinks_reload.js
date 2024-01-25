jQuery('input#mc_banklinks_reload').on('click', function() {

    init_mc_loading();

    jQuery.ajax({
        url: MC_BANKLINKS_RELOAD.site_url + '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: 'action=mc_banklinks_reload',
        success: function (output) {
            if ( output.data ) {
                alert( output.data );
            } else {
                alert( MC_BANKLINKS_RELOAD.error );
            }
        },
        complete: function() { stop_mc_loading(); }
    });
});

function init_mc_loading() {
    jQuery('input#mc_banklinks_reload').attr('disabled', 'disabled');
}

function stop_mc_loading() {
    jQuery('input#mc_banklinks_reload').removeAttr('disabled');
}