<?php

namespace MakeCommerce;

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      3.0.0
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */
class i18n {

	//set default locale when there is nothing specified
	public $defaultLocale = "et";

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    3.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'wc_makecommerce_domain',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}

	/**
     * Returns country name
     * 
     * @since 3.0.0
     */
    public static function get_country_name( $country_code ) {

        switch ( $country_code ) {
            case 'ee':
                return __( 'Estonia', 'wc_makecommerce_domain' );
                break;
            case 'lv':
                return __( 'Latvia', 'wc_makecommerce_domain' );
                break;
            case 'lt':
                return __( 'Lithuania', 'wc_makecommerce_domain' );
                break;
            case 'fi':
                return __( 'Finland', 'wc_makecommerce_domain' );
                break;
			case 'other':
				return __( 'International', 'wc_makecommerce_domain' );
				break;
        }

        return $country_code;
    }

	/**
	 * Gets the default language for the site.
	 * 
	 * @since 3.0.3
	 */
	public static function get_site_default_language() {

		//polylang
		if ( self::is_polylang_active() ) {

			return strtolower( substr( pll_default_language(), 0, 2 ) );
		}

		//woocommerce
		if ( self::is_wpml_woocommerce_active() ) {

			global $sitepress;

			if ( isset($sitepress) ) {
				return strtolower( substr( $sitepress->get_default_language(), 0, 2 ) );
			}
		}

		//default
		return self::get_two_char_locale();
	}

	/**
	 * Returns currently used locale
	 * WPML is superior to Polylang in case both are active
	 * 
	 * @since 3.0.0
	 */
	public static function get_locale() {

		$locale = get_locale();
		
		if ( $locale ) {
			
			$locale = explode( '_', $locale );

			if ( isset( $locale[0] ) ) {
				$locale = $locale[0];
			}
		}
		
		//polylang uses different locale names, while wpml uses lv, lt, en. Polylang uses lv_LV, lt_LT and en_GB
        //this method should be improved and made generic everywhere
        if ( function_exists( 'pll_languages_list' ) ) {
            $locale = get_locale();
		}
		
		//nothing found, set default
		if ( empty( $locale ) ) {
			return self::$defaultLocale;
		}
		
		return $locale;
	}

	/**
	 * Returns the first two characters of the currently used locale
	 * 
	 * @since 3.0.0
	 */
	public static function get_two_char_locale() {

		return strtolower( substr( self::get_locale(), 0, 2 ) );
	}

	/**
     * if orderMeta contains wpml language parameter then change the language to it
     * 
     * @since 3.0.0
     */
    public static function change_language_from_post( $orderMeta ) {

        if ( isset( $orderMeta["meta"] ) ) {
            foreach ( $orderMeta["meta"] as $key=>$value ) {
                if ( is_array( $value ) ) {
                    if ( $value["key"] == "wpml_language" ) {
                        self::switch_language( $value["value"] );
                    }
                }
            }
        }
    }
	
	/**
	 * Returns true if WPML for WooCommerce is active, else false
	 * 
	 * @since 3.0.0
	 */
	public static function is_wpml_woocommerce_active() {

		if ( in_array( 'woocommerce-multilingual/wpml-woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if polylang plugin is active, else false
	 * 
	 * @since 3.0.0
	 */
	public static function is_polylang_active() {

		if ( function_exists( 'pll_languages_list' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if there are any language plugins like Polylang or WPML used
	 * 
	 * @since 3.0.0
	 */
	public static function using_language_plugins() {

		if ( self::is_polylang_active() || self::is_wpml_woocommerce_active() ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns a string from mo file for the specified locale
	 * 
	 * @since 3.0.0
	 */
    public static function get_string_from_mo( $string, $textdomain, $locale ) {

        $file = plugin_dir_path( __FILE__ ) . '../languages/' . $textdomain . '-' . $locale . '.mo';

        if ( file_exists( $file ) ) {
			
			$mo = new \MO();
            $mo->import_from_file( $file );
            
            if ( isset( $mo->entries ) ) {
                if ( isset( $mo->entries[$string] ) ) {
                    if ( isset( $mo->entries[$string]->translations ) ) {
                        if ( isset( $mo->entries[$string]->translations[0] ) ) {
                            $string = $mo->entries[$string]->translations[0];
                        }
                    }
                }
            }
        }

        return $string;
	}
	
	/**
	 * Get a list of languages that are used
	 * WPML is superior to Polylang in case both are active
	 * 
	 * @since 3.0.0
	 */
	public static function get_active_languages() {

		$languages = array();

		//wpml
        if ( function_exists( 'icl_object_id' ) && !function_exists( 'pll_languages_list' ) ) {

            $languages = apply_filters( 'wpml_active_languages', NULL, 'skip_missing=0' );
        } else if ( function_exists( 'pll_languages_list' ) ) { //polylang

            $pl_locales = pll_languages_list( array( 'fields'=>'locale' ) );
            foreach ( $pl_locales as $key => $locale_pl ) {

                $language = array( 'id' => $key, 'code' => $locale_pl ) ;
                $languages[$locale_pl] = $language;
            }
		}

		$languages2 = array();
		foreach ( $languages as $key => $language ) {

			$language["code"] = strtolower( substr( $language["code"], 0, 2 ) );
			$languages2[strtolower( substr( $key, 0, 2 ) )] = $language;
		}
		
		return $languages2;
	}

	/**
     * Switches language using WPML
	 * 
	 * This function needs work
     * 
     * @since 3.0.0
     */
	public static function switch_language( $language_code ) {

		//switch wordpress language first
		try {
			switch_to_locale( $language_code );
		} catch ( Throwable $t ) {
			//switching to locale doesnt always work
			error_log( print_r( $t, true ) );
		}

		//set wpml language
		if ( function_exists( 'icl_object_id' ) ) {
			do_action( 'wpml_switch_language', $language_code );

		} else {
			//set polylang language
			if ( function_exists( 'pll_current_language' ) ) {
				do_action( 'wpml_switch_language', $language_code ); //this also seems to work for polylang
			}
		}
	}
}
