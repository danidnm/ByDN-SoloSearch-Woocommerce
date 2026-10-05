<?php

namespace Bydn\SoloSearchWoo\ProductQueue;

use Bydn\SoloSearchWoo\Config;
use Bydn\SoloSearchWoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Removes finished (success/error) product queue rows older than the
 * configured retention window (see Config::getQueueRetentionDays()). Pending
 * rows are never touched. Mirrors suite-magento's Model/ProductQueueCleaner.php.
 */
class Cleaner {

    /**
     * @return void
     */
    public function cleanup() {
        $retention_days = ( new Config() )->getQueueRetentionDays();
        $cutoff         = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        $deleted = ( new Repository() )->delete_finished_older_than( $cutoff );

        Logger::info( sprintf( 'ProductQueue cleaner: removed %d finished entr%s older than %d day(s).', $deleted, 1 === $deleted ? 'y' : 'ies', $retention_days ) );
    }
}
