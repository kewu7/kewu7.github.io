let path = MC_PARCELMACHINE_MAP_JS.path;
let mapInitUrl = MC_PARCELMACHINE_MAP_JS.map_init_url;
let locale = MC_PARCELMACHINE_MAP_JS.locale.toLowerCase();
let country;
let coordinates = [];
let markersArray = [];
let machines = [];
let carriers = [];
let infowindow = null;

let urls = {};
urls['dpd'] = 'https://static.maksekeskus.ee/img/shipping/dpd.svg';
urls['smartpost'] = 'https://static.maksekeskus.ee/img/shipping/itella_smartpost.svg';
urls['lp_express_lt'] = 'https://static.maksekeskus.ee/img/shipping/lp_express.svg';
urls['omniva'] = 'https://static.maksekeskus.ee/img/shipping/omniva.svg';

let map;
// Default map location
let mapOptions = {
    zoom: 11,
    center: { lat: 59.436962, lng: 24.753574 }
};

/**
 * Shows Makecommerce parcel machine map
 */
async function showMCPMMap() {

    // Get address coordinates
    let latitude = coordinates[0];
    let longitude = coordinates[1];

    if (latitude && longitude) {
        mapOptions = {
            zoom: 13,
            center: { lat: latitude, lng: longitude }
        }
    } else {
        // Default map locations for different countries
        switch(country) {
            case "EE":
                mapOptions = {
                    zoom: 11,
                    center: { lat: 59.436962, lng: 24.753574 }
                };
                break;
            case "LV":
                mapOptions = {
                    zoom: 11,
                    center: { lat: 56.946285, lng: 24.105078 }
                };
                break;
            case "LT":
                mapOptions = {
                    zoom: 11,
                    center: { lat: 54.687157, lng: 25.279652 }
                };
                break;
            case "FI":
                mapOptions = {
                    zoom: 11,
                    center: { lat: 60.192059, lng: 24.945831 }
                };
                break;
        }          
    }

    // Map not yet generated
    if (!jQuery( "#MakeCommerceParcelMap" ).length) {
        jQuery( "body" ).append( 
            "<div class='mcpmmap_overlay'><div class='mcpmmap_overlay-frame'><div id='MakeCommerceParcelMap' class='mcpmmap_overlay-content'></div></div></div>" 
        );
        jQuery.getScript(mapInitUrl);
        jQuery( "body" ).css("overflow", "hidden");
        addButtons(carriers);
    } else {
        // Map already generated and exists, just show it again
        initMCPMMap();
        jQuery('.mcpmmap_overlay').show();
        jQuery( "body" ).css("overflow", "hidden");
    }
    // When showing map, show the chosen parcel machine carrier
    enableCorrectCheckbox();
}

/**
 * Adds buttons and checkboxes to map view
 *
 * @param carriers The parcel machine carriers that are enabled in checkout
 */
function addButtons( carriers ) {
    jQuery('<div class="mc_map_close"><img id ="mc_map_close_button" src="' + path +'/map_close.svg"/></div>').appendTo('.mcpmmap_overlay-frame');
    jQuery('<div class="mc_map_carriers"></div>').appendTo('.mcpmmap_overlay-frame');

    alterCheckBoxes(carriers);

    jQuery('#mc_map_close_button').on('click', function() {
        // Close the map
        jQuery('.mcpmmap_overlay').hide();
        jQuery( "body" ).css("overflow", "auto");
    })
}

/**
 * Adds the correct checkboxes to the map view
 *
 * @param carriers The parcel machine carriers that are enabled in checkout
 */
function alterCheckBoxes(carriers) {
    // Remove all old checkboxes
    jQuery('.mc_map_carriers').empty();

    // Add new checkboxes
    jQuery.each(carriers, function( key, value ) {

        let name = "";
        //LP Express
        if (value.includes('_')) {
            let names = value.split('_');
            name = names[0].toUpperCase();
            
            let firstLetter = names[1].charAt(0).toUpperCase();
            names[1] = firstLetter + names[1].slice(1);
            name += " " + names[1];
        }
        //DPD
        else if (value.length < 4) {
            name = value.toUpperCase();
        } else {
            // Omniva, Smartpost
            let firstLetter = value.charAt(0).toUpperCase();
            name = firstLetter + value.slice(1);
        }

        let element = jQuery('#shipping_method').find('[for*="parcelmachine_' + value + '"]').children('.amount');
        let amount = jQuery(element).text();

        let html = '<div class="mc_map_carrier"><label class="mc_form_control" for="mc_map_' + value + '_checkbox">'
            + '<input type="checkbox" class="mc_map_carrier_checkbox" id="mc_map_' + value + '_checkbox" name="mc_map_' + value + '_checkbox" value="' + value + '"/>'
            + '<span class="checkmark"></span>'
            +  name + ' (' + amount + ')</label></div>';
        jQuery(html).appendTo('.mc_map_carriers');
    });

    // When carrier checkboxes are changed, create markers based on new values
    jQuery('.mc_map_carrier_checkbox').change(function() {
        let enabledCarriers = [];
        // Loop all the checkboxes to add correct ones on the map
        jQuery('.mc_map_carrier_checkbox').each(function() {
            if (jQuery(this).is(':checked')) {
                // Gather all the enabled carriers
                enabledCarriers.push(jQuery(this).attr('value'));
            }
        });

        createMarkers(enabledCarriers, machines, path);
    });
}

/**
 * Enables the desired carrier in map view based on checkout selection
 */
function enableCorrectCheckbox() {
    jQuery('#shipping_method').find('input').each(function() {
        if (jQuery(this).attr('checked')) {
            let value = jQuery(this).attr('value');
            jQuery('.mc_map_carrier_checkbox').prop('checked', false);
            jQuery('.mc_map_carrier_checkbox').each(function() {

                if (value.includes(jQuery(this).attr('value'))) {
                    // Found the selected method, enable it in map view
                    jQuery(this).prop('checked', true);
                }
            });
        }
    });
}

/**
 * Callback function for map initialization
 */
function initMCPMMap() {

    // Create the variables
    let carrier = jQuery('.parcel_machine_checkout:visible').attr('class');
    // Get last value of carrier
    carrier = carrier.split('parcel_machine_checkout_parcelmachine_').slice(-1)[0];

    initializeMap(mapOptions);
    createMarkers([carrier], machines, path);
}

/**
 * Provides the possibility to choose machines from the map
 *
 * @param element The element that was chosen, related to the parcel machine
 */
function chooseMachine(element) {
    // Close the map
    jQuery('.mcpmmap_overlay').hide();
    jQuery( "body" ).css("overflow", "auto");
    // Select the correct carrier when machine is chosen
    let carrier = jQuery(element).attr('carrier');

    jQuery('#shipping_method').find('input[value*=parcelmachine_' + carrier + ']').prop('checked', true).trigger('change');

    let id = jQuery(element).attr('machine_id').split('||')[1];

    jQuery(document).on('updated_checkout', function() {
        jQuery('.parcel-machine-select-box:visible option[value*=' + id + ']').prop('selected', true).trigger('change');
    });
}

/**
 * Initializes the Google Map
 *
 * @param options Custom settings for the map
 */
function initializeMap(options) {
    // Initialize the map using the provided options
    map = new google.maps.Map(document.getElementById("MakeCommerceParcelMap"), options);
}

/**
 * Creates markers on the Google Map
 *
 * @param carriers The enabled parcel machine carriers
 * @param machines All the possible machines that could be added to the map
 * @param path The path to the correct resource files
 */
function createMarkers(carriers, machines, path) {

    removeMarkers(carriers);

    for (let i = 0; i < machines.length; ++i) {
        if (!carriers.includes(machines[i].carrier)) {
            continue;
        }

        var latLng = new google.maps.LatLng(machines[i].y, machines[i].x);

        var marker = new google.maps.Marker({
            position: latLng,
            icon: path + '/' + machines[i].carrier + '.svg',
            title: machines[i].name,
            carrier: machines[i].carrier
        });

        marker.setMap(map);
        markersArray.push(marker);

        bindInfoWindow(marker, map, machines[i]);
    }
}

/**
 * Removes markers from the Google Map
 *
 * @param carriers The enabled parcel machine carriers which will not have markers removed
 */
function removeMarkers(carriers) {
    markersArrayTemp = [];

    for (let i = 0; i < markersArray.length; i++) {
        if (!carriers.includes(markersArray[i].carrier)) {
            // Not an enabaled carrier
            markersArray[i].setMap(null);
        } else {
            // Enabled carrier, do not remove
            markersArrayTemp.push(markersArray[i]);
        }
    }
    // Set new array of markers
    markersArray = markersArrayTemp;
}

/**
 * Binds infowindows to the markers and adds even listeners
 *
 * @param marker The marker to add the infowindow to
 * @param map Map that the marker is added to
 * @param infowindow Infowindow object to modify and add to marker
 * @param machine Parcel machine with all the necessary data like name, id, carrier etc
 */
function bindInfoWindow(marker, map, machine) {
    google.maps.event.addListener(marker, 'click', function() {
        if (infowindow) {
            infowindow.close();
        }

        infowindow = new google.maps.InfoWindow();

        let url = urls[machine.carrier];

        let html = '<div class="mc_map_infowindow">' +
            '<div class="mc_map_info_div"><img class="mc_map_machine_logo" src="' + url + '"></div>' +
            '<div class="mc_map_info_div"><p class="mc_map_machine_name">' + machine.name + '</p></div>' +
            '<div class="mc_map_info_div"><p>' + machine.city + ', ' + machine.address + '</p></div>';

        if (machine.comment !== '') {
            html += '<div class="mc_map_info_div"><p class="additional-machine-info">' + machine.comment + '</p></div>' +
                    '<div class="mc_map_info_div"><p class="additional-machine-info">' + machine.availability + '</p></div>';
        }

        html += '<div><button class="mc_map_machine_choose" machine_id="' + machine.value + '" carrier="' + machine.carrier + '" onclick="chooseMachine(this)">Choose</button></div>' +
            '</div>';

        infowindow.setContent(html);
        infowindow.open(map, marker);
        jQuery('.gm-style-iw-d').css("max-height", "unset");
    });
}

/**
 * Updates the provided carriers and parcel machines when the checkout is updated.
 */
function updateMapMachines() {
    let newMachines = [];
    let newCarriers = [];
    // Loop through all the possible parcel machine carriers
    jQuery('#shipping_method').find('[value*="parcelmachine_"]').each(function() {
        var method = jQuery(this).val();

        if (method.indexOf(':') > -1) {
            var tmp = method.split(':');
            method = tmp[0];
        }

        if (method.indexOf('parcelmachine_') > -1) {
            var tmp = method.split('parcelmachine_');
            carrier_name = tmp[1];
            newCarriers.push(carrier_name);
        } else {
            newCarriers.push(method);
        }
        // Loop through all the possible machines
        jQuery(".parcel_machine_checkout_"+method).find('option').each(function() {
            let value = jQuery(this).attr('value');
            // Avoid duplicates since there may be duplicate selectboxes
            if (!newMachines.includes(value) && value !== "") {
                // Check that the option has coordinates, can't add to map otherwise
                if (jQuery(this).attr('x') !== "" && jQuery(this).attr('y') !== "") {
                    let availability = jQuery(this).attr('availability');
                    let comment = jQuery(this).attr('comment'+locale);
                    if (comment === '' || comment === undefined) {
                        availability = comment = '';
                    } else {
                        comment = comment.charAt(0).toUpperCase() + comment.slice(1);
                    }
                    newMachines.push ({
                        'address': jQuery(this).attr('address'),
                        'carrier': jQuery(this).attr('carrier'),
                        'city': jQuery(this).attr('city'),
                        'name': jQuery(this).attr('name'),
                        'value': jQuery(this).attr('value'),
                        'x': jQuery(this).attr('x'),
                        'y': jQuery(this).attr('y'),
                        'zip': jQuery(this).attr('zip'),
                        'comment': comment,
                        'availability': availability
                    });
                }
            }
        });
    });
    machines = newMachines;
    carriers = newCarriers;
    alterCheckBoxes(carriers);
}

jQuery(document).ready(function() {
    // Whenever the checkout is updated
    jQuery(document).on('updated_checkout', function() {
        updateMapMachines();

        jQuery.ajax({
            url: MC_PARCELMACHINE_MAP_JS.site_url + '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: 'action=update_map_center',
            success: function (data) {
                // Update the URL
                if (data.coordinates) {
                    coordinates = data.coordinates;
                }
                // Update country
                if (data.country) {
                    country = data.country;
                }
            },
        });
    });

    jQuery(document).on('click', '.mc_pmmap_choose_button', function() {
        showMCPMMap();
    });
});
