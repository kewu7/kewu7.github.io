jQuery(document).ready(function($) {

    var api_type = $('#woocommerce_' + MC_ADMIN_UI.id + '_api_type');
    
    var ui_inline_uselogo_row = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_inline_uselogo').closest('tr');
    var ui_widget_logosize_row = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_widget_logosize').closest('tr');
    var ui_widget_countryselector_row = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_widget_countryselector').closest('tr');
    var ui_widget_countries_hidden_row = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_widget_countries_hidden').closest('tr');
    var ui_widget_countries_hidden = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_widget_countries_hidden');

    var ui_mode = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_mode');
    var ui_widget_groupcountries = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_widget_groupcountries');
    var ui_inline_uselogo = $('#woocommerce_' + MC_ADMIN_UI.id + '_ui_inline_uselogo');
    
    parseVisibility();

    function parseVisibility() {

        $('.ui-identifier').closest('tr').show();
        
        if (api_type.val() == 'live') {
            $('.mc-test-link').hide();
            $('.api-test').closest('tr').hide();
        } else {
            $('.mc-test-link').show();
            $('.api-live').closest('tr').hide();
        }

        //hide/show logo size if text is selected
        if ( ui_inline_uselogo.val() == "text" ) {
            ui_widget_logosize_row.hide();
        } else {
            ui_widget_logosize_row.show();
        }

        if ( ui_mode.val() == "inline" ) {
            ui_inline_uselogo_row.show();
        } else {
            ui_inline_uselogo_row.hide();
        }

        if ( ui_widget_groupcountries.prop( 'checked' ) ) {
            ui_widget_countries_hidden_row.hide();
            ui_widget_countryselector_row.hide();
        } else {
            ui_widget_countries_hidden_row.show();
            ui_widget_countryselector_row.show();
        }

        //hide/show country selector options
        if ( ui_widget_countries_hidden.prop( 'checked' ) ) {
            ui_widget_countryselector_row.hide();
        } else {
            if ( !ui_widget_groupcountries.prop( 'checked' ) ) {
                ui_widget_countryselector_row.show();
            }
        }
    }
    
    ui_inline_uselogo.on('change', parseVisibility);
    api_type.on('change', parseVisibility);
    ui_mode.on('change', parseVisibility)
    ui_widget_groupcountries.on('change', parseVisibility);
    ui_widget_countries_hidden.on('change', parseVisibility);
});