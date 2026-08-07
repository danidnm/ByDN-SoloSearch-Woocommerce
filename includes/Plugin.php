<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wires the plugin together. Actual logic lives in dedicated classes - this
 * class only decides what runs and when, mirroring suite-magento's module
 * bootstrap (di.xml + Cron/Console entries) rather than a Magento Helper.
 */
class Plugin {

    /**
     * The cron hook is registered unconditionally - a wp-cron.php request is
     * not is_admin(), so gating it the same way as the settings page would
     * mean the callback never runs. Admin-only concerns (settings) stay
     * gated so the storefront never loads admin code. WP-CLI registration is
     * its own guard, since WP_CLI requests aren't is_admin() either.
     */
    public function run() {
        add_action( Cron\FeedSchedule::HOOK, array( '\\Bydn\\SoloSearchWoo\\Cron\\FeedSchedule', 'run' ) );

        if ( is_admin() ) {
            add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_settings_page' ) );
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'solosearch feed', Cli\FeedCommand::class );
        }
    }

    /**
     * Deliberately does not instantiate Admin\Settings until this filter
     * actually fires. Admin\Settings extends \WC_Settings_Page, and that
     * class isn't defined yet at plugins_loaded time (when run() executes) -
     * WooCommerce only loads its admin settings classes later, right before
     * it calls this same filter to collect settings pages. Instantiating
     * eagerly in run() causes a fatal "Class WC_Settings_Page not found".
     *
     * @param \WC_Settings_Page[] $pages
     * @return \WC_Settings_Page[]
     */
    public function register_settings_page( $pages ) {
        $pages[] = new Admin\Settings();
        return $pages;
    }

    /**
     * Runs on plugin activation.
     */
    public static function activate() {
        Cron\FeedSchedule::schedule();
    }

    /**
     * Runs on plugin deactivation, so deactivating always leaves no
     * scheduled event behind.
     */
    public static function deactivate() {
        Cron\FeedSchedule::unschedule();
    }

    /**
     * Shown instead of booting the plugin when WooCommerce isn't active -
     * every part of this plugin assumes WooCommerce's classes exist.
     */
    public static function render_missing_woocommerce_notice() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__( 'SoloSearch for WooCommerce requires WooCommerce to be installed and active.', 'solosearch-for-woocommerce' )
        );
    }
}
