<?php

namespace MakeCommerce\Shipping\Method;

/**
 * Courier shipping specific methods
 * extended by specific courier methods
 * 
 * @since 3.0.0
 */

abstract class Courier extends \MakeCommerce\Shipping\Method {

    public $type = "cou";
    public $identifier = "courier";
    public $identifierString;


    /**
     * Required function for all methods, initializes method type
     * 
     * @since 3.0.0
     */
    public function initialize() {

        //set method hooks
        $this->set_method_hooks();

        //needed for identifier translation
        $this->identifierString = __( "courier", 'wc_makecommerce_domain' );
    }

    /**
     * Set method title
     * 
     * @since 3.0.0
     */
    public function return_method_title() {

        return ucfirst( $this->carrier_title ) . " " . __( "courier (MC)", 'wc_makecommerce_domain' );
    }

    /**
     * Initialize method type specific checkout fields
     * 
     * @since 3.0.0
     */
    public function initialize_method_type_checkout_fields() {

    }

    /**
     * Force creating a function for initializing method specific form fields
     * 
     * @since 3.0.0
     */
    abstract public function initialize_method_form_fields();

    /**
     * Force creating a function for setting method specific hooks
     * 
     * @since 3.0.0
     */
    abstract public function set_method_hooks();
}
