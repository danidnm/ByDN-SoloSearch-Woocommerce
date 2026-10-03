<?php

namespace Bydn\SoloSearchWoo\Cron;

use Bydn\SoloSearchWoo\ProductQueue\Cleaner;
use Bydn\SoloSearchWoo\ProductQueue\Sync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schedules and runs the two product queue cron tasks: the real-time sync
 * (as close to every minute as WP-Cron allows) and the history cleanup
 * (daily, off-peak). Same pseudo-cron reasoning as Cron\FeedSchedule - no
 * DISABLE_WP_CRON requirement - but worth calling out here specifically:
 * WordPress's default pseudo-cron only fires on a page load, so a per-minute
 * cadence is far more sensitive to site traffic than the once-daily feed
 * job is. A low-traffic store may see real-time sync fire noticeably less
 * often than every minute. Documented, not enforced - whether a site runs a
 * real system cron against wp-cron.php is up to whoever administers it, same
 * as FeedSchedule.
 */
class ProductQueueSchedule {

    const SYNC_HOOK  = 'solosearch_woo_sync_product_queue';
    const CLEAN_HOOK = 'solosearch_woo_clean_product_queue';
    const INTERVAL   = 'solosearch_woo_every_minute';

    /**
     * Hooked on the `cron_schedules` filter in Plugin::run() - WP-Cron has
     * no native per-minute schedule, only the built-in hourly/twicedaily/daily.
     *
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public static function register_interval( $schedules ) {
        $schedules[ self::INTERVAL ] = array(
            'interval' => 60,
            'display'  => __( 'Every minute (SoloSearch)', 'solosearch-for-woocommerce' ),
        );

        return $schedules;
    }

    /**
     * Hooked on plugin activation. Idempotent - wp_next_scheduled() guards
     * against duplicate events if the plugin is deactivated/reactivated.
     *
     * @return void
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
            wp_schedule_event( time(), self::INTERVAL, self::SYNC_HOOK );
        }

        if ( ! wp_next_scheduled( self::CLEAN_HOOK ) ) {
            wp_schedule_event( self::next_cleanup_timestamp(), 'daily', self::CLEAN_HOOK );
        }
    }

    /**
     * Hooked on plugin deactivation, so deactivating always leaves no
     * scheduled event behind.
     *
     * @return void
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::SYNC_HOOK );
        wp_clear_scheduled_hook( self::CLEAN_HOOK );
    }

    /**
     * Hooked on self::SYNC_HOOK in Plugin::run().
     *
     * @return void
     */
    public static function run_sync() {
        ( new Sync() )->sync();
    }

    /**
     * Hooked on self::CLEAN_HOOK in Plugin::run().
     *
     * @return void
     */
    public static function run_cleanup() {
        ( new Cleaner() )->cleanup();
    }

    /**
     * Today at 03:00 site time if that has not passed yet, otherwise
     * tomorrow at 03:00 - off-peak, mirrors suite-magento's cleanup cron
     * (`0 3 * * *`). Not admin-configurable, unlike the feed generation time.
     *
     * @return int Unix timestamp.
     */
    private static function next_cleanup_timestamp(): int {
        $today = strtotime( 'today 03:00' );

        if ( false === $today ) {
            $today = time();
        }

        return $today > time() ? $today : $today + DAY_IN_SECONDS;
    }
}
