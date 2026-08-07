<?php

namespace Bydn\SoloSearchWoo\Cron;

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
     *
     * 03:00 server time is a hardcoded default for now - it becomes
     * configurable (like Magento's GenerationTime) once the Feed Generation
     * settings section exists.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 03:00:00' ), 'daily', self::HOOK );
        }
    }

    /**
     * Hooked on plugin deactivation, so deactivating always leaves no
     * scheduled event behind.
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::HOOK );
    }

    /**
     * Hooked on self::HOOK in Plugin::run().
     *
     * @return void
     */
    public static function run() {
        Logger::info( 'Feed generation started (cron).' );

        ( new FeedGenerator() )->generate();

        Logger::info( 'Feed generation finished (cron).' );
    }
}
