<?php

/**
 * Plugin Name: SA Hosted Checkout for WooCommerce
 * Plugin URI: https://www.sleekalgo.com
 * Description: Increase conversions by using Sleek Checkout on your WooCommerce website. Let your customers pay with confidence using highly optimized, Stripe hosted checkout. Setup in a few minutes. All configuration options are available test-01.
 * Version: 1.0.0
 * Requires at least: 5.1
 * Requires PHP: 5.6
 * Author: Sleek Algo
 * Author URI: https://www.sleekalgo.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sa-hosted-checkout-for-woocommerce
 * Domain Path: /languages
 * 
 */

/* Exit if accessed directly. */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'get_plugin_data' ) ) {
    /**
    * load plugins class.
    */
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

$plugin_data = get_plugin_data( __FILE__ );
$wp_upload_dir = wp_get_upload_dir();
/* Define constants. */
!defined( 'SAHCFWC_VERSION' ) && define( 'SAHCFWC_VERSION', $plugin_data['Version'] );
!defined( 'SAHCFWC_BASE' ) && define( 'SAHCFWC_BASE', basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) );
!defined( 'SAHCFWC_TEXT_DOMAIN' ) && define( 'SAHCFWC_TEXT_DOMAIN', $plugin_data['TextDomain'] );
!defined( 'SAHCFWC_TEXT_DOMAIN_PATH' ) && define( 'SAHCFWC_TEXT_DOMAIN_PATH', dirname( SAHCFWC_BASE ) . $plugin_data['DomainPath'] );
!defined( 'SAHCFWC_FILE' ) && define( 'SAHCFWC_FILE', __FILE__ );
!defined( 'SAHCFWC_DIR' ) && define( 'SAHCFWC_DIR', plugin_dir_path( SAHCFWC_FILE ) );
!defined( 'SAHCFWC_URL' ) && define( 'SAHCFWC_URL', plugins_url( '', SAHCFWC_FILE ) );
!defined( 'SAHCFWC_URL_ASSETS_FRONTEND' ) && define( 'SAHCFWC_URL_ASSETS_FRONTEND', SAHCFWC_URL . '/assets/frontend' );
!defined( 'SAHCFWC_URL_ASSETS_FRONTEND_CSS' ) && define( 'SAHCFWC_URL_ASSETS_FRONTEND_CSS', SAHCFWC_URL_ASSETS_FRONTEND . '/css' );
!defined( 'SAHCFWC_URL_ASSETS_FRONTEND_JS' ) && define( 'SAHCFWC_URL_ASSETS_FRONTEND_JS', SAHCFWC_URL_ASSETS_FRONTEND . '/js' );
!defined( 'SAHCFWC_URL_ASSETS_FRONTEND_IMAGES' ) && define( 'SAHCFWC_URL_ASSETS_FRONTEND_IMAGES', SAHCFWC_URL_ASSETS_FRONTEND . '/images' );
!defined( 'SAHCFWC_URL_ASSETS_BACKEND' ) && define( 'SAHCFWC_URL_ASSETS_BACKEND', SAHCFWC_URL . '/assets/backend' );
!defined( 'SAHCFWC_URL_ASSETS_BACKEND_CSS' ) && define( 'SAHCFWC_URL_ASSETS_BACKEND_CSS', SAHCFWC_URL_ASSETS_BACKEND . '/css' );
!defined( 'SAHCFWC_URL_ASSETS_BACKEND_JS' ) && define( 'SAHCFWC_URL_ASSETS_BACKEND_JS', SAHCFWC_URL_ASSETS_BACKEND . '/js' );
!defined( 'SAHCFWC_URL_ASSETS_BACKEND_IMAGES' ) && define( 'SAHCFWC_URL_ASSETS_BACKEND_IMAGES', SAHCFWC_URL_ASSETS_BACKEND . '/images' );
!defined( 'SAHCFWC_URL_ASSETS_BACKEND_LOCAL_PAYMENT_METHODS_IMAGES' ) && define( 'SAHCFWC_URL_ASSETS_BACKEND_LOCAL_PAYMENT_METHODS_IMAGES', SAHCFWC_URL . '/assets/local-payment-methods' );
!defined( 'SAHCFWC_PATH' ) && define( 'SAHCFWC_PATH', dirname( SAHCFWC_FILE ) );
    
/**
 * PSR-4 Composer Autoloader
 */
if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
    /**
     * load plugin  auotoload class.
     */
    $loader = (require_once SAHCFWC_DIR . '/vendor/autoload.php');
    // Log the class map for debugging
    // $classMap = $loader->getClassMap();
}

function sahcfwc_plugin_init() {
    $plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';
    if ( in_array( $plugin_path, wp_get_active_and_valid_plugins() ) ) {
        \SAHCFWC\Bootstrap\App::get_instance();
        do_action( 'sahcfwc_plugin_loaded' );
    } else {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

function register_plugin_row_meta_links(
    $plugin_meta,
    $plugin_file,
    $plugin_data,
    $status
) {
    if ( plugin_basename( __FILE__ ) === $plugin_file ) {
        $setting_url = admin_url( 'admin.php' ) . '?page=scpp-dashboard';
        $plugin_meta[] = '<a href="' . $setting_url . '" target="_blank">' . esc_html__( 'Settings', 'sa-hosted-checkout-for-woocommerce' ) . '</a>';
        $plugin_meta[] = '<a href="https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/" target="_blank">' . esc_html__( 'Documentation', 'sa-hosted-checkout-for-woocommerce' ) . '</a>';
        $plugin_meta[] = '<a href="https://www.sleekalgo.com/contact-us/" target="_blank">' . esc_html__( 'Support', 'sa-hosted-checkout-for-woocommerce' ) . '</a>';
    }
    return $plugin_meta;
}

add_filter(
    'plugin_row_meta',
    'register_plugin_row_meta_links',
    10,
    4
);
add_action( 'plugins_loaded', 'sahcfwc_plugin_init', 30 );
