<?php

namespace MakeCommerce\Shipping\Method;

/**
 * Adds shipping classes functionality to shipping methods
 * 
 * @since 3.0.6
 */

trait ShippingClasses {

    /**
     * Add shipping class specific form fields to admin
     * 
     * @since 3.0.6
     */
    public function add_form_fields( $instance_form_fields ) {

        $shipping_classes = WC()->shipping()->get_shipping_classes();

        if ( ! empty( $shipping_classes ) ) {

            $instance_form_fields['class_costs'] = array(
                'title'       => __( 'Shipping class costs (optional)', 'wc_makecommerce_domain' ),
                'description' => __( 'Shipping class cost is added to the shipping price. If multiple are found, the one with highest cost is added.', 'wc_makecommerce_domain' ),
                'type'        => 'title',
                'default'     => '',
            );

            foreach ( $shipping_classes as $shipping_class ) {

                if ( ! isset( $shipping_class->term_id ) ) {
                    continue;
                }
                
                $instance_form_fields[ 'class_cost_' . $shipping_class->term_id ] = array(
                    'title'       => esc_html( $shipping_class->name ),
                    'type'        => 'text',
                    'default'     => '',
                    'desc_tip'    => true,
                );
            }
        }
        
        return $instance_form_fields;
    }

    /**
     * Calculates shipping price using shipping class costs
     * 
     * @since 3.0.6
     */
    public function calculate_shipping_class_price( $rate, $package ) {

		$shipping_classes = WC()->shipping()->get_shipping_classes();

		if ( !empty( $shipping_classes ) ) {
            $found_shipping_classes = $this->find_shipping_classes( $package );
            $highest_class_cost = 0;

            foreach ( $found_shipping_classes as $shipping_class => $products ) {
                $shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
                $class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $this->get_option( 'class_cost_' . $shipping_class_term->term_id, $this->get_option( 'class_cost_' . $shipping_class, '' ) ) : 0;

                if ( '' === $class_cost_string ) {
                    continue;
                }

                $class_cost = $this->evaluate_cost( $class_cost_string );

                if ( $class_cost > $highest_class_cost ) {
                    $highest_class_cost = $class_cost;
                }
            }

            if ( !is_numeric( $rate['cost'] ) ) {
                $rate['cost'] = intval( $rate['cost'] );
            }

            if ( !is_numeric( $highest_class_cost ) ) {
                $highest_class_cost = intval( $highest_class_cost );
            }

            $rate['cost'] += $highest_class_cost;
		}

        return $rate;
    }

    /**
     * Finds and returns a shipping classes
     * 
     * @since 3.0.6
     */
    public function find_shipping_classes( $package ) {

		$found_shipping_classes = array();

		foreach ( $package['contents'] as $item_id => $values ) {
			if ( $values['data']->needs_shipping() ) {
				$found_class = $values['data']->get_shipping_class();

				if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
					$found_shipping_classes[ $found_class ] = array();
				}

				$found_shipping_classes[ $found_class ][ $item_id ] = $values;
			}
		}

		return $found_shipping_classes;
	}

    /**
     * Formats and returns sum
     * 
     * @since 3.0.6
     */
    protected function evaluate_cost( $sum ) {

		$locale   = localeconv();
		$decimals = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'] );

		// Remove whitespace from string.
		$sum = preg_replace( '/\s+/', '', $sum );

		// Remove locale from string.
		$sum = str_replace( $decimals, '.', $sum );

		// Trim invalid start/end characters.
		$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// Do the math.
		return $sum ? $sum : 0;
	}
}
