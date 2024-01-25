<?php

namespace MakeCommerce\Payment\Gateway\WooCommerce;

trait Banklink {

    /**
     * Reloads banklinks
     * 
     * @since 3.0.0
     */
    public function mc_banklinks_reload( $force = false) {

        if ( $force || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {

            $updated = $this->banklinks_update();

            if ( $force ) {
                $this->methods->load_methods();

                return $updated;
            }
            
            if ( $updated ) {
                
                if ( !empty( $this->methods->get_payment_methods() ) ) {
                    wp_send_json( array( 'success' => 1, 'data' => __( 'Update successfully completed!', 'wc_makecommerce_domain' ) ) );
                } else {
                    wp_send_json( array( 'success' => 1, 'data' => __( 'Update successfully completed, but no payment methods were returned. Please contact us to solve the issue at support@maksekeskus.ee', 'wc_makecommerce_domain' ) ) );
                }

                exit;
            }
            
            wp_send_json( array( 'success' => 0, 'data' => __( 'There was an error with your update. Please try again.', 'wc_makecommerce_domain' ) ) );

            exit; 
        }

        die();
    }

    /**
     * Updates banklinks database
     * 
     * @since 3.0.9
     */
    public function banklinks_update() {

        $methods = self::get_payment_methods();
        $updated = self::insert_payment_methods( $methods );
        
        return $updated;
    }


    /**
     * Inserts payment methods into DB
     * 
     * @since 3.0.12
     */
    public static function insert_payment_methods( $methods ) {

        if ( !is_object( $methods ) ) {
            return false;
        }

        global $wpdb;

        $tableName = $wpdb->prefix . MAKECOMMERCE_TABLENAME;

        $wpdb->query( 'TRUNCATE TABLE `'.$tableName.'`' );
        
        if ( isset( $methods->banklinks ) ) {
            foreach( $methods->banklinks as $method ) {
                $wpdb->insert( $tableName, array( 'type' => 'banklink', 'country' => $method->country, 'name' => $method->name, 'url' => $method->url, 'logo_url' => $method->logo_url, 'min_amount' => $method->min_amount ?? NULL, 'max_amount' => $method->max_amount ?? NULL, 'channel' => $method->channel, 'display_name' => $method->display_name ) );
            }
        }
        
        if ( isset( $methods->cards ) ) {
            foreach( $methods->cards as $method ) {
                $wpdb->insert( $tableName, array( 'type' => 'card', 'name' => $method->name, 'logo_url' => $method->logo_url, 'min_amount' => $method->min_amount ?? NULL, 'max_amount' => $method->max_amount ?? NULL, 'channel' => $method->channel, 'display_name' => $method->display_name ) );
            }
        }

        if ( isset( $methods->other ) ) {
            foreach( $methods->other as $method ) {
                $wpdb->insert( $tableName, array( 'type' => 'other', 'country' => $method->country, 'name' => $method->name, 'url' => $method->url, 'logo_url' => $method->logo_url, 'min_amount' => $method->min_amount ?? NULL, 'max_amount' => $method->max_amount ?? NULL, 'channel' => $method->channel, 'display_name' => $method->display_name ) );
            }
        }

        if ( isset( $methods->payLater ) ) {
            foreach( $methods->payLater as $method ) {
                $wpdb->insert( $tableName, array( 'type' => 'payLater', 'country' => $method->country, 'name' => $method->name, 'url' => $method->url, 'logo_url' => $method->logo_url, 'min_amount' => $method->min_amount ?? NULL, 'max_amount' => $method->max_amount ?? NULL, 'channel' => $method->channel, 'display_name' => $method->display_name ) );
            }
        }

        // Check if methods from API and table are the same
        $updated = false;
        $db_methods = $wpdb->get_results( "SELECT `name`,`country` FROM $tableName" );

        // Gather all payment methods from API by name and country
        $all_methods = [];
        foreach( $methods as $method_type ) {
            foreach( $method_type as $method ) {
                if ( isset( $method->country )) {
                    $api_method_name = $method->name . $method->country;
                } else {
                    $api_method_name = $method->name;
                }

                array_push( $all_methods, $api_method_name );
            }
        }
            
        // Check if db contains correct methods
        foreach( $db_methods as $method ) {
            $db_method_name = $method->name . $method->country;
            if ( in_array( $db_method_name, $all_methods ) ) {
                $key = array_search( $db_method_name, $all_methods );
                unset( $all_methods[$key] );
            } else {
                // Methods don't match
                break;
            }
        }

        // Check if all methods were included
        if ( count( $all_methods ) == 0 ) {
            $updated = true;
        }

        if ( isset( $shopConfig ) && strlen( $shopConfig->name ) ) {
            update_option( 'mc_shop_name', $shopConfig->name );
        }

        if ( $updated ) {
            update_option( 'mc_banklinks_api_type', get_option( 'mk_api_type', false) );
        }

        return $updated;
    }


    /**
     * Gets payment methods
     * 
     * @since 3.0.12
     */
    public static function get_payment_methods() {

        $MK = \MakeCommerce::get_api();

        if ( !$MK ) {
            return false;
        }

        try {
            $shopConfig = $MK->getShopConfig( \MakeCommerce::config_request_parameters( MAKECOMMERCE_PLUGIN_ID.' '.MAKECOMMERCE_VERSION ) );
            $methods = $shopConfig->paymentMethods;	
        } catch ( \Exception $e ) {
            error_log( print_r( $e, 1 ) );
            return false;
        }

        return $methods;
    }


    /**
     * Creates banklinks reload button
     * 
     * @since 3.0.0
     */
    public function generate_mc_banklinks_reload_html( $key, $data ) {

        $field    = $this->get_field_key( $key );
        $defaults = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array()
        );
    
        $data = wp_parse_args( $data, $defaults );
        
        \MakeCommerce::mc_enqueue_script( 
            'MC_BANKLINKS_RELOAD', 
            dirname( __FILE__ ) . '/js/mc_banklinks_reload.js', 
            [
                'site_url' => get_site_url(),
                'error' => __( 'There was an error with your update. Please try again.', 'wc_makecommerce_domain' )
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
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <input id="mc_banklinks_reload" class="button <?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $data['description'] ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> />
                </fieldset>
            </td>
        </tr>
        <?php
    
        return ob_get_clean();
    }
}
