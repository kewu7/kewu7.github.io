<?php

namespace MakeCommerce\Shipping\Method\Courier;

/**
 * Specifics for Smartpost courier shipping method
 * 
 * @since 3.0.0
 */

class Smartpost extends \MakeCommerce\Shipping\Method\Courier {

    use \MakeCommerce\Shipping\Method\Common\Smartpost;

    public $default_price = "4.95";
    public $default_max_weight = "60";

    /**
     * Overrides Method class function
     * Removes loading of user and password fields
     * 
     * @since 3.3.0
     */
    public function initialize_method_type_form_fields() {

        //Initialize method specific fields
        $this->initialize_method_form_fields();
    }

    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.0.0
     */
    public function initialize_method_form_fields() {

        $this->initialize_smartpost_api_field();
    }

    /**
     * Set method specific hooks/filters
     * 
     * @since 3.0.0
     */
    public function set_method_hooks() {

        add_filter( 'woocommerce_review_order_after_shipping' , array( $this, 'add_smartpost_courier_checkout_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_order_meta' ) );
    }

    /**
     * Adds courier arrival time select option to checkout fields
     * 
     * @since 3.0.0
     */
    public function add_smartpost_courier_checkout_fields( $fragment ) {

        echo '
        <tr style="display: none;" class="parcel_machine_checkout parcel_machine_checkout_courier_' . mb_strtolower($this->ext ) . '">
            <th>'.__('Pick courier arrival time window', 'wc_makecommerce_domain').'</th>
            <td>
                <p class="form-row" id="' . esc_attr( $this->id ) . '_field">
                    <select class="select parcel-machine-select-box-time" name="' . esc_attr( $this->id ) . '" id="' . esc_attr( $this->id ) . '">
                        <option value="1">' . __( 'Any time', 'wc_makecommerce_domain' ) . '</option>
                        <option value="2">' . __( 'Worktime (09:00..17:00)', 'wc_makecommerce_domain' ) . '</option>
                        <option value="3">' . __( 'After worktime (17:00..21:00)', 'wc_makecommerce_domain' ) . '</option>
                    </select>
                </p>
            </td>
        </tr>
        ';
    }

    /**
     * Adds delivery time option to order metadata
     * 
     * @since 3.0.0
     */
    public function add_order_meta( $order_id ) {

        if ( !empty( $_POST[$this->id] ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( '_delivery_time', sanitize_text_field( $_POST[$this->id] ) );
            $order->save();
        }
    }
}
