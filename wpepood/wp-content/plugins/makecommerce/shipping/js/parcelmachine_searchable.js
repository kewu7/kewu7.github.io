jQuery(function(){
	
	jQuery(document).on('updated_checkout', function() {
        searchable_selectbox();
	});

    jQuery(document).ready(function() {
        searchable_selectbox();
    });

    function searchable_selectbox() {

        $selectBox = jQuery('.parcel-machine-select-box-searchable');

        $json = {
            placeholder: MC_PARCELMACHINE_SEARCHABLE_JS[0]['placeholder'],
            width: '100%',
            dropdownAutoWidth: true,
            containerCssClass: 'parcel-machine-select-box',
            dropdownCssClass: 'makecommerce-selectbox-dropdown'
        }

        //It seems that some plugins or themes forcibly remove selectWoo
        //Check if selectWoo 
        try {
            if (typeof $selectBox.selectWoo === "function") { 
                $selectBox.selectWoo($json);
                focus($selectBox);
                
            } else {
                //selectWoo didnt exist, try select2 or do nothing
                if (typeof $selectBox.select2 === "function") { 
                    $selectBox.select2($json);
                    focus($selectBox);
                }
            }

            function focus($selectBox) {
                $selectBox.on('select2:open', function (e) {
                    //this is ran using timeout because otherwise another event defocuses the element, most likely the click itself.
                    $searchField = jQuery('.select2-container--open').find('input.select2-search__field')[0];
                    setTimeout(function() { jQuery($searchField).focus(); }, 1);
                });
            }
        } catch {}
    }
});
