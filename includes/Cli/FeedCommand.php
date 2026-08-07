<?php

namespace Bydn\SoloSearchWoo\Cli;

use Bydn\SoloSearchWoo\Config;
use Bydn\SoloSearchWoo\FeedGenerator;
use Bydn\SoloSearchWoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * `wp solosearch feed generate` - manual/forced feed generation, equivalent
 * to `bin/magento solosearch:feed:generate`. Registered as a WP-CLI command
 * in Plugin::run() only when WP_CLI is defined, so this class is never
 * touched on a normal web request.
 */
class FeedCommand {

    /**
     * Generates the SoloSearch product feed immediately, regardless of the
     * scheduled cron task or "Enable automatic generation". Still respects
     * the plugin's master switch ("Enable SoloSearch" in General settings) -
     * same as suite-magento's console command, which calls
     * generateForStoreIfEnabled() and so is gated by the general Enable flag
     * but not by daily_generation_enabled.
     *
     * ## EXAMPLES
     *
     *     wp solosearch feed generate
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function generate( $args, $assoc_args ) {
        if ( ! ( new Config() )->isEnabled() ) {
            Logger::info( 'Feed generation skipped (WP-CLI command): SoloSearch is disabled.' );
            \WP_CLI::warning( 'SoloSearch is disabled (General > Enable SoloSearch). Nothing generated.' );
            return;
        }

        Logger::info( 'Feed generation started (WP-CLI command).' );

        ( new FeedGenerator() )->generate();

        Logger::info( 'Feed generation finished (WP-CLI command).' );

        \WP_CLI::success( 'Feed generated.' );
    }
}
