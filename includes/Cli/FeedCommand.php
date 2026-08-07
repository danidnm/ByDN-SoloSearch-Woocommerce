<?php

namespace Bydn\SoloSearchWoo\Cli;

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
     * scheduled cron task or whether feed generation is enabled in settings.
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
        Logger::info( 'Feed generation started (WP-CLI command).' );

        ( new FeedGenerator() )->generate();

        Logger::info( 'Feed generation finished (WP-CLI command).' );

        \WP_CLI::success( 'Feed generated.' );
    }
}
