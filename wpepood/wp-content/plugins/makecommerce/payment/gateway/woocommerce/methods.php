<?php

namespace MakeCommerce\Payment\Gateway\WooCommerce;

class Methods {

    public $banklinks = array();
    public $banklinks_grouped = array();
    public $cards = array();
    public $paylater = array();
    public $paylater_grouped = array();

    private $id;
    private $init;
    private $settings;

    /**
     * Construct payment methods
     * 
     * @since 3.0.0
     */
    public function __construct( $id, $init, $settings ) {

        $this->id = $id;
        $this->init = $init;
        $this->settings = $settings;

        $this->load_methods();
    }

    /**
     * Loads all payment methods from the database
     * 
     * @since 3.0.0
     */
    public function load_methods() {

        global $wpdb;

        $manual_renewals = false;
        $has_subscriptions = false;
        if ( class_exists( '\WC_Subscriptions_Cart' ) ) {
            if ( \WC_Subscriptions_Cart::cart_contains_subscription() ) {
                $has_subscriptions = true;

                if ( class_exists( '\WC_Subscriptions_Admin' ) 
                    && get_option( \WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no' ) === 'yes'
                    && get_option( \WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) === 'yes'
                ) {
                    $manual_renewals = true;
                }
            }
        }

        $methods = $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . MAKECOMMERCE_TABLENAME );
        
        if ( count( $methods ) ) {

            if ( is_admin() && $this->init == true && get_option( 'mc_banklinks_api_type' ) != get_option( 'mk_api_type', false ) ) {
                add_action( 'admin_notices', array( $this, 'banklinks_list_type_notice' ) );
            }

            foreach ( $methods as $method ) {
                if ( $method->type == 'banklink'|| $method->type == 'other' ) {
                    # If manual renewals are enabled, allow banklinks
                    if ( !$has_subscriptions || $manual_renewals ) {
                        $banklinks[] = $banklinks_grouped[$method->country][] = $method;
                    }
                } elseif ( $method->type == 'card' ) {
                    $cards[] = $method;
                } elseif( $method->type == 'payLater' ) {
                    if ( !$has_subscriptions ) {
                        $paylater[] = $paylater_grouped[$method->country][] = $method;
                    }
                }
            }
            
            if ( isset( $this->settings['ui_chorder'] ) ) {

                if ( (!$has_subscriptions || $manual_renewals) && isset( $banklinks ) && isset( $banklinks_grouped ) ) {
                    usort( $banklinks, array( $this, 'sort_banklinks' ) );
                    
                    foreach( $banklinks_grouped as &$country ) {
                        usort( $country, array( $this, 'sort_banklinks' ) );
                    }
                }
            }

            if ( !$has_subscriptions ) {

                if ( isset( $paylater ) ) {
                    $this->paylater = $paylater;
                }

                if ( isset( $paylater_grouped ) ) {
                    $this->paylater_grouped = $paylater_grouped;
                }
            }

            # Only when the order does not contain subscriptions or has manual renewals enabled
            if ( !$has_subscriptions || $manual_renewals) {
                if ( isset( $banklinks ) ) {
                    $this->banklinks = $banklinks;
                }

                if ( isset( $banklinks_grouped ) ) {
                    $this->banklinks_grouped = $banklinks_grouped;
                }
            }

            if ( isset( $cards ) ) {
                $this->cards = array_reverse( $cards );
            }

            if ( is_admin() ) {
                remove_action( 'admin_notices', array( $this, 'empty_banklinks_notice' ), 30 );
            }

        } elseif ( is_admin() && $this->init == true ) {

            add_action( 'admin_notices', array( $this, 'empty_banklinks_notice' ), 30 );
        }
    }
    
    /**
     * Banklink sorting function
     * 
     * @since 3.0.0
     */
    public function sort_banklinks( $a, $b ) {

        $order = array_map( 'trim', explode( ",", $this->settings['ui_chorder'] ) );
        
        $posA = array_search( $a->name, $order );
        $posB = array_search( $b->name, $order );
        
        if ( $posA === $posB ) {
            return $a->id > $b->id ? 1 : -1;
        }

        if ( $posA === FALSE ) {
            return 1;
        }

        if ( $posB === FALSE ) {
            return -1;
        }
        
        return $posA > $posB ? 1 : -1;
    }

    /**
     * Notice for admin that banklinks are empty
     * 
     * @since 3.0.0
     */
    public function empty_banklinks_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php echo __('The payment methods list for MakeCommerce payment module is empty.', 'wc_makecommerce_domain'); ?>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=makecommerce'); ?>"><?php echo __('Go to the settings to update them', 'wc_makecommerce_domain'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Notice for admin that payment methods have been loaded for a different environment
     * 
     * @since 3.0.0
     */
    public function banklinks_list_type_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php echo __('You have changed the environment for MakeCommerce payment module. The payment methods list has been loaded for a different environment.', 'wc_makecommerce_domain'); ?>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=makecommerce'); ?>"><?php echo __('Go to the settings to update them', 'wc_makecommerce_domain'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Returns customers default country if they are logged in
     * if not, returns default country by selected language
     * 
     * @since 3.0.0
     */
    private function get_default_country() {

        global $woocommerce;

        if ( $woocommerce->customer ) {

            $customerCountry = strtolower( $woocommerce->customer->get_shipping_country() );
            if ( array_key_exists( $customerCountry, $this->banklinks_grouped ) ) {
                return $customerCountry;
            } else {
                return 'other';
            }
        }
        
        $localeToCountry = array(
            'et' => 'ee',
            'lv' => 'lv',
            'lt' => 'lt',
            'fi' => 'fi',
        );

        $locale = \MakeCommerce\i18n::get_two_char_locale();
        if ( array_key_exists( $locale, $localeToCountry ) ) {
            return $localeToCountry[$locale];
        }
        
        return key( $this->banklinks_grouped );
    }

    /**
     * Show available payment methods in checkout
     * 
     * @since 3.0.0
     */
    public function show_methods() {

        if ( !($this->settings['ui_mode'] == 'inline') ) {
            $this->hidden_select_box_payment_methods();
        }

        $this->method_list();
    }

    /**
     * Display country selector for payment methods
     * 
     * @since 3.0.1
     */
    private function method_list_countries() {

        //sort countries
        $this->sort_countries_list();
        
        if ( ( empty( $this->settings['ui_widget_groupcountries'] ) || $this->settings['ui_widget_groupcountries'] == 'no' ) && 
             ( !isset( $this->settings['ui_widget_countries_hidden'] ) || $this->settings['ui_widget_countries_hidden'] == 'no' ) ) {
            ?>
            <div class="makecommerce_country_picker_countries">
                <?php foreach ( array_keys( $this->banklinks_grouped ) as $country ): ?>
                    <input style="display: none;" type="radio" id="makecommerce_country_picker_<?php echo $country; ?>" name="makecommerce_country_picker" value="<?php echo $country; ?>" <?php if ( $this->get_default_country() == $country ) echo 'checked="checked" '; ?>/><?php if ( $this->settings['ui_widget_countryselector'] == 'flag' ) { ?><label for="makecommerce_country_picker_<?php echo $country; ?>" class="makecommerce_country_picker_label country_picker_image_<?php echo $country; ?>" style="background-image: url(<?php echo plugins_url( '/images/'.$country.'32.png', __FILE__ ); ?>);"></label><?php } ?>
                <?php endforeach; ?>
                <?php if ( $this->settings['ui_widget_countryselector'] == 'dropdown' ) : ?>
                    <select name="makecommerce_country_picker_select" style="width: 100%;">
                        <?php foreach ( array_keys( $this->banklinks_grouped ) as $country ): ?>
                            <option value="<?php echo $country; ?>" <?php if ( $this->get_default_country() == $country ) echo 'selected="selected" '; ?>><?php echo \MakeCommerce\i18n::get_country_name( $country ); ?></option>
                        <?php endforeach; ?>
                        <option value="card" style="display:none;"></option>
                    </select>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    /**
     * Sort countries
     * 
     * @since 3.0.6
     */
    private function sort_countries_list() {

        if ( isset( $this->settings['ui_payment_country_order'] ) ) {

            $order = explode( ',', $this->settings['ui_payment_country_order'] );
            foreach ( $order as $key=>&$country ) {
                if ( !isset( $this->banklinks_grouped[$country] ) ) {
                    unset( $order[$key] );
                    continue;
                }

                $country = strtolower( trim( $country ) );
            }

            if ( !empty( $order ) ) {
                $this->banklinks_grouped = array_merge( array_flip( $order ), $this->banklinks_grouped );
            }
        }
    }

    /**
     * Hidden selectbox for all payment methods
     * 
     * @since 3.0.1
     */
    private function hidden_select_box_payment_methods() {

        ?>
        <select id="<?php echo $this->id; ?>" name="PRESELECTED_METHOD_<?php echo $this->id; ?>">
            <option value=""></option>
            <?php foreach( $this->banklinks as $method ): ?>
            <option value="<?php echo $method->country.'_'.$method->name; ?>"><?php echo strtoupper( $method->country ).' - '.ucfirst( $method->display_name ); ?></option>
            <?php endforeach; ?>

            <?php foreach( $this->cards as $method ): ?>
            <option value="card_<?php echo $method->name; ?>"><?php echo ucfirst( $method->display_name ); ?></option>
            <?php endforeach; ?>

            <?php foreach( $this->paylater as $method ): ?>
            <option value="<?php echo $method->country.'_'.$method->name; ?>"><?php echo strtoupper( $method->country ).' - '.ucfirst( $method->display_name ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Country picker
     * 
     * @since 3.0.1
     */
    private function country_picker( $country ) {

        if ( $this->settings['ui_widget_groupcountries'] == 'yes' ) {
                    
            $checked = "";
            if ( $this->get_default_country() == $country ) {
                $checked = 'checked="checked"';
            }

            if ( empty( $this->settings['ui_widget_groupcountries'] ) || $this->settings['ui_widget_groupcountries'] == 'no' ) {
                echo '<input type="radio" id="makecommerce_country_picker_'. $country .'" name="makecommerce_country_picker" value="'. $country .'" '. $checked . '/>';
            } else {

            }

            echo '
            <label for="makecommerce_country_picker_'. $country .'">
                <img src="'.plugins_url( '/images/'.$country.'32.png', __FILE__ ) .'" />
            </label>
            ';
        }
    }
    
    /**
     * Display payment methods as a widget
     * 
     * @since 3.0.0
     */
    private function method_list() {

        echo '<input type="hidden" id="makecommerce_customer_country" value="'.$this->get_default_country().'"/>
        <ul class="makecommerce-picker">';

        global $woocommerce;

        $cartTotal = $woocommerce->cart->total;

        if ( $this->banklinks || $this->cards ) {

            //dont show other countries if there are no card payments available.
            if ( $this->cards ) {
                $this->banklinks_grouped['other'] = array();
            }

            $this->method_list_countries();
            
            foreach ( $this->banklinks_grouped as $country => $methods ) {
                
                echo '<li class="makecommerce-picker-country">';

                $this->country_picker( $country );

                echo '<div class="makecommerce_country_picker_methods logosize-' . $this->settings["ui_widget_logosize"] . '" id="makecommerce_country_picker_methods_'. $country .'">';
                
                $this->banklink_list( $cartTotal, $methods );

                $this->creditcard_list( $cartTotal, $country );

                $this->paylater_list( $cartTotal, $country );
                
                echo '
                    </div>
                </li>
                ';
            }
        }
        
        echo '</ul>
        
        <div class="mc-clear-both"></div
        ';
    }

    /**
     * List version of a method item
     * 
     * @since 3.0.1
     */
    private function list_version_item( $method, $card = false, $country = '' ) {

        $banklink_value = $banklink_id = $method->country.'_'.$method->name;
        if ( $card ) {
            $banklink_value = 'card_' . $method->name;
            $banklink_id = 'card_' . $method->name . '_' . $country;
        }

        $methodName = "";
        if ( in_array( $this->settings['ui_inline_uselogo'], array( 'text', 'text_logo' ) ) ) {
            $methodName = $method->display_name;
        }

        $logo = "";
        if ( in_array( $this->settings['ui_inline_uselogo'], array( 'logo', 'text_logo' ) ) ) {
            if ( $methodName == "" ) {
                $logo = '<img style="display: inline-block; float: none; margin-bottom: -7px;" src="'. $method->logo_url .'" title="'. ucfirst( $method->display_name ) .'" />';
            } else {
                $logo = '<img src="'. $method->logo_url .'" title="'. ucfirst( $method->display_name ) .'" />';
            }
        }

        echo '
        <div class="payment_method_list_item_container">
            <input type="radio" id="makecommerce_method_picker_'. $banklink_id .'" name="PRESELECTED_METHOD_'. $this->id .'" value="'. $banklink_value .'"/>
            
            <label for="makecommerce_method_picker_' . $banklink_id . '">
                <span class="makecommerce-method-title">' . trim($methodName) . $logo.'</span>
            </label>
        </div>
        ';
    }

    /**
     * Widget version of a method item
     * 
     * @since 3.0.1
     */
    private function widget_version_item( $method, $card = false ) {

        $banklink_id = $method->country.'_'.$method->name;
        if ( $card ) {
            $banklink_id = "card_" . $method->name;
        }

        echo '
        <div class="makecommerce-banklink-picker" banklink_id="'. $banklink_id .'" id="' . $method->name . '">
            <img src="'. $method->logo_url .'" title="'. ucfirst( $method->display_name ) .'" />
        </div>
        ';
    }

    /**
     * Show list of banklinks
     * 
     * @since 3.0.1
     */
    private function banklink_list( $cartTotal, $methods ) {

        foreach ( $methods as $method ) {
            // Check min max values
            if ( $this->is_allowed_method( $method, $cartTotal ) ) {
                if ( $this->settings['ui_mode'] == 'inline' ) {
                    $this->list_version_item( $method );
                } else {
                    $this->widget_version_item( $method );
                }
            }
        }
    }

    /**
     * Show list of paylater methodss
     * 
     * @since 3.0.1
     */
    private function paylater_list( $cartTotal, $selected_country ) {

        if ( $this->paylater_grouped ) {
            echo '<div class="breaker"></div>';
        }

        foreach ( $this->paylater_grouped as $country => $methods ) {

            if ( $country == $selected_country ) {
                foreach ( $methods as $method ) {
                    // Check min max values
                    if ( $this->is_allowed_method( $method, $cartTotal ) ) {
                        if ( $this->settings['ui_mode'] == 'inline' ) {
                            $this->list_version_item( $method );
                        } else {
                            $this->widget_version_item( $method );
                        }
                    }
                }
                
                if ( $country == 'other' ) {
                    echo '<p class="no-methods">'. _e( 'No payment methods for selected country' ,'wc_makecommerce_domain' ) .'</p>';
                }
            }
        }
    }

    /**
     * Show list of credit cards
     * 
     * @since 3.0.1
     */
    private function creditcard_list( $cartTotal, $country ) {

        if ( $this->cards ) {

            echo '<div class="breaker"></div>';
            
            foreach ( $this->cards as $method ) {
                // Check min max values
                if ( $this->is_allowed_method( $method, $cartTotal ) ) {
                    if ( $this->settings['ui_mode'] == 'inline' ) {
                        $this->list_version_item( $method, true, $country );
                    } else {
                        $this->widget_version_item( $method, true );
                    }
                }
            }
        } else if ( $country == 'other' ) {
            echo '<p class="no-methods">'. _e( 'No payment methods for selected country' ,'wc_makecommerce_domain' ) .'</p>';
        }
    }

    /**
     * Return list of all payment methods
     * 
     * @since 3.0.12
     */
    static function get_payment_methods() {
        global $wpdb;

        return $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . MAKECOMMERCE_TABLENAME );
    }

    /**
     * Return bool whether the payment method can be used based on amount constraints
     *
     * @since 3.3.0
     */
    private function is_allowed_method( $method, $cartTotal ) {
        $min = $method->min_amount ?? 0;
        $max = $method->max_amount ?? INF;

        return $min <= $cartTotal && $max >= $cartTotal;
    }
}
