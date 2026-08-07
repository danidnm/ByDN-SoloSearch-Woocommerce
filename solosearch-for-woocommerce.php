<?php
/**
 * Plugin Name: SoloSearch for WooCommerce
 * Plugin URI: https://solosearch.app
 * Description: Generates a product feed for SoloSearch and embeds the search widget in your store.
 * Version: 0.1.0
 * Author: SoloSearch
 * Author URI: https://solosearch.app
 * License: Proprietary
 * Text Domain: solosearch-for-woocommerce
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SOLOSEARCH_WOO_PLUGIN_DIR' ) ) {
    define( 'SOLOSEARCH_WOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SOLOSEARCH_WOO_PLUGIN_URL' ) ) {
    define( 'SOLOSEARCH_WOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SOLOSEARCH_WOO_VERSION' ) ) {
    define( 'SOLOSEARCH_WOO_VERSION', '0.1.0' );
}

/**
 * PSR-4 autoloader for Bydn\SoloSearchWoo\, mapped to includes/. The plugin has
 * no external Composer dependencies, so a tiny autoloader avoids shipping and
 * keeping a vendor/ directory in sync just to load our own classes - unlike
 * Composer's generated autoloader, this needs no build step, which matters
 * here because (unlike suite-magento, where Magento's own component registrar
 * reads composer.json directly) WordPress has no built-in PSR-4 support, so a
 * real Composer-based plugin would have to ship vendor/autoload.php in the
 * distributed zip. composer.json's autoload map is kept as metadata for
 * IDE/tooling support and must stay in sync with this by hand.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'Bydn\\SoloSearchWoo\\';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $path     = SOLOSEARCH_WOO_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $path ) ) {
        require $path;
    }
} );

register_activation_hook( __FILE__, array( '\\Bydn\\SoloSearchWoo\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\Bydn\\SoloSearchWoo\\Plugin', 'deactivate' ) );

/**
 * Boot on plugins_loaded (not immediately) so the WooCommerce-active check,
 * and anything relying on WC_Settings_Page/WC_Product, only runs once
 * WooCommerce itself has had a chance to load.
 */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', array( '\\Bydn\\SoloSearchWoo\\Plugin', 'render_missing_woocommerce_notice' ) );
        return;
    }

    $plugin = new \Bydn\SoloSearchWoo\Plugin();
    $plugin->run();
} );
