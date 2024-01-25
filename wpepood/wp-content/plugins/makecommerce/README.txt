=== MakeCommerce for WooCommerce ===
Contributors: MakeCommerce
Tags: woocommerce, payment, maksekeskus, shipping, banklink, creditcard, estonia, latvia, lithuania, pangalink, kaardimaksed, omniva, smartPOST, WPML , eesti, swedbank, seb, lhv, citadele, nordea, pocopay
Requires at least: 5.6.1
Tested up to: 6.3.2
Stable tag: 3.4.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Payment Gateway for Estonian, Latvian, Lithuanian and Finnish bank-links and Visa/MasterCard payments with single contract (by Maksekeskus). And more...


== Description ==
MakeCommerce is a payment service provider for e-commerce businesses in the Baltics and Finland.
It is the international brand of Maksekeskus AS.

The MakeCommerce plugin enables Estonian, Latvian, Lithuanian and Finnish bank payments, credit card payments, Revolut and N26 payments, and buy-now-pay-later (BNPL) payment options.
In addition, it can be used to register parcel machine deliveries with Omniva, DPD, Itella SmartPost and LP Express.

In order to use the services please sign up at https://makecommerce.net/ and then configure the module by entering API credentials given to your shop after sign-up.
(You can also test out the module without signup, using test-shop credentials).

No multiple bank contracts needed, all you need is one bank account.

Installing the plugin is free of charge.
A transaction fee is charged by MakeCommerce separately.
See our pricing at https://makecommerce.net/pricing/.

Overview of functionality:

* Bank-links of all major banks in Estonia, Latvia, Lithuania, Finland
* Credit-card payments (Visa, Mastercard) through MakeCommerce PCI DSS compliant card dialog (removes compliancy requirement from merchant)
* Pay later payments
* Recurring payments with credit cards for subscriptions
* Customisable payment methods presentation  
* Payment country selector independent of billing/shipping address
* Make full or partial refunds right within shop Admin (order view)
* Omniva, Smartpost, DPD & LP Express automated parcel terminals as shipment methods
* Omniva, Smartpost & DPD courier service as shipment method
* Automatic registration of shipments into Omniva, Smartpost, DPD & LP Express systems
* Printing Omniva/Smartpost/DPD/LP Express parcel labels right within shop Admin (orders view)
* Shipping methods support Shipping Zones
* Supports multilingual shops (WPML and Polylang). The plugin is fully translated to English, Russian, Estonian, Latvian and Lithuanian
* Supports WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels
* Supports WordPress multi-site


== Installation ==
1. Install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure MakeCommerce API settings (Woocommerce->Settings->Advanced->MakeCommerce API access)
4. Fine-tune and activate your payment settings (Woocommerce->Settings->Payments->MakeCommerce)
5. Configure and activate shipping methods:
  * Woocommerce->Settings->Shipping->Omniva Parcel Machine by MC
  * Woocommerce->Settings->Shipping->Smartpost Parcel Machine by MC
  * Woocommerce->Settings->Shipping->DPD Parcel Machine by MC
  * Woocommerce->Settings->Shipping->LP Express Parcel Machine by MC
  * Woocommerce->Settings->Shipping->Omniva Courier by MC
  * Woocommerce->Settings->Shipping->Smartpost Courier by MC
  * Woocommerce->Settings->Shipping->DPD Courier by MC



See more on:
https://makecommerce.net/integration-modules/MakeCommerce-WooCommerce-Payment-plugin/


== Screenshots ==

1. presentation of payment methods in checkout dialog
5. easy way to refund
10. the plugin adds to shipping methods to the shop
11. the plugin provides dropdowns of Parcel Terminals on checkout page
14. you can print package labels right from the shop admin view
15. you can mark some product as 'not suitable' for parcel terminal delivery
21. for multilingual shops you can adjust translations


-

== Changelog ==

= 3.4.2 2024-01-03 =
* Fix - Orders with wrong or missing transaction ids

= 3.4.1 2023-12-22 =
* Fix - Correct hook for payment method title filter
* Fix - Parcel machine translation

= 3.4.0 2023-12-20 =
* Feature - WooCommerce HPOS support
* Feature - DPD courier addition
* Feature - TMS support for DPD parcel machines

= 3.3.1 2023-11-09 =
* Fix - Shipment credentials pickup fix for LV and LT
* Fix - Separate credit card payment method identifiers in checkout list view

= 3.3.0 2023-11-03 =
* Feature - Tracking service implementation
* Feature - Options for disabling automatic status changes
* Feature - Map view for the selection of parcel machines
* Feature - LP Express order tracking in admin order view
* Tweak - Improved the deposit payment logic
* Tweak - Made duplicate error messages method specific
* Tweak - Automated migration from older versions to SmartPOST API key
* Tweak - Simple code refactoring
* Tweak - Geocoding API key check and error
* Tweak - Improved obtaining customer language
* Tweak - Min and max values for payment options
* Tweak - Improved failed refund error notice message
* Fix - Improved LP Express API call logic
* Fix - Improved Smartpost shipment registration flow

= 3.2.1 2023-03-21 =
* Feature - Added API key field for Smartpost
* Tweak - Updated Indivy description
* Fix - Wrong function call for shipping method titles
* Fix - Resetting API cache when switching environments
* Fix - Displaying payment methods for Polylang Russian checkout

= 3.2.0 2022-12-08 =
* Feature - Removed SCO
* Feature - LP Express addition for Lithuanian shipping
* Tweak - Added metadata to create functions
* Tweak - Modified payment methods script queueing
* Tweak - Added notification of invalid phone numbers in admin settings
* Tweak - Support for WordPress version 6.1
* Fix - Double queueing of scripts
* Fix - Pay later causing AJAX null redirect
* Fix - Removed showing country name in checkout methods list
* Fix - Non-numeric shipping cost when calculation shipping class costs

= 3.1.0 2022-07-18 =
* Feature - SCO deprecation notice
* Tweak - Support for WordPress version 6.0
* Fix - Text placement in order view
* Fix - Paylater amount comparison
* Fix - Omniva courier shipping method edit (postcode)

= 3.0.14 2022-04-22 =
* Tweak - Made searchable parcel machine list more user-friendly
* Fix - Parcel machine editing design
* Fix - Missing translations for searchable parcel machines on the admin side
* Fix - Unnecessary deactivation of plugin
* Fix - Warning in admin email trackinglink

= 3.0.13 2022-04-01 =
* Tweak - Fixed using selectWoo for misbehaving themes and plugins that forcibly remove it from the system

= 3.0.12 2022-03-30 =
* Feature - Added option to make parcel machine selection searchable
* Tweak - Improved order tracking functionality
* Tweak - Added notification of an unsuccessful payment
* Tweak - Removed duplicate translation files
* Tweak - Added notification for no enabled payment methods
* Tweak - Added new text translations
* Tweak - Support for WordPress version 5.9
* Tweak - Replaced unique key with primary key
* Tweak - Replaced information schema queries with a version check
* Fix - Updating payment methods with no table
* Fix - Activating MakeCommerce did not create tables in some edge cases
* Fix - Bank-links grouped wrong in widget mode
* Fix - Displaying pay later option that exceeds limit
* Fix - LV and LT translations switched
* Fix - Geolocation not displaying correct payment methods

= 3.0.11 2022-02-25 =
* Fix - Subscription multiuse fix (subscription payments in 3.0.9 and 3.0.10 are done with single use token, which causes automatic renewals to fail)

= 3.0.10 2021-11-10 =
* Feature - Added pay later for LV and BigBank
* Tweak - Improved queries to prevent using reserved MySQL keywords
* Tweak - Don't load settings when not in admin
* Fix - Payment method selection for countries
* Fix - Fresh install banklinks table creation query

= 3.0.9 2021-11-01 =
* Feature - New pay later features on product pages
* Fix - Problems with jquery datepicker 
* Fix - Using shipping address instead of billing address when filled
* Fix - Wrong redirects on failed payments
* Fix - Marking payment completed more than once
* Fix - Ordering payment channels for list view
* Fix - Courier selectbox placement issue
* Fix - Correct javascript injects. Fixes some issues with specific themes and plugins
* Fix - Overwriting more data than needed when using parcelmachine as shipping method
* Fix - Wrong titles on banklinks
* Fix - Deprecated unparenthesized expressions
* Fix - Parcelmachine selectbox size issues
* Fix - SCO database entries automatic cleanup
* Fix - Language update old test- domain names

= 3.0.8 2021-07-30 =
* Feature - Added select under admin to enable onle non-white Itella parcel machines
* Fix - Free shipping coupon caused all shipping methods to be free in SCO

= 3.0.7 2021-07-16 =
* Fix - Double shipping costs in checkout for unassigned shippingclasses

= 3.0.6 2021-07-15 =
* Feature - Added basic implementation for shipping classes
* Feature - Payment country flags sorting
* Fix - usort returning bool deprecated warning
* Fix - parcel machine translation error (Estonian)
* Fix - Fatal error on wrong credentials or disabled shop

= 3.0.5 2021-06-28 =
* Tweak - Added (pretty) display name for payment list
* Fix - SCO payment return URL fix

= 3.0.4 2021-06-17 =
* Feature - PHP 8.0 support
* Feature - Support for different sizes label printing formats
* Feature - Phone number validations for shipping methods
* Tweak - Remove spaces from phonenumber
* Fix - Payment gateway WooCommerce object null error
* Fix - Multisite bug  when plugin is not  network activated
* Fix - Autoloader for case-sensitive filesystems
* Fix - Remove final private methods
* Fix - Order number as reference
* Fix - Payment methods list view issues
* Fix - Translation issues on shipping configuration pages
* Fix - AeroCheckout compatibility

= 3.0.3 2021-03-16 =
* Feature - WordPress 5.7 support
* Feature - WooCommerce 5.1 support
* Tweak - Show error when generating a label does not work
* Fix - Missing tracking link for courier orders using SCO
* Fix - Order notes were in customers language, now they come in site default language
* Fix - Multisite didn't load module because WooCommerce check failed
* Fix - Removed 'None - do not register automatically' option from Omniva courier settings
* Fix - Added icon for other countries selection in checkout and disabled showing other countries option if there are no card payments available
* Fix - Changed order view to display shippingmethod title instead of carrier name
* Fix - Duplicate selectboxes in checkout (parcelmachine and itella courier). Caused problems in some themes
* Fix - Editing Omniva selected parcelmachine in Admin didn't work
* Fix - Fixed Multiparcels/Postbox container HTML issues

= 3.0.2 2021-03-02 =
* Fix - Creditcard only payment method did not display in checkout

= 3.0.1 2021-03-01 =
* Tweak - Disabled automatic updates of the plugin
* Fix - Payment methods display (some options were missing or not working as they should)
* Fix - Don't change Polylang language for email shipping details if MakeCommerce transportation is not used
* Fix - Incorrect translations for shipping and payment method when using WPML
* Fix - Warning, undefined variables when not using banklinks
* Fix - Memory leak when deactivating plugin
* Fix - Missing </style> attribute in SCO when hide shipping methods in cart is activated

= 3.0.0 2021-02-16 =
* Tweak - Refactored all code
* Fix - Pretty payment method name
* Fix - Omniva shipping method validation button didn't work
* Fix - Changes in API, test and merchant url's

= 2.6.2 2020-10-13 =
* Fix - Bug in payment processing

= 2.6.1 2020-10-13 =
* Feature - Added Polylang support
* Feature - Added WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels support
* Feature - Support multiple ToS url's for SimpleCheckout
* Feature - Added payment method metadata to order view
* Tweak - Full translation of English, Russian, Estonian, Latvian and Lithuanian
* Tweak - Carrier country selection for Omniva and DPD shipping methods
* Tweak - Renamed DPD Pickup network to DPD parcel machine
* Fix - Omniva courier transportation registration
* Fix - Replaced deprecated functions
* Fix - Tracking codes for Omniva courier and others

= 2.6.0 2020-09-01 =
* Fix - README file fixes
* Fix - WordPress 5.5 support verified
* Fix - Delivery time was displayed on parcel machine and other not needed delivery types
* Tweak - Translation updates
* Feature - Shipment tracking links (different .tld per shop/delivery country, if possible different language automatically selected)

= 2.5.14 2020-08-10 =
* Fix - Typo

= 2.5.13 2020-08-07 =
* Fix - Emails to customer (e.g. order confirmation) are sent in correct language now
* Fix - language files for et, lv, lt, ru updated

= 2.5.12 2020-07-24 =
* Fix - Return URL language fix in case of WPML language as URL parameter is used

= 2.5.11 2020-07-22 =
* Fix - Return URL language fix
* Fix - payLater notice
* Feature - free shipping coupon support
* Feature - tracking info in emails
* Fix - Shipping registration timing

= 2.5.10 2020-03-25 =
* Fix - Slice banner defaults to no

= 2.5.9 2020-03-22 =
* Feature - Slice banner added

= 2.5.8 2020-03-17 =
* Fix - CC payments using currency other than EUR is now supported
* Fix - WooCommerce 4.0.x  support verified
* Fix - payment update triggers randomized

= 2.5.7 2020-02-06 =
* Fix - Few translation strings fixed
* Fix - Support for sequential order numbers

= 2.5.6 2020-01-29 =
* Fix - Woocommerce 3.9.x support verified
* Fix - Avoid multiple hidden parcel machine divs at checkout page

= 2.5.5 2019-10-23 =
* Fix - mTasku and Pay Later methods support added

= 2.5.4 2019-06-14 =
* Fix - PolyLang / WPML detection further improved

= 2.5.3 2019-06-13 =
* Fix - Changed PolyLang detection, previous one broke WPML

= 2.5.2 2019-04-03 =
* Fix - Omniva tracking code was erraneously replaced on order update

= 2.5.1 2019-02-03 =
* Fix - WPML handling improved, return pages / confirmation emails now in previously selected language

= 2.5.0 2018-12-18 =
* Fix - Shipping tax calculation fix

= 2.4.9 2018-09-18 =
* Fix - Pre/post WC 3.4 handling improved

= 2.4.8 2018-09-17 =
* Fix - Initial configuration links fixed, typo fix

= 2.4.7 2018-09-17 =
* Fix - Initial configuration links fixed

= 2.4.6 2018-06-15 =
* Fix - Mobile number check without area code

= 2.4.5 2018-05-20 =
* Fix - Finnish Nordea logo

= 2.4.4 2018-05-20 =
* Fix - Woocommerce 3.4.X support

= 2.4.3 2018-02-09 =
* Fix - Woocommerce 3.3.x support
* Fix - compatibility with BackupBuddy plugin
* Tweak - Enabled the "enabled" button
* Feature - Simple Checkout enabled by default

= 2.4.2 2017-10-24 =
* Fix - Woocommerce 2.6.x support
* Fix - php errors fixed
* Tweak - Coupon usage for free transportation
* Feature - Ability to change order shipping information in Admin view

= 2.4.1 2017-08-30 =
* Tweak - Disabled autoload of "Shipping destinations"

= 2.4 2017-07-12 =
* Feature - added support for Woocommerce Subscriptions recurring payments (https://woocommerce.com/products/woocommerce-subscriptions/
* Fix - 'Print Parcel labels' button did not work in some cases

= 2.3.1 2017-07-03 =
* Fix - fixing backward support for older Woo version
* Tweak - on payment, removed order state valuation, Woo handles it

= 2.3 2017-05-25 =
* Feature - Omniva Parcel Terminals can be used without Omniva contract (paid via Maksekeskus)
* Feature - Added transport method 'DPD pickup network'
* Feature - Smartpost courier now supports delivery time-window selection
* Feature - SimpleCheckout now supports Woo coupons

= 2.2.7 2017-05-09 =
* Fix - SimpleCheckout now supports product variations

= 2.2.6 2017-05-03 =
* Tweak - SimpleCheckout now respects 'Enable Guest Checkout' setting
* Fix - SimpleCheckout does not include disabled shipping methods
* Fix - another Woo 2.6 - 3.0 compatibility fixes

= 2.2.5 2017-04-27 =
* Fix - another Woo 2.6 - 3.0 compatibility fix

= 2.2.4 2017-04-25 =
* Fix - fixed SimpleCheckout compatibility with Woo 3.0 warnings
* Fix - shipping method tax calculation fix on Simplecheckout

= 2.2.3 2017-04-18 =
* Fix - tax was not correctly applied on SimpleCheckout

= 2.2.2 2017-04-10 =
* Fix - wrong country selector (flag) was not high-lighted at payment method selection on Woo 3.x

= 2.2.1 2017-04-03 =
* Feature - Support for WordPress Multi-Site, aka WPMU, aka Network
* Fix - SimpleCheckout to work without any shipping methods

= 2.2.0 2017-03-21 =
* Feature - SimpleCheckout
* Tweak - translations of error texts
* Tweak - individual sub-modules can be switched on/off on API settings page
* Fix - making refund now works again

= 2.1.5 2017-02-20 =
* Fix - parcel label print resulted in empty page

= 2.1.4 2017-02-17 =
* Fix - parcel-machine related shipping methods were not available at some cases

= 2.1.3 2017-02-17 =
* Feature - new Shipping method - Smartpost courier
* Tweak - parcel terminal name/address is now copied into order delivery address
* Fix - canceling payment process will now return to cart
* Fix - fixed some warning messages popping up

= 2.1.2 2017-01-30 =
* Fix - occasional false warning 'No payment methods for selected country' removed
* Fix - parcel-machine info was missing from order view

= 2.1.1 2017-01-17 =
* Fix - when shop had credit-card only from MK, the card logos were not displayed
* Tweak - fallback to billing address data when registering shipment at carrier and shipment address field is empty 
* Tweak - credit-card dialog now loaded on /order-pay/ page
* Tweak - various translations improvements
* Tweak - code restructured and cleaned

= 2.0.1 2016-11-29 =
* Tweak - product parcel-machine shipment attributes available now for variable product type as well
* Tweak - removed 'shipped via' hook

= 2.0.0 2016-11-24 =
* Feature - Shipping methods now use Woocommerce Shipping Zones, you can define pricing per zone
* Feature - added shipping method "Omniva courier"
* Feature - possibility to hide country selector (flag) at payment methods
* Tweak - shop configuration is updated automatically, daily
* Tweak - solved a conflict with polylang (though not compatible with)
* Fix - improved language handling on card-payment (wpml)
* Fix - improved translations handling of shipping methods (wpml)
* Tweak - improved priority grouping in parcel terminals listing
* Tweak - display weight units at shipment method configurations

= 1.1.7 2016-09-19 =
* Feature - Omniva/Itella shipping methods: added possibility to define free shipping amount per destination country
* Feature - added 'Print parcel label' button to order view
* Feature - added possibility to mark a product as "Shipping free to parcel automat"
* Tweak - simplified payment methods settings dialog (shop name comes from API)
* Tweak - simplified API settings dialog
* Tweak - improved payment options presentation 

= 1.1.5 2016-08-15 =
* Fix - Fixed mising sender data on parcel label print

= 1.1.4 2016-08-15 =
* Fix - Parcel Machine selection verification did not work on some themes

= 1.1.3 2016-08-09 =
* Fix - got rid of waring on order confirmation page 

= 1.1.2 2016-08-04 =
* Fix - fixed Parcel Machine selection bug 

= 1.1.1 2016-07-27 =
* Tweak - improved Parcel Machine dropdown presenation 
* Tweak - added ru and fi transaltion files

= 1.1 2016-07-27 =
* Fix - parcel machine dropdown did not appear if only one shipment method was enabled
* Feature - WPML-based multilingual shop support

= 0.5 =
* initial release


== Upgrade Notice ==
Upgrade to version 2.0.0 requires WooCommerce 2.6 as minimum (because of the Shipping Zones)
https://docs.woocommerce.com/document/setting-up-shipping-zones/

Upgrade to 2.0.0 will create shipping zones based on your current configuration,
will enable the shipping methods in respective zones and will current transfer pricing to those zones.
Please review your shipment zones and setup after the upgrade.
