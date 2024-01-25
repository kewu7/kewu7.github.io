function makecommerce_cc_complete(data)
{
    if (data.paymentToken) {
        
        jQuery('div.mc-processing-message').show();

        var submitform = jQuery('<form action="' + MC_CC_COMPLETE.payment_return_url + '" method="POST" style="display: none;"><input type="submit"/><input type="hidden" name="transaction" value="' + MC_CC_COMPLETE.transaction_id + '" /></form>');
        
        for (var key in data) {
            submitform.append(jQuery('<input type="hidden" name="' + key + '" />').val(data[key])); 
        }

        jQuery('body').append(submitform);

        submitform.submit();
    }
}