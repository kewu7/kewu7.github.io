jQuery(document).ready(function($) {

    var data = {
        'action': 'print_pml',
        'id': MC_LABEL_BUTTON.post_id
    };

    var loading = $('.mc_loading');

    $('#print_parcel_machine_label').click(function(e){

        e.preventDefault();
        var button = $(this);
        button.addClass('disabled');
        loading.show();

        $.post(ajaxurl, data, function(response) {
            button.removeClass('disabled');
            loading.hide();
            
            if ( response == "" ) { //some error has occured
                alert( MC_LABEL_BUTTON.error );
            } else {
                window.open( response, 'pdf' );
            }
        });
    });
});