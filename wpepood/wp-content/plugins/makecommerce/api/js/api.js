jQuery(document).ready(function($) {

    var env_select = $('#mk_api_type');
    var test_fields = ['mk_test_shop_id', 'mk_test_private_key', 'mk_test_public_key'];
    var live_fields = ['mk_shop_id', 'mk_private_key', 'mk_public_key'];

    env_select.on('change', function(){
        hideFields($(this).val());
    });

    hideFields(env_select.val());

    function hideFields(type) {
        var hide = type == 'live' ? test_fields : live_fields;
        var show = type == 'live' ? live_fields : test_fields;
        $.each(hide, function(i, elem) {
            $('#'+elem).closest('tr').hide();
        });
        $.each(show, function(i, elem) {
            $('#'+elem).closest('tr').show();
        });
    }
});