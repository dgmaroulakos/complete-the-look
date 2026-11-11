<?php
/**
 * Plugin Name:       Woo Complete the Look
 * Plugin URI:        https://github.com/dgmaroulakos/complete-the-look
 * Description:       Link complementary products to any WooCommerce product and display a "Complete the Look" section on the single product page.
 * Version:           1.6.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Complete the Look
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-complete-the-look
 * Domain Path:       /languages
 * Update URI:        https://github.com/dgmaroulakos/complete-the-look
 * WC requires at least: 5.0
 * WC tested up to:   9.0
 *
 * @package WooCompleteTheLook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap class.
 */
final class Woo_Complete_The_Look {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.6.4';

	/**
	 * Singleton instance.
	 *
	 * @var Woo_Complete_The_Look|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Woo_Complete_The_Look
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Define plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		if ( ! defined( 'CTM_VERSION' ) ) {
			define( 'CTM_VERSION', self::VERSION );
		}

		if ( ! defined( 'CTM_PLUGIN_FILE' ) ) {
			define( 'CTM_PLUGIN_FILE', __FILE__ );
		}

		if ( ! defined( 'CTM_PLUGIN_DIR' ) ) {
			define( 'CTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'CTM_PLUGIN_URL' ) ) {
			define( 'CTM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		if ( ! defined( 'CTM_META_KEY' ) ) {
			define( 'CTM_META_KEY', '_ct_l_product_ids' );
		}

		if ( ! defined( 'CTM_LOOK_IMAGE_META' ) ) {
			define( 'CTM_LOOK_IMAGE_META', '_ct_l_look_image_id' );
		}

		if ( ! defined( 'CTM_COLOR_PRESETS_META' ) ) {
			define( 'CTM_COLOR_PRESETS_META', '_ct_l_color_presets' );
		}
	}

	/**
	 * Include required class files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once CTM_PLUGIN_DIR . 'includes/class-ctm-helpers.php';
		require_once CTM_PLUGIN_DIR . 'includes/class-ctm-updates.php';
		require_once CTM_PLUGIN_DIR . 'includes/class-ctm-settings.php';
		require_once CTM_PLUGIN_DIR . 'includes/class-ctm-admin.php';
		require_once CTM_PLUGIN_DIR . 'includes/class-ctm-frontend.php';
	}

	/**
	 * Initialize plugin after WooCommerce is loaded.
	 *
	 * @return void
	 */
	public function init() {
		// GitHub updates work even when WooCommerce is inactive.
		CTM_Updates::instance();

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		load_plugin_textdomain(
			'woo-complete-the-look',
			false,
			dirname( plugin_basename( CTM_PLUGIN_FILE ) ) . '/languages'
		);

		CTM_Settings::instance();
		CTM_Admin::instance();
		CTM_Frontend::instance();
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				CTM_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Admin notice when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'Woo Complete the Look requires WooCommerce to be installed and active.',
			'woo-complete-the-look'
		);
		echo '</p></div>';
	}
}

/**
 * Returns the main instance of Woo_Complete_The_Look.
 *
 * @return Woo_Complete_The_Look
 */
function woo_complete_the_look() {
	return Woo_Complete_The_Look::instance();
}

woo_complete_the_look();
