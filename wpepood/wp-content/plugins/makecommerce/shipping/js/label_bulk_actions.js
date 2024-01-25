jQuery(function() {

    if ( typeof MC_LABEL_BULK_ACTIONS.error !== 'undefined' ) {
        alert(MC_LABEL_BULK_ACTIONS.error);
    }

    if (typeof MC_LABEL_BULK_ACTIONS.hpos !== 'undefined' && typeof MC_LABEL_BULK_ACTIONS.pdf !== 'undefined') {
        window.open( MC_LABEL_BULK_ACTIONS.pdf, 'pdf' );
        return;
    }
    jQuery('<option>').val('parcel_machine_labels').text( MC_LABEL_BULK_ACTIONS.shipments_text ).appendTo('select[name="action"]');
    jQuery('<option>').val('parcel_machine_labels').text( MC_LABEL_BULK_ACTIONS.shipments_text ).appendTo('select[name="action2"]');
    jQuery('<option>').val('parcel_machine_print_labels').text( MC_LABEL_BULK_ACTIONS.labels_text ).appendTo('select[name="action"]');
    jQuery('<option>').val('parcel_machine_print_labels').text( MC_LABEL_BULK_ACTIONS.labels_text ).appendTo('select[name="action2"]');

    if ( typeof MC_LABEL_BULK_ACTIONS.pdf !== 'undefined' ) {
        window.open( MC_LABEL_BULK_ACTIONS.pdf, 'pdf' );
    }
});