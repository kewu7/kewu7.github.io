jQuery(document).ready(function($) {

    function mc_autoselect_paylater_method()
    {
        $('input#payment_method_makecommerce').trigger( 'click' );

        //flag
        $('.country_picker_image_' + MC_PAYLATER_AUTOSELECT.country).trigger( 'click' );

        //selectbox
        $("[name='makecommerce_country_picker_select']").val( MC_PAYLATER_AUTOSELECT.country );

        //widget
        $('#' + MC_PAYLATER_AUTOSELECT.method).trigger( 'click' );

        //list
        $('#makecommerce_method_picker_' + MC_PAYLATER_AUTOSELECT.country + '_' +  MC_PAYLATER_AUTOSELECT.method).trigger( 'click' );
    }

    //update also on checkout ajax updates
    jQuery( document.body ).on( 'updated_checkout', function() {
        mc_autoselect_paylater_method();
    });

    mc_autoselect_paylater_method();
});
