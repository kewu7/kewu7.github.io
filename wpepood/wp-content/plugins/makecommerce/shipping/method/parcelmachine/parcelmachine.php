<?php

namespace MakeCommerce\Shipping\Method;

/**
 * Smartpost parcelmachine shipping method.
 * Defines all Smartpost specific options.
 * 
 * @since 3.0.0
 */

use \MakeCommerce\Shipping\Method\ParcelMachine\Map;

abstract class ParcelMachine extends \MakeCommerce\Shipping\Method {

    use Map;

    public $type = "apt";
    public $identifier = "parcelmachine";
    public $identifierString;


    /**
     * Required function for all methods, initializes method type
     * 
     * @since 3.0.0
     */
    public function initialize() {

        $this->check_updates();

        //load all hooks/filters for method type
        $this->set_method_type_hooks();

        //set parcelmachine specific properties
        $this->prioritization = null;
        if ( isset( $this->settings['prioritization'] ) ) {
            $this->prioritization = $this->settings['prioritization'];
        }

        $this->short_office_names = null;
        if ( isset( $this->settings['short_office_names'] ) ) {
            $this->short_office_names = $this->settings['short_office_names'];
        }

        $this->use_white_apts = null;
        if ( isset( $this->settings['use_white_apts'] ) ) {
            $this->use_white_apts = $this->settings['use_white_apts'];
        }

        $this->searchable_parcelmachines = null;
        if ( isset( $this->settings['searchable_parcelmachines'] ) ) {
            $this->searchable_parcelmachines = $this->settings['searchable_parcelmachines'];
        }

        $this->mk_lpexpress_template = null;
        if ( isset( $this->settings['mk_lpexpress_template'] ) ) {
            $this->mk_lpexpress_template = $this->settings['mk_lpexpress_template'];
            if ( get_option( 'mk_lpexpress_template' ) === false ) {
                add_option( 'mk_lpexpress_template', $this->settings['mk_lpexpress_template'] );
            } else {
                update_option( 'mk_lpexpress_template', $this->settings['mk_lpexpress_template'] );
            }
        }

        //needed for identifier translation
        $this->identifierString = __( "parcelmachine", 'wc_makecommerce_domain' );
    }

    /**
     * Get method title
     * 
     * @since 3.0.0
     */
    public function return_method_title() {

        return $this->carrier_title . " " . __( "parcel machine (MC)", 'wc_makecommerce_domain' );
    }

    /**
     * Initialize method type specific checkout fields
     * 
     * @since 3.0.0
     */
    public function initialize_method_type_checkout_fields() {

        $this->form_fields['prioritization'] = array(
            'title'            => __('Prioritize', 'wc_makecommerce_domain'),
            'type'             => 'checkbox',
            'label'            => __('Bigger cities will be on top of list, others sorted alphabetically', 'wc_makecommerce_domain'),
            'default'          => 'yes'
        );
        
        $this->form_fields['short_office_names'] = array(
            'title'            => __('Short names', 'wc_makecommerce_domain'),
            'type'             => 'checkbox',
            'label'            => __('Display only parcel machine names, without addresses', 'wc_makecommerce_domain'),
            'default'          => 'no'
        );

        $this->form_fields['searchable_parcelmachines'] = array(
            'title'            => __('Search', 'wc_makecommerce_domain'),
            'type'             => 'checkbox',
            'label'            => __('Make parcel machine selection searchable', 'wc_makecommerce_domain'),
            'default'          => 'no'
        );
    }

    /**
     * Set method type specific hooks/filters
     * 
     * @since 3.0.0
     */
    public function set_method_type_hooks() {

        //add parcelmachines selectbox to checkout
        add_filter( 'woocommerce_review_order_after_shipping' , array( $this, 'add_parcelmachine_checkout_fields' ) );

        //add shipping metadata to order
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_parcelmachine_order_meta' ) );
    }

    /**
     * Set parcelmachine specific checkout fields
     * 
     * @since  3.0.0
     */
    public function add_parcelmachine_checkout_fields() {

        $this->order_country = WC()->customer->get_shipping_country();

        //get all parcelmachines
        $aptopts = [ 'use_white_apts' => $this->use_white_apts ];
        $machines = \MakeCommerce\Shipping::mk_get_machines( $this->ext, $this->order_country, $aptopts );

        //sort machines
        $machines = $this->sort_machines( $machines );

        // Create new class and placeholder for searchable parcel machines
        $placeholder = __( '-- select parcel machine --', 'wc_makecommerce_domain' );
        $searchable_class = ''; 
        if ( $this->searchable_parcelmachines === 'yes' ) {
            $searchable_class = ' parcel-machine-select-box-searchable'; 
            $placeholder = '';
        }

        $html = '
        <tr style="display: none;" class="parcel_machine_checkout parcel_machine_checkout_parcelmachine_' . mb_strtolower( $this->ext ) . '">
            <td colspan="2">
                <p class="form-row" id="' . esc_attr( $this->id ) . '_field">
                    <select class="select parcel-machine-select-box' . $searchable_class . '" name="' . esc_attr( $this->id ) . '" id="' . esc_attr( $this->id ) . '">
                        <option value="">' . $placeholder . '</option>
        ';

        $html .= $this->create_parcelmachine_html( $machines, $this->short_office_names );

        $html .= '
                    </select>
                </p>';

        if ( self::map_enabled() ) {
            $html .= '
            <div class="mc_pmmap_choose_button">
                <img class="mc_pmmap_pin" src="' . plugin_dir_url(__FILE__) . 'images/pin.svg' . '">
                <p>' . __( 'Choose machine from map', 'wc_makecommerce_domain' ) . '</p>
            </div>
			';
        }

        $html .= '
            </td>
        </tr>
        ';

        echo $html;
    }

    /**
     * Creates html for displaying parcel machines
     * 
     * @since 3.0.15
     */
    public static function create_parcelmachine_html( $machines, $short_names = 'no', $selected_machine_id = null ) {

        $output = '';

        //group machines by city and set name
        $city_machines = array();
        foreach ( $machines as $machine ) {
            if ( $short_names !== 'yes' ) {
                // Original name for map
                $machine["original_name"] = $machine["name"];
                $machine["name"] .= ' - ' . $machine['city'] . ', ' . $machine['address'];
            }

            $city_machines[$machine["city"]][] = $machine;
        }

        foreach ( $city_machines as $city=>$grouped_machines ) {
            $output .= '<optgroup label="' . $city . '">';

            foreach ( $grouped_machines as $machine ) {
                if ( isset($machine['x']) && isset($machine['y'])) {
                    $output .= '<option';
                    if ( isset( $machine["original_name"] ) ) {
                        $output .= ' name="'.esc_attr( $machine['original_name'] ).'"';
                    } else {
                        $output .= ' name="'.esc_attr( $machine['name'] ).'"';
                    }
                    $output .= ' x="'.esc_attr( $machine['x'] ).'"'
                    .' y="'.esc_attr( $machine['y'] ).'"'
                    .' carrier="'.esc_attr( $machine['carrier'] ).'"'
                    .' city="'.esc_attr( $machine['city'] ).'"'
                    .' address="'.esc_attr( $machine['address'] ).'"'
                    .' zip="'.esc_attr( $machine['zip'] ).'"'
                    .' commentet="'.esc_attr( $machine['commentEt'] ).'"'
                    .' commentlv="'.esc_attr( $machine['commentLv'] ).'"'
                    .' commentlt="'.esc_attr( $machine['commentLt'] ).'"'
                    .' commentfi="'.esc_attr( $machine['commentFi'] ).'"'
                    .' availability="'.esc_attr( $machine['availability'] ).'"'
                    .' value="'.esc_attr( $machine['carrier'].'||'.$machine['id'] ).'" '.( $selected_machine_id === $machine['id'] ? ' selected="selected"' : '' ).'>'.$machine["name"].'</option>';
                      
                } else {
                    $output .= '<option value="'.esc_attr( $machine['carrier'].'||'.$machine['id'] ).'" '.( $selected_machine_id === $machine['id'] ? ' selected="selected"' : '' ).'>'.$machine["name"].'</option>';
                }
            }

            $output .= '</optgroup>';
        }
        
        return $output;
    }

    /**
     * Adds metadata to order about shipping
     * 
     * @since 3.0.0
     */
    public function add_parcelmachine_order_meta( $order_id ) {

        $shipping_method = !empty( $_POST['shipping_method'] ) ? $_POST['shipping_method'] : false;

        if ( !empty($shipping_method[0] ) ) {
            $shipping_method_ext = explode( ':', $shipping_method[0] );
        }

        if ( $shipping_method_ext[0] === $this->id && !empty( $_POST[$this->id] ) ) {

            \MakeCommerce\Shipping::update_order_parcelmachine_meta( $_POST[$this->id], $order_id );
        }
    }

    /**
     * Convert old methods to shipping zones if current site wc_mc_version is older than one of this module
     * 
     * @since 3.0.0
     */
    private function check_updates() {

        if ( get_site_option('wc_mc_version', 0) < WC_MC_VERSION) {

            update_site_option('wc_mc_version', WC_MC_VERSION);

            error_log("Need to update from [" . get_site_option( 'wc_mc_version', 0 ) . "] to [" . WC_MC_VERSION . "]");

            if ( WC_MC_VERSION === 2.0 ) {

                // Check if omniva / smartpost is enabled and in which countries
                // Convert to shipping zone
                foreach ( array(
                    'parcelmachine_omniva' => 'MakeCommerce\Shipping\Method\ParcelMachine\Omniva', 
                    'parcelmachine_smartpost' => 'MakeCommerce\Shipping\Method\ParcelMachine\Smartpost', 
                    'parcelmachine_dpd' => 'MakeCommerce\Shipping\Method\ParcelMachine\DPD',
                    'parcelmachine_lp_express_lt' => 'MakeCommerce\Shipping\Method\ParcelMachine\LPExpress'

                    ) as $method_name => $method_class ) {

                    $parcel_machine_method = new $method_class();

                    if ( $parcel_machine_method->enable === 'yes' ) {

                        $zones = \WC_Shipping_Zones::get_zones();

                        //skip if there were no countries selected
                        if ( !empty( $parcel_machine_method->countries ) ) {
                            continue;
                        }

                        foreach ( $parcel_machine_method->countries as $ecountry ) {

                            $has_ecountry = false;

                            foreach ( $zones as $zone ) {
                                foreach ( $zone['zone_locations'] as $location ) {

                                    if ( $location->code === $ecountry ) {

                                        $has_ecountry = $zone['zone_id'];

                                        //skip complete class if a shippingzone with this name has alread been defined
                                        foreach ( $zone['shipping_methods'] as $method ) {
                                            if ( $method->id === $method_name ) {
                                                
                                                continue 4;
                                            }
                                        }
                                    }
                                }
                            }

                            //convert to shipping zones
                            if ( !$has_ecountry ) {

                                $zone = new \WC_Shipping_Zone();
                                $zone->set_zone_name( $ecountry );
                                $zone->add_location( $ecountry, 'country' );

                                $zone->save();
                            } else {
                                $zone = new \WC_Shipping_Zone( $has_ecountry );
                            }

                            $instance_id = $zone->add_shipping_method($method_name);
                            $transport_class = new $method_class($instance_id);

                            $price = !empty( $transport_class->settings['price_'.strtolower($ecountry)] ) ? $transport_class->settings['price_'.strtolower($ecountry)] : 0;

                            $free_shipping_min_amount = !empty( $transport_class->settings['free_shipping_min_amount_'.strtolower( $ecountry )] ) ? $transport_class->settings['free_shipping_min_amount_'.strtolower($ecountry)] : 0;
                            $transport_class->set_post_data( array( $transport_class->get_field_key( 'price' ) => $price, $transport_class->get_field_key( 'free_shipping_min_amount' ) => $free_shipping_min_amount ) );

                            $transport_class->process_admin_options();
                        }
                    }
                }
            }
        }
    }

    /**
     * Checks if there is a product that doesn't fit in parcelmachine
     * 
     * @since 3.0.0
     */
    private function fits_parcel_machine( $package ) {

        foreach ( $package['contents'] as $line ) {
            if ( get_post_meta( $line['product_id'], '_no_parcel_machine', true ) === 'yes' ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if shipping method is available.
     * In addition to parent is_available, also checks if package fits in parcel machine
     * 
     * @since 3.0.0
     */
    public function is_available( $package ) {

        if ( !$this->fits_parcel_machine( $package ) ) {
            return false;
        }

        return parent::is_available( $package );
    }

    /**
     * Sort machines (biggest cities first)
     * 
     * @since 3.0.0
     */
    private function sort_machines( $machines ) {

        if ( $this->prioritization === 'yes' ) {
            
            usort( $machines, function( $a, $b ) {

                $sortorder = array(
                    'tallinn', 'tartu linn','tartu', 'narva', 'pärnu linn','pärnu', 'viljandi', 'kohtla-järve', 'rakvere', 'maardu', 'sillamäe', 'kuressaare',
                    'helsinki', 'espoo', 'tampere', 'vantaa', 'oulu', 'turku', 'jüväskülä', 'lahti', 'kuopio', 'kouvola',
                    'rīga','riga', 'daugavpils', 'liepāja','liepaja', 'jelgava', 'jūrmala','jurmala', 'ventspils', 'rezekne', 'valmiera', 'jekabpils',
                    'vilnius', 'kaunas', 'klaipeda', 'siauliai', 'panevezys', 'alytus', 'mariampole', 'mazeikiai', 'jonava', 'utena'
                );

                $acity = mb_strtolower($a['city']);
                $bcity = mb_strtolower($b['city']);
                
                if ( !$acity ) {
                    $acity = 'xxxxxxx';
                }

                if ( !$bcity ) {
                    $bcity = 'xxxxxxx';
                }

                $aidx = array_search( $acity, $sortorder );
                $bidx = array_search ($bcity, $sortorder );

                if ( $aidx !== false ) {
                    $acity = str_pad( $aidx, 4, "0", STR_PAD_LEFT ) . '-' . $acity;
                }

                if ( $bidx !== false ) {
                    $bcity = str_pad( $bidx, 4, "0", STR_PAD_LEFT ) . '-' . $bcity;
                }

                $acity .= '-' . mb_strtolower($a['name']);
                $bcity .= '-' . mb_strtolower($b['name']);
                
                return $acity < $bcity ? -1 : 1;
            } );
        }

        return $machines;
    }

    /**
     * Generates HTML for checking MakeCommerce shipment mediation availability
     *
     * @since 3.0.0
     */
    public function generate_verify_feature_swc_html( $key, $data ) {

        $field = $this->get_field_key( $key );
        $defaults = [
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => []
        ];

        $data = wp_parse_args( $data, $defaults );

        \MakeCommerce::mc_enqueue_script(
            'MC_TRANSPORT_MEDIATION_VERIFICATION',
            dirname( __FILE__ ) . '/js/transport_mediation_verification.js',
            [
                'site_url' => get_site_url(),
                'enabled' => __( 'The transport mediation service is already enabled for your shop. You are good to go!', 'wc_makecommerce_domain' ),
                'not_enabled' => __('The transport mediation service is NOT ENABLED enabled for your shop. Please go to merchant portal to activate it!', 'wc_makecommerce_domain' ),
                'error' => __( 'There was an error with your request. Please try again.', 'wc_makecommerce_domain' )
            ],
            [ 'jquery' ]
        );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                <?php echo $this->get_tooltip_html( $data ); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php echo wp_kses_post( $data['title'] ); ?></span>
                    </legend>
                    <input id="verify_feature_swc" class="button <?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $data['placeholder'] ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> />
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }
    
    /**
     * Force creating a function for initializing method specific form fields
     * 
     * @since 3.0.0
     */
    abstract public function initialize_method_form_fields();
}
