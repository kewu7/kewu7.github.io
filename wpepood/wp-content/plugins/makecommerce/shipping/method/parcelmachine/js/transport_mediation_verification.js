jQuery('input#verify_feature_swc').on('click', function() {

    init_mc_loading();

    jQuery.ajax({
        url: MC_TRANSPORT_MEDIATION_VERIFICATION.site_url + '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: 'action=verify_feature_swc',
        success: function (output) {
            if (output) {
                if (output.feature_status == true) {
                    alert( MC_TRANSPORT_MEDIATION_VERIFICATION.enabled );
                } else {
                    alert( MC_TRANSPORT_MEDIATION_VERIFICATION.not_enabled );
                }
                
            } else {
                alert( MC_TRANSPORT_MEDIATION_VERIFICATION.error );
            }
        },
        complete: function() { stop_mc_loading(); }
    });
});

function init_mc_loading() {
    jQuery('input#verify_feature_swc').attr('disabled', 'disabled');
}

function stop_mc_loading() {
    jQuery('input#verify_feature_swc').removeAttr('disabled');
}
