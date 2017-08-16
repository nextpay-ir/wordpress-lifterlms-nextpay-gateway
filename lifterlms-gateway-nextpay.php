<?php
/**
* Plugin Name: LifterLMS Nextpay Gateway
* Plugin URI: https://lifterlms.com/
* Description: Sell LifterLMS courses and memberships using Nextpay Gateway
* Version: 1.0.0
* Author: Nextpay
* Author URI: https://nextpay.ir
* Text Domain: lifterlms-nextpay
* Domain Path: /languages
* License:     GPLv2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Requires at least: 4.2
* Tested up to: 4.5.3
*
* @package 		LifterLMS Nextpay
* @category 	Core
* @author 		LifterLMS
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Restrict direct access

if ( ! class_exists( 'LifterLMS_Nextpay') ) :

final class LifterLMS_Nextpay {

	/**
	 * Plugin Version
	 */
	public $version = '1.0.0';

	/**
	 * Singleton class instance
	 * @var  obj
	 * @since  1.0.0
	 * @version  1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main Instance of LifterLMS_Nextpay
	 * Ensures only one instance of LifterLMS_Nextpay is loaded or can be loaded.
	 * @see LLMS_Gateway_Nextpay()
	 * @return LifterLMS_Nextpay - Main instance
	 * @since  1.0.0
	 * @version  1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 * @since  1.0.0
	 * @version  1.0.0
	 * @return  void
	 */
	private function __construct() {

		$this->define_constants();

		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );

	}

	/**
	 * Define plugin constants
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	private function define_constants() {
		// LLMS Nextpay Plugin File
		if ( ! defined( 'LLMS_Nextpay_PLUGIN_FILE' ) ) {
			define( 'LLMS_Nextpay_PLUGIN_FILE', __FILE__ );
		}

		// LLMS Convert Kit Plugin Directory
		if ( ! defined( 'LLMS_Nextpay_PLUGIN_DIR' ) ) {
			define( 'LLMS_Nextpay_PLUGIN_DIR', WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__) ) . '/');
		}
	}

	/**
	 * Initialize, require, add hooks & filters
	 * @return  void
	 * @since  1.0.0
	 * @version  1.0.0
	 */
	public function init() {

		// can only function with LifterLMS 3.0.0 or later
		if ( function_exists( 'LLMS' ) && version_compare( '3.0.0-alpha', LLMS()->version, '<=' ) ) {

			add_action( 'lifterlms_settings_save_checkout', array( $this, 'maybe_check_reference_transactions' ) );
			add_filter( 'lifterlms_payment_gateways', array( $this, 'register_gateway' ), 10, 1 );

			require_once 'includes/class.llms.payment.gateway.nextpay.php';
		}

	}

	/**
	 * When saving the Checkout tab, check reference transactions if the check button was clicked
	 * @return   void
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public function maybe_check_reference_transactions() {

		$gateways = LLMS()->payment_gateways();
		$g = $gateways->get_gateway_by_id( 'Nextpay' );

		$check = false;

		// if live creds have changed we should check ref transactions on the new creds
		if ( isset( $_POST[ $g->get_option_name( 'api_key' ) ] ) && $g->get_api_key() !== $_POST[ $g->get_option_name( 'api_key' ) ] ) {

			$check = true;

		} elseif ( isset( $_POST['llms_gateway_Nextpay_check_ref_trans'] ) ) {

			$check = true;

		}

		// checkem
		if ( $check ) {

			// wait until after settings are saved so that the check will always be run with the credentials that we're just submitted
			add_action( 'lifterlms_settings_saved', array( $g, 'check_reference_transactions' ) );

		}

	}

	/**
	 * Register the gateway with LifterLMS
	 * @param   array $gateways array of currently registered gateways
	 * @return  array
	 * @since  1.0.0
	 * @version  1.0.0
	 */
	public function register_gateway( $gateways ) {

		$gateways[] = 'LLMS_Payment_Gateway_Nextpay';

		return $gateways;

	}

}

endif;

/**
 * Returns the main instance of LifterLMS_Nextpay
 * @return LifterLMS
 * @since  1.0.0
 * @version  1.0.0
 */
function LLMS_Gateway_Nextpay() {
	return LifterLMS_Nextpay::instance();
}
return LLMS_Gateway_Nextpay();
