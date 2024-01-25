jQuery(document).ready(function($) {

	var env_select = $('[id*="_use_mk_contract"]');
	var contract_fields = [
		'woocommerce_parcelmachine_omniva_service_user',
		'woocommerce_parcelmachine_omniva_service_password',
		'woocommerce_parcelmachine_omniva_service_carrier',
		'woocommerce_parcelmachine_dpd_service_user',
		'woocommerce_parcelmachine_dpd_service_password',
		'woocommerce_parcelmachine_dpd_service_carrier',
		'woocommerce_parcelmachine_dpd_api_key'
	];
	var self_fields = ['verify_feature_swc'];
	
	env_select.on('change', function(){
		hideFields($(this).val());
	});

	hideFields(env_select.val());

	function hideFields(type) {

		var hide = type == '1' ? contract_fields : self_fields;
		var show = type == '0' ? contract_fields : self_fields;

		$.each(hide, function(i, elem) {
			$('#'+elem).closest('tr').addClass('fhidden');
		});

		$.each(show, function(i, elem) {
			$('#'+elem).closest('tr').removeClass('fhidden');
		});
	}
});