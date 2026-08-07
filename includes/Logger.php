<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Thin wrapper around WooCommerce's own logger (wc_get_logger()), writing to
 * a dedicated 'solosearch' log source - viewable under WooCommerce > Status >
 * Logs in the admin, no filesystem/SSH access needed. Mirrors suite-magento's
 * dedicated var/log/bydn_solosearch.log (its own virtualType in di.xml).
 */
class Logger {

    const SOURCE = 'solosearch';

    public static function info( string $message ): void {
        wc_get_logger()->info( $message, array( 'source' => self::SOURCE ) );
    }

    public static function error( string $message ): void {
        wc_get_logger()->error( $message, array( 'source' => self::SOURCE ) );
    }
}
