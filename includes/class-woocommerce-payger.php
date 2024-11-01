<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.widgilabs.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Gateway_Payger
 * @subpackage Woocommerce_Gateway_Payger/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woocommerce_Gateway_Payger
 * @subpackage Woocommerce_Gateway_Payger/includes
 * @author     WidgiLabs <contact@widgilabs.com>
 */
class Woocommerce_Payger {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WOOCOMERCE_GATEWAY_PAYGER' ) ) {
			$this->version = WOOCOMERCE_GATEWAY_PAYGER;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'woocommerce-gateway-payger';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woocommerce_Payger_i18n. Defines internationalization functionality.
	 * - Woocommerce_Payger_Admin. Defines all hooks for the admin area.
	 * - Woocommerce_Payger_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {


		// Checks if Woocommerce is active, plugin must have woocommerce to function
		try {
			if ( ! in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins' ), true ) ) {
				throw new Exception( __( 'WooCommerce Gateway Payger requires WooCommerce to be activated', 'payger' ), 2 );
			}
		} catch ( Exception $e ) {
			update_option( 'payger_warning_message', $e->getMessage() );
			add_action( 'admin_notices', array( $this, 'show_install_warning' ) );
		}

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woocommerce-payger-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-woocommerce-payger-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-woocommerce-payger-public.php';


	}


	/**
	 * Adds error message when woocommerce is not
	 * activated but we have this plugin active.
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function show_install_warning() {
		$message = get_option( 'payger_warning_message', '' );
		if ( ! empty( $message ) ) {
			?>
			<div class="error fade">
				<p>
					<strong><?php echo esc_html( $message ); ?></strong>
				</p>
			</div>
		<?php
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woocommerce_Payger_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Woocommerce_Payger_i18n();

		add_action( 'plugins_loaded', array( $plugin_i18n, 'load_plugin_textdomain' ) );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woocommerce_Payger_Admin( $this->get_plugin_name(), $this->get_version() );

		add_action( 'admin_enqueue_scripts',        array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts',        array( $plugin_admin, 'enqueue_scripts' ) );

		add_action( 'plugins_loaded',               array( $plugin_admin, 'init_gateway') );
		add_filter( 'woocommerce_payment_gateways', array( $plugin_admin, 'add_payger_gateway_class') );

		add_filter( 'cron_schedules',               array( $plugin_admin, 'payger_intervals') );
		add_action( 'payger_check_payment',         array( $plugin_admin, 'check_payment' ), 10, 2 );


		add_action( 'wp_ajax_payger_get_quote',        array( $plugin_admin, 'get_quote' ) );
		add_action( 'wp_ajax_nopriv_payger_get_quote', array( $plugin_admin, 'get_quote' ) );

		add_action( 'wp_ajax_check_order_status',        array( $plugin_admin, 'check_order_status' ) );
		add_action( 'wp_ajax_nopriv_check_order_status', array( $plugin_admin, 'check_order_status' ) );

		add_action( 'woocommerce_email_before_order_table', array( $plugin_admin, 'update_email_instructions' ), 10, 3 );

		//FIXME pode não estar inicializado quando o callback corre...
		add_action( 'woocommerce_api_wc_gateway_payger', array( $plugin_admin, 'check_payger_response' ) );

		add_action( 'woocommerce_cancelled_order', array( $plugin_admin, 'cancel_order' ), 10, 1 );

		add_action( 'woocommerce_update_options_payment_gateways_payger_gateway' , array( $plugin_admin, 'process_admin_options' ) );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Woocommerce_Payger_Public( $this->get_plugin_name(), $this->get_version() );

		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );

		add_action( 'woocommerce_thankyou_payger_gateway', array( $plugin_public, 'update_thank_you' ), 10, 1 );
		add_action( 'woocommerce_view_order', array( $plugin_public, 'update_thank_you' ), 10, 1);

		add_filter( 'woocommerce_email_classes', array( $plugin_public, 'add_payger_emails' ), 10, 1 );

	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
