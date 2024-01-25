<?php

namespace MakeCommerce\Payment\Gateway\WooCommerce;

trait Paylater {

    /**
     * Allowed pay later product view methods
     */
    public $allowed_pl_product_view_methods = array(
        'slice',
        'indivy-go',
        'liisi_ee',
        'bigbank'
    );

    /**
     * Allowed shop locations
     */
    public $allowed_pl_countries = array(
        'EE',
        'LV'
    );

    //needed so that the block is not show twice.
    public static $pay_later_shown = false;

    /**
     * Returns description for pay later method
     * 
     * @throws exception when method is not implemented
     * @since 3.0.9
     */
    public function get_paylater_method_description( $method, $price ) {

        switch ( $method ) {
            case 'slice':

                $amount = round( $price / 3, 2 );
                $first_payment = date( 'Y-m-d', strtotime( '+30 days' ) );

                return sprintf( __( 'Pay in <b>three equal parts, %s EUR per month</b>. First payment on <b>%s %s</b>', 'wc_makecommerce_domain' ), $amount, $this->month_to_text( strtotime( $first_payment ) ), $this->day_to_ordinal( strtotime( $first_payment ) ) );
            case 'indivy-go':

                return __( 'Buy now, <b>pay on the 25th of the next month</b>', 'wc_makecommerce_domain' );
            case 'liisi_ee':

                $interest = 11.9;
                $downpayment = 0;
                $months = 12;
                $amount = ( ( $price - $downpayment ) * ( 1 + ( $interest / 100 ) * $months / 12 ) ) / ( $months );

                return sprintf( __( 'Pay <b>%s EUR per month for 12 months</b>. Interest %s', 'wc_makecommerce_domain' ), ceil( $amount ), $interest ) . '%';
            case 'bigbank':

                $months = 12;
                $downpayment = 0;
                $interest = 12.9;
                $contractfee = 3;
                $managementfee = 0;

                $contractconclusion = $price / 100 * $contractfee;
                $creditamount = ( $price - $downpayment ) + $contractconclusion;

                $monthlypayment = $this->pmt( $interest, $months, $creditamount );
                                
                $totalamount = $months * $monthlypayment;
                $interestpayment = $totalamount - $creditamount - ( $months * $managementfee );
                $annualinterest = $this->effect( $this->rate( $months, $monthlypayment, -( $creditamount - $contractconclusion ) ) * 12, 12 ) * 100;

                return sprintf( 
                    __( 'Pay <b>%s EUR per month</b> for 12 months<br>
                        <br>
                        <i>Borrowing rate %s&#37;, credit amount %s€, contract conclusion fee %s€, total amount payable %s€, annual interest rate %s&#37;
                        Calculation is only informative.</i>' , 'wc_makecommerce_domain'
                    ), 
                    number_format( $monthlypayment, 2 ),
                    number_format( $interest, 2 ),
                    number_format( $creditamount, 2 ),
                    number_format( $contractconclusion, 2 ),
                    number_format( $totalamount, 2 ),
                    number_format( $annualinterest, 2 )
                );
            default:
            
                return 'Pay with ' . $method;
        }
    }

    /**
     * Calculate rate
     * 
     * @since 3.0.10
     */
    public function rate( $numberOfPeriods, $payment, $presentValue ) {

        $rate = 0.1;
        $close = false;
        $iter = 0;

        while ( !$close && $iter < 128 ) {

            $nextdiff = $this->rateNextGuess( $rate, $numberOfPeriods, $payment, $presentValue );
            if ( !is_numeric( $nextdiff ) ) {
                break;
            }

            $rate1 = $rate - $nextdiff;
            $close = abs( $rate1 - $rate ) < 1.0e-08;
            $iter++;
            $rate = $rate1;
        }

        return $rate;
    }

    /**
     * Returns next rate guess
     * 
     * @since 3.0.10
     */
    private function rateNextGuess( $rate, $numberOfPeriods, $payment, $presentValue, $futureValue = 0, $type = 0 )
    {
        $tt1 = ( $rate + 1 ) ** $numberOfPeriods;
        $tt2 = ( $rate + 1 ) ** ( $numberOfPeriods - 1 );
        $numerator = $futureValue + $tt1 * $presentValue + $payment * ( $tt1 - 1 ) * ( $rate * $type + 1 ) / $rate;
        $denominator = $numberOfPeriods * $tt2 * $presentValue - $payment * ( $tt1 - 1 )
            * ( $rate * $type + 1 ) / ( $rate * $rate ) + $numberOfPeriods
            * $payment * $tt2 * ( $rate * $type + 1 ) / $rate + $payment * ( $tt1 - 1 ) * $type / $rate;

        return $numerator / $denominator;
    }

    /**
     * Calculate monthly payment
     * 
     * @since 3.0.10
     */
    public function pmt( $interest, $months, $loan )
    {
        $interest = $interest / 1200;
        $amount = $interest * -$loan * pow( ( 1 + $interest ), $months ) / ( 1 - pow( ( 1 + $interest ), $months ) );

        return $amount;
    }

    /**
     * Calculate effect
     * 
     * @since 3.0.10
     */
    public function effect($nominalRate, $periodsPerYear)
    {
        return ( ( 1 + $nominalRate / $periodsPerYear ) ** $periodsPerYear ) - 1;
    }

    /**
     * Adds pay later block to product view
     * 
     * @since 3.0.9
     */
    public function add_pay_later_block_to_product() {

        if ( !in_array( wc_get_base_location()['country'], $this->allowed_pl_countries ) ) {
            return;
        }

        //don't show more than once
        if ( self::$pay_later_shown ) {
            return;
        }

        global $product;

        //ignore for subscription and grouped products
        if ( class_exists( '\WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
            return;
        }
        
        //get product using variation id
        if ( isset( $_GET['paylater_variation_id'] ) ) {
            $product = wc_get_product( $_GET['paylater_variation_id'] );
        }

        //dont use for grouped products. Too confusing to implement. Something for v2 perhaps
        if ( $product->get_type() == 'grouped' ) {
            return;
        }

        //get product price
        $price = wc_get_price_including_tax( $product );

        //get cart total
        $total = floatval( WC()->cart->get_cart_contents_total() );

        //get allowed methods
        $methods = $this->get_allowed_pl_methods();

        //add paylater css and js
        wp_enqueue_style( 'mc_paylater', plugins_url( '/css/paylater.css', __FILE__ ), array(), $this->version );
        \MakeCommerce::mc_enqueue_script( 
            'MC_PAYLATER', 
            dirname( __FILE__ ) . '/js/mc_paylater.js', 
            [ 'type' => $product->get_type() ], 
            [ 'jquery' ]
        );

        $logo = plugins_url( '/images/paylater-en.png', __FILE__ );
        if ( file_exists( dirname( __FILE__ ) . '/images/paylater-' . \MakeCommerce\i18n::get_two_char_locale() .'.png' ) ) {
            $logo = plugins_url( '/images/paylater-' . \MakeCommerce\i18n::get_two_char_locale() . '.png', __FILE__ );
        }

        $displaycss = '';
        if ( $product->get_type() == 'variable' || isset( $_GET['paylater_variation_id'] ) ) {
            $displaycss = 'style="display: none;"';
        }

        echo '<div id="mc_paylater_parent">';
        $pl_html = '
        <div class="mc_paylater_block" '.$displaycss.' id="mc_paylater_block">
            <img src="' . $logo . '" />
            <div class="mc_paylater_block_inner">
        ';

        $first = true;
        foreach ( $methods as $method ) {
            
            if ( in_array( $method->name, $this->settings["pl_show_methods"] )
                && $price >= $method->min_amount
                && $price + $total <= $method->max_amount
            ) {
                if ( !$first ) {
                    $pl_html .= '<hr />';
                }

                $pl_html .= '
                <a style="all: unset;" href="' . esc_url( $product->add_to_cart_url() ) .'&mc_paylater_method=' . $method->name . '&mc_paylater_method_country=' . $method->country . '">
                    <div class="mc_paylater_line" id="' . $method->name . '">
                        <div><img class="mc_paylater_image" src="' . $method->logo_url . '" /></div>
                        <div><p class="mc_paylater_text">' . $this->get_paylater_method_description( $method->name, $price ) . '</p></div>
                    </div>
                </a>
                ';

                $first = false;
            }
        }

        $pl_html .= '
            </div>
        </div>
        ';

        //if first is false then at least on of the methods was accepted. Otherwise dont show pay later block at all
        if ( !$first ) {
             echo $pl_html;
        }

        echo '</div>';

        self::$pay_later_shown = true;
    }

    /**
     * Returns all allowed paylater methods
     * 
     * @since 3.0.9
     */
    public function get_allowed_pl_methods() {

        global $wpdb;

        return $wpdb->get_results( '
        SELECT 
            *
        FROM 
            ' . $wpdb->prefix . MAKECOMMERCE_TABLENAME . '
        WHERE 
            `type` = "payLater" AND 
            `name` IN (\''.implode( "','", $this->allowed_pl_product_view_methods ).'\')
        ' );
    }

    /**
     * Sets pay later settings for admin
     * 
     * @since 3.0.9
     */
    public function set_pay_later_settings() {

        $methods = $this->get_allowed_pl_methods();

        if ( !empty( $methods ) && in_array( wc_get_base_location()['country'], $this->allowed_pl_countries ) ) {

            $this->form_fields['pl_title'] = array(
                'title' => '<hr><br>'.__( 'Pay later Settings', 'wc_makecommerce_domain' ),
                'type' => 'title',
            );

            $this->form_fields['pl_show_on_product_pages'] = array(
                'title' => __( 'Show pay later options at product view', 'wc_makecommerce_domain' ),
                'type' => 'checkbox',
                'default' => 'no'
            );
            
            $options = array();
            foreach ( $methods as $method ) {
                if ( isset($method->display_name) ) {
                    $options[$method->name] = $method->display_name;
                } else {
                    $options[$method->name] = $method->name;
                }
            }

            $this->form_fields['pl_show_methods'] = array(
                'title' => __( 'Select desired pay later methods to show in product view', 'wc_makecommerce_domain' ),
                'type' => 'multiselect',
                'options' => $options,
                'desc_tip' => __( 'Select multiple methods by holding down Ctrl or Command button', 'wc_makecommerce_domain' ),
            );
        } else {

            $description = __( 'Your shop doesn\'t currently offer pay later methods with Maksekeskus. Adding pay later options to your should increases number of customers and purchase amounts. Please <a target="_blank" href="https://makecommerce.net/contact/">get in touch</a> with us to enable pay later for your shop.', 'wc_makecommerce_domain' );

            if ( !in_array( wc_get_base_location()['country'], $this->allowed_pl_countries ) ) {
                $description = __( 'This pay later feature is not available in your country yet. <a target="_blank" href="https://makecommerce.net/contact/">Contact us</a> for more information!', 'wc_makecommerce_domain' );
            }

            $this->form_fields['pl_title'] = array(
                'title' => '<hr><br>' . __( 'Pay later Settings', 'wc_makecommerce_domain' ),
                'description' => $description,
                'type' => 'title',
            );
        }
    }

    /**
     * Redirect to checkout after cart add via paylater and set selected method to session
     * 
     * @since 3.0.9
     */
    public function added_to_cart_via_paylater_redirect() {

        if ( isset( $_GET['mc_paylater_method'] ) ) {
            if ( !empty( $_GET['mc_paylater_method'] ) ) {

                WC()->session->set( 'mc_selected_paylater_method', $_GET['mc_paylater_method']);
                WC()->session->set( 'mc_selected_paylater_method_country', $_GET['mc_paylater_method_country']);

                return wc_get_checkout_url();
            }
        }
        return wc_get_cart_url();
    }

    static $autoselect_enqueued = false;

    /**
     * Automatically select chosen paylater method in checkout
     * 
     * @since 3.0.9
     */
    public function autoselect_chosen_paylater_method( $wccm_after_checkout  ) {

        $selectedMethod = WC()->session->get( 'mc_selected_paylater_method' );

        if ( $selectedMethod !== NULL && !self::$autoselect_enqueued ) {

            \MakeCommerce::mc_enqueue_script( 
                'MC_PAYLATER_AUTOSELECT', 
                dirname( __FILE__ ) . '/js/mc_paylater_autoselect.js', 
                [ 'method' => $selectedMethod, 'country' => WC()->session->get( 'mc_selected_paylater_method_country' ), 'id' => $this->id ], 
                [ 'jquery' ]
            );
            
            self::$autoselect_enqueued = true;

            //unset session after selecting once.
            WC()->session->__unset( 'mc_selected_paylater_method' );
            WC()->session->__unset( 'mc_selected_paylater_method_country' );
        }
    }

    static $pl_notice_shown = false;
    /**
     * Adds notification to admin when paylater is loaded for the first time
     * 
     * @since 3.0.9
     */
    public function paylater_first_time_notice() {

        if ( !in_array( wc_get_base_location()['country'], $this->allowed_pl_countries ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( isset( $_GET['mc_pay_later_notice_dismissed'] ) ) {
            add_user_meta( $user_id, 'mc_pay_later_notice_dismissed', 'true', true );
        }

        if ( self::$pl_notice_shown || get_user_meta( $user_id, 'mc_pay_later_notice_dismissed' ) ) {
            return;
        }

        $dismiss_href = '?mc_pay_later_notice_dismissed';

        if ( isset( $_GET ) && !empty( $_GET ) ) {
            $dismiss_href = '&mc_pay_later_notice_dismissed';
        }

        echo '
        <div class="notice notice-info">
            <p>
                <a style="float: right; " href="' . $dismiss_href . '">' . __( 'Dismiss', 'wc_makecommerce_domain' ) . '</a>
                ' . sprintf( __( 'MakeCommerce module now includes <a href="%s">option to display pay later options and payment terms examples in product view</a>. Having pay later options available potentially grows your customer base as well as average shopping cart. We recommend to use this option if considerable number of purchases are above 75 euros.', 'wc_makecommerce_domain' ), '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=makecommerce&mc_pay_later_notice_dismissed' ). '
            </p>
        </div>';

        self::$pl_notice_shown = true;
    }

	/**
	 * Converts date to textual representation
	 * Expects unixtime, returns textual month e.g. November, July
	 * 
	 * @since 3.0.9
	 */
	public function month_to_text( $timestamp ) {

		$months = array(
			1 => __( 'January', 'wc_makecommerce_domain' ),
			2 => __( 'February', 'wc_makecommerce_domain' ),
			3 => __( 'March', 'wc_makecommerce_domain' ),
			4 => __( 'April', 'wc_makecommerce_domain' ),
			5 => __( 'May', 'wc_makecommerce_domain' ),
			6 => __( 'June', 'wc_makecommerce_domain' ),
			7 => __( 'July', 'wc_makecommerce_domain' ),
			8 => __( 'August', 'wc_makecommerce_domain' ),
			9 => __( 'September', 'wc_makecommerce_domain' ),
			10 => __( 'October', 'wc_makecommerce_domain' ),
			11 => __( 'November', 'wc_makecommerce_domain' ),
			12 => __( 'December', 'wc_makecommerce_domain' )
		);

		return $months[ date( 'n', $timestamp ) ];
	}

	/**
	 * Converts date to textual representation
	 * Expects unixtime, returns ordinal number/text mix e.g. 1st, 2nd
	 * 
	 * @since 3.0.9
	 */
	public function day_to_ordinal( $timestamp ) {

		$days = array(
			1 => __( '1st', 'wc_makecommerce_domain' ),
			2 => __( '2nd', 'wc_makecommerce_domain' ),
			3 => __( '3rd', 'wc_makecommerce_domain' ),
			4 => __( '4th', 'wc_makecommerce_domain' ),
			5 => __( '5th', 'wc_makecommerce_domain' ),
			6 => __( '6th', 'wc_makecommerce_domain' ),
			7 => __( '7th', 'wc_makecommerce_domain' ),
			8 => __( '8th', 'wc_makecommerce_domain' ),
			9 => __( '9th', 'wc_makecommerce_domain' ),
			10 => __( '10th', 'wc_makecommerce_domain' ),
			11 => __( '11th', 'wc_makecommerce_domain' ),
			12 => __( '12th', 'wc_makecommerce_domain' ),
			13 => __( '13th', 'wc_makecommerce_domain' ),
			14 => __( '14th', 'wc_makecommerce_domain' ),
			15 => __( '15th', 'wc_makecommerce_domain' ),
			16 => __( '16th', 'wc_makecommerce_domain' ),
			17 => __( '17th', 'wc_makecommerce_domain' ),
			18 => __( '18th', 'wc_makecommerce_domain' ),
			19 => __( '19th', 'wc_makecommerce_domain' ),
			20 => __( '20th', 'wc_makecommerce_domain' ),
			21 => __( '21st', 'wc_makecommerce_domain' ),
			22 => __( '22nd', 'wc_makecommerce_domain' ),
			23 => __( '23rd', 'wc_makecommerce_domain' ),
			24 => __( '24th', 'wc_makecommerce_domain' ),
			25 => __( '25th', 'wc_makecommerce_domain' ),
			26 => __( '26th', 'wc_makecommerce_domain' ),
			27 => __( '27th', 'wc_makecommerce_domain' ),
			28 => __( '28th', 'wc_makecommerce_domain' ),
			29 => __( '29th', 'wc_makecommerce_domain' ),
			30 => __( '30th', 'wc_makecommerce_domain' ),
			31 => __( '31st', 'wc_makecommerce_domain' )
		);

		return $days[ date( 'j', $timestamp ) ];
	}
}
