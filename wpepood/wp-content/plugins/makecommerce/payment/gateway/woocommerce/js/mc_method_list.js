jQuery(document).ready(function($) {

    function method_list_set(pick = false) {

        if (pick) {
            MC_METHOD_LIST.pick = pick; //allows changing payment method country after it has been automatically selected via paylater
        }

        var selectedCountry = MC_METHOD_LIST.country;

        if ( jQuery('.li.makecommerce-picker-country').length == 1 ) {

            jQuery('li.makecommerce-picker-country').show();
            jQuery('div.makecommerce_country_picker_countries').hide();
            jQuery('li.makecommerce-picker-country > input, li.makecommerce-picker-country > label').hide();
        } else {

            makecommercePick();
            
            jQuery('body').on('change', 'select[name=makecommerce_country_picker_select]', function() {
                if (MC_METHOD_LIST.pick) {
                    selectedCountry = jQuery(this).val();
                }

                jQuery('input[name=makecommerce_country_picker]').removeAttr('checked');
                makecommercePick(selectedCountry);
            });

            jQuery('body').on('change', 'input[name=makecommerce_country_picker]', function() {

                if (jQuery(this).is(":checked")) {
                    if (MC_METHOD_LIST.pick) {
                        selectedCountry = jQuery(this).val();
                    }

                    jQuery('select[name=makecommerce_country_picker_select]').val(selectedCountry);
                    makecommercePick(selectedCountry);
                }
            });
            
            function makecommercePick(selectedCountry = null) {

                if (selectedCountry == null) {
                    selectedCountry = jQuery('#makecommerce_customer_country').val();
                }
                jQuery('select#'+MC_METHOD_LIST.id).val('');
                jQuery('div.makecommerce-banklink-picker').removeClass('selected');
                jQuery('label.makecommerce_country_picker_label').removeClass('selected');
                
                if ( MC_METHOD_LIST.settings.ui_widget_groupcountries && MC_METHOD_LIST.settings.ui_widget_groupcountries == 'no' ) {
                    jQuery('li.makecommerce-picker-country').hide();
                } else {
                    jQuery('li.makecommerce-picker-country').show();
                }

                jQuery('div#makecommerce_country_picker_methods_' + selectedCountry).parent().show();
                jQuery('label[for=makecommerce_country_picker_' + selectedCountry + ']').addClass('selected');
            }
        }

        jQuery('div.makecommerce-banklink-picker').on('click', function() {

            var banklink_id = jQuery(this).attr('banklink_id');
            
            jQuery('select#'+MC_METHOD_LIST.id).val(banklink_id);
            jQuery('div.makecommerce-banklink-picker').removeClass('selected');
            jQuery(this).addClass('selected');
        });
    }

    method_list_set();

    //update also on checkout ajax updates
    jQuery( document.body ).on( 'updated_checkout', function() {
        method_list_set(true);
    });
});