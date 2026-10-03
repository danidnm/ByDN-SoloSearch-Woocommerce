<?php

namespace Bydn\SoloSearchWoo\Cli;

use Bydn\SoloSearchWoo\Config;
use Bydn\SoloSearchWoo\Logger;
use Bydn\SoloSearchWoo\ProductQueue\Sync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * `wp solosearch queue sync` - manual/forced product queue sync, equivalent
 * to `bin/magento solosearch:product-queue:sync`. Registered as a WP-CLI
 * command in Plugin::run() only when WP_CLI is defined, mirrors
 * Cli\FeedCommand.
 */
class ProductQueueSyncCommand {

    /**
     * Sends every pending queued product change to SoloSearch immediately,
     * without waiting for the per-minute cron. Still respects the plugin's
     * master switch and the Enable Real-Time Sync setting (see
     * ProductQueue\Sync::sync()) - same as suite-magento's console command.
     *
     * ## EXAMPLES
     *
     *     wp solosearch queue sync
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function sync( $args, $assoc_args ) {
        if ( ! ( new Config() )->isEnabled() ) {
            Logger::info( 'ProductQueue sync skipped (WP-CLI command): SoloSearch is disabled.' );
            \WP_CLI::warning( 'SoloSearch is disabled (General > Enable SoloSearch). Nothing synced.' );
            return;
        }

        Logger::info( 'ProductQueue sync started (WP-CLI command).' );

        ( new Sync() )->sync();

        Logger::info( 'ProductQueue sync finished (WP-CLI command).' );

        \WP_CLI::success( 'Product queue sync finished.' );
    }
}
