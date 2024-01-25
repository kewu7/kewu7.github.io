<?php

namespace MakeCommerce\Shipping;

/**
 * All functionality that has to do with shipping and products
 * 
 * @since 3.0.0
 */

class Product extends \MakeCommerce\Shipping {

	private $loader;
	
	/**
	 * Constructs Label class, defines loader and hooks
	 * 
	 * @since 3.0.0
	 */
    public function __construct( \MakeCommerce\Loader $loader ) {
    
        $this->loader = $loader;

        $this->define_hooks();
	}
	
	/**
	 * Define all wordpress hooks needed for printing stuff and creating labels
	 * 
	 * @since 3.0.0
	 */
    public function define_hooks() {

        $this->loader->add_filter( 'woocommerce_product_options_shipping', $this, 'option_fields' );
        $this->loader->add_filter( 'woocommerce_process_product_meta', $this, 'save' );
    }

    /**
     * Save product fields
     * 
     * @since 3.0.0
     */
    public function save( $post_id ) {
	    $no_parcel_machine = isset($_POST['_no_parcel_machine']) ? 'yes' : 'no';
	    update_post_meta($post_id, '_no_parcel_machine', $no_parcel_machine);

	    $no_shipping_cost = isset($_POST['_no_shipping_cost']) ? 'yes' : 'no';
	    update_post_meta($post_id, '_no_shipping_cost', $no_shipping_cost);
    }
    
    /**
     * Add options to product page
     * 
     * @since 3.0.0
     */
    public function option_fields( $fields ) {

		echo '<div class="options_group">';

		woocommerce_wp_checkbox( 
			array( 
				'id'            => '_no_parcel_machine',
				'label'         => __('Does not fit parcel machine', 'wc_makecommerce_domain'), 
				'description'   => __('When this is checked, parcel machine shipping option is not available for a cart with this product', 'wc_makecommerce_domain')
			)
		);

		woocommerce_wp_checkbox( 
			array( 
				'id'            => '_no_shipping_cost',
				'label'         => __('Free parcel machine', 'wc_makecommerce_domain'), 
				'description'   => __('When this is checked, parcel machine is free for this product', 'wc_makecommerce_domain')
			)
		);

		echo '</div>';
    }
}