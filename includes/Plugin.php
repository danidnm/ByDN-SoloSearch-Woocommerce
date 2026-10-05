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

        // wp_footer only ever fires on a real front-end page render, so this
        // is effectively already scoped to the storefront without needing an
        // explicit !is_admin() check.
        add_action( 'wp_footer', array( '\\Bydn\\SoloSearchWoo\\Frontend\\WidgetEmbed', 'render' ) );

        // Reschedule whenever the generation time changes. Both hooks are
        // needed: update_option_{option} only fires once the option row
        // already exists in the DB - the very first save of a brand new
        // option goes through add_option() instead, firing add_option_{option}.
        // activate() seeds the option so this should always be the update_option
        // path in practice, but both are registered so it's correct regardless.
        add_action( 'update_option_' . Config::OPTION_GENERATION_TIME, array( '\\Bydn\\SoloSearchWoo\\Cron\\FeedSchedule', 'reschedule' ) );
        add_action( 'add_option_' . Config::OPTION_GENERATION_TIME, array( '\\Bydn\\SoloSearchWoo\\Cron\\FeedSchedule', 'reschedule' ) );

        // Product queue: WP-Cron has no native per-minute schedule, so the
        // interval itself has to be registered (unconditionally - a cron
        // request isn't is_admin() either, same reasoning as the hook above).
        add_filter( 'cron_schedules', array( '\\Bydn\\SoloSearchWoo\\Cron\\ProductQueueSchedule', 'register_interval' ) );
        add_action( Cron\ProductQueueSchedule::SYNC_HOOK, array( '\\Bydn\\SoloSearchWoo\\Cron\\ProductQueueSchedule', 'run_sync' ) );
        add_action( Cron\ProductQueueSchedule::CLEAN_HOOK, array( '\\Bydn\\SoloSearchWoo\\Cron\\ProductQueueSchedule', 'run_cleanup' ) );

        // Product queue: detect changes to enqueue for real-time sync. See
        // ProductQueue\ChangeNotifier's docblock for why a single hook
        // covers normal saves and bulk/quick edit alike here, unlike
        // suite-magento which needs a dedicated observer for mass updates.
        add_action( 'woocommerce_product_object_updated_props', array( '\\Bydn\\SoloSearchWoo\\ProductQueue\\ChangeNotifier', 'product_changed' ), 10, 2 );
        add_action( 'woocommerce_trash_product', array( '\\Bydn\\SoloSearchWoo\\ProductQueue\\ChangeNotifier', 'product_deleted' ) );
        add_action( 'woocommerce_delete_product', array( '\\Bydn\\SoloSearchWoo\\ProductQueue\\ChangeNotifier', 'product_deleted' ) );

        if ( is_admin() ) {
            add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_settings_page' ) );

            // Unlike Admin\Settings, FieldMappingField doesn't extend anything
            // from WooCommerce, so there's no WC_Settings_Page-style timing
            // hazard instantiating it directly here.
            ( new Admin\FieldMappingField() )->register();
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'solosearch feed', Cli\FeedCommand::class );
            \WP_CLI::add_command( 'solosearch queue', Cli\ProductQueueSyncCommand::class );
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
     * Runs on plugin activation. Seeds the generation time option so it
     * exists in the DB from the start - see the comment in run() on why that
     * matters for reschedule() firing correctly on the very first change.
     */
    public static function activate() {
        add_option( Config::OPTION_GENERATION_TIME, Config::DEFAULT_GENERATION_TIME );
        Cron\FeedSchedule::schedule();

        ProductQueue\Schema::install();

        // Activation runs before run()'s plugins_loaded-gated add_filter() below ever fires
        // (the plugin was inactive a moment ago, so its main file wasn't loaded during this
        // request's normal plugins_loaded pass) - without registering the interval here too,
        // wp_schedule_event() below would silently no-op on an unrecognised schedule name and
        // the per-minute sync would never actually get scheduled.
        add_filter( 'cron_schedules', array( '\\Bydn\\SoloSearchWoo\\Cron\\ProductQueueSchedule', 'register_interval' ) );
        Cron\ProductQueueSchedule::schedule();
    }

    /**
     * Runs on plugin deactivation, so deactivating always leaves no
     * scheduled event behind.
     */
    public static function deactivate() {
        Cron\FeedSchedule::unschedule();
        Cron\ProductQueueSchedule::unschedule();
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
