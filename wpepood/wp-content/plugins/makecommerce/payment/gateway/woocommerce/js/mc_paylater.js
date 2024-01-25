jQuery(document).ready(function($) {

    if (MC_PAYLATER.type == 'variable') {

        function paylater_block_changes( variation = null )
        { 
            if ( variation != null
                && variation != undefined
                && variation != ''
                && variation != 0 ) {
                $( "#mc_paylater_parent" ).load( location.protocol + '//' + location.host + location.pathname + "?paylater_variation_id=" + variation + " #mc_paylater_parent>*", function() {
                    $( '.mc_paylater_block' ).slideDown( 'slow' );
                } );
            }
        }

        paylater_block_changes( $( '.variation_id' ).val() );

        $( '.variation_id' ).change( function() {
            $( '.mc_paylater_block' ).slideUp( 'slow' );
            paylater_block_changes( $( '.variation_id' ).val() );
        } );
    }
});
