jQuery(function(){
	
	jQuery(document).on('updated_checkout', function() {

		jQuery(".parcel_machine_checkout").css('display', 'none');

		jQuery('.shipping_method:checked, .shipping_method[type=hidden]').each(function() {
			var method = jQuery(this).val(), display;
			if (method.indexOf(':') > -1) {
				var tmp = method.split(':');
				method = tmp[0];
			}
			jQuery(".parcel_machine_checkout_"+method).not(':first').remove();
			jQuery(".parcel_machine_checkout_"+method).css('display', 'table-row');
		});

		adjust_select_box_width();
	});

	jQuery(window).resize(function() {
		adjust_select_box_width();
	});
	
	function adjust_select_box_width() {
		// Get widths of the content, parcel_machine_checkout row
		var select_box_width;
		var content_width = jQuery('.woocommerce-checkout-review-order').width();
		var checkout_width = jQuery('.woocommerce-checkout').width();
		var parcel_row = jQuery('.parcel_machine_checkout');
		// Get the parent table class
		var default_layout = jQuery(parcel_row).closest('table').attr('class') == 'shop_table woocommerce-checkout-review-order-table';
		// Get the width of the correct select box
		parcel_row.find('.parcel-machine-select-box').each(function() {
			select_box_width = jQuery(this).width() > 1 ? jQuery(this).width() : select_box_width;
		});

		// If it is not the default table class, the theme is customized and no changes will be made
		if (default_layout && content_width !== 'undefined' && checkout_width !== 'undefined') {
			// Keep the searchable selectbox within size limits
			if (content_width > checkout_width / 2){
				jQuery('.parcel_machine_checkout > td').css('max-width', (content_width / 2 - 20) +'px');
			} else {
				jQuery('.parcel_machine_checkout > td').css('max-width', (content_width - 20) +'px');
			}
			// If the size of the selectbox is bigger than X pixels and less than 2 times as small as the content, add a cell before it
			if (select_box_width > 500 && content_width / select_box_width < 2) {
				// Unless there already exist 2 cells, add an empty one before select_box
				if (parcel_row.find('.padding_cell').length < 1) {
					parcel_row.prepend('<td class="padding_cell"></td>');
				}

				// Loop through children and change colspan of select_box cell to 1
				parcel_row.children().each(function() {
					if (typeof(jQuery(this).attr('colspan')) !== 'undefined') {
						jQuery(this).attr('colspan', 1);
					}
				});
			} else if (content_width <= 500 && parcel_row.find('.padding_cell').length > 0) {
				// If the row is smaller than the min amount and there's a padding cell, remove it
				parcel_row.children().each(function() {
					// Change colspan back to 2
					if (typeof(jQuery(this).attr('colspan')) !== 'undefined') {
						jQuery(this).attr('colspan', 2);
					} else {
						jQuery(this).remove();
					}
				});
			}
		}
	}
});
