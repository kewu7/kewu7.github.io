function init_mc_cc_form() {
    
    cc_init_json = {
        'key' : MC_CC_INIT.key, 
        'transaction' : MC_CC_INIT.transaction,
        'amount' : MC_CC_INIT.amount, 
        'currency' : MC_CC_INIT.currency, 
        'email' : MC_CC_INIT.email, 
        'clientName' : MC_CC_INIT.clientname, 
        'locale' : MC_CC_INIT.locale, 
        'name' : MC_CC_INIT.name, 
        'description' : MC_CC_INIT.description, 
        'completed' : MC_CC_INIT.completed,
        'openOnLoad': MC_CC_INIT.openonload,
        'currency': MC_CC_INIT.currency,
        'backdropClose': MC_CC_INIT.backdropclose
    }

    if ( MC_CC_INIT.hasOwnProperty( 'recurringrequired' ) ) {

        cc_init_json["recurringRequired"] = MC_CC_INIT.recurringrequired;
        cc_init_json["recurringTitle"] = MC_CC_INIT.recurringtitle;
        cc_init_json["recurringDescription"] = MC_CC_INIT.recurringdescription;
        cc_init_json["recurringConfirmation"] = MC_CC_INIT.recurringconfirmation;
    }

    window.Maksekeskus.Checkout.initialize( cc_init_json );
}

init_mc_cc_form();