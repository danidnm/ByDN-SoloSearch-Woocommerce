<?php

namespace Bydn\SoloSearchWoo\Cron;

use Bydn\SoloSearchWoo\Config;
use Bydn\SoloSearchWoo\FeedGenerator;
use Bydn\SoloSearchWoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schedules and runs the feed generation cron task, the same way any other
 * WordPress/WooCommerce scheduled task does - no DISABLE_WP_CRON requirement.
 * WordPress's own pseudo-cron fires the request via wp_remote_post() with
 * 'blocking' => false (see wp-includes/cron.php, spawn_cron()), so it does
 * not delay the visitor's own page response. Whether the site runs a real
 * system cron against wp-cron.php or relies on the default pseudo-cron is
 * entirely up to whoever administers it, same as for every other scheduled
 * task on the site - not something this plugin should require or enforce.
 */
class FeedSchedule {

    const HOOK = 'solosearch_woo_generate_feed';

    /**
     * Hooked on plugin activation. Idempotent - wp_next_scheduled() guards
     * against duplicate events if the plugin is deactivated/reactivated.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( self::next_run_timestamp(), 'daily', self::HOOK );
        }
    }

    /**
     * Hooked (in Plugin::run()) on update_option_{Config::OPTION_GENERATION_TIME} -
     * re-registers the event at the newly configured time whenever the admin
     * changes it in Feed Generation settings. Unlike schedule(), not
     * idempotent on purpose: this only runs when the option's value actually
     * changed (that's what the update_option_{option} hook guarantees), so
     * always clearing and re-adding is correct here.
     */
    public static function reschedule() {
        self::unschedule();
        wp_schedule_event( self::next_run_timestamp(), 'daily', self::HOOK );
    }

    /**
     * Hooked on plugin deactivation, so deactivating always leaves no
     * scheduled event behind.
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::HOOK );
    }

    /**
     * Hooked on self::HOOK in Plugin::run(). The event itself stays
     * scheduled even while disabled (same as suite-magento's cron job, which
     * always runs but skips stores with daily_generation_enabled=false) - so
     * turning the setting back on later doesn't require reactivating the
     * plugin to get it scheduled again.
     *
     * @return void
     */
    public static function run() {
        $config = new Config();

        if ( ! $config->isEnabled() || ! $config->isAutoGenerationEnabled() ) {
            Logger::info( 'Feed generation skipped (cron): disabled in settings.' );
            return;
        }

        Logger::info( 'Feed generation started (cron).' );

        ( new FeedGenerator() )->generate();

        Logger::info( 'Feed generation finished (cron).' );
    }

    /**
     * Today at the configured time if that has not passed yet, otherwise
     * tomorrow at that time - the same "next occurrence" semantics a real
     * cron expression would give, computed once since wp_schedule_event()
     * needs a concrete timestamp rather than an expression.
     *
     * @return int Unix timestamp.
     */
    private static function next_run_timestamp(): int {
        $time  = ( new Config() )->getGenerationTime();
        $today = strtotime( 'today ' . $time );

        if ( false === $today ) {
            $today = strtotime( 'today ' . Config::DEFAULT_GENERATION_TIME );
        }

        return $today > time() ? $today : $today + DAY_IN_SECONDS;
    }
}
