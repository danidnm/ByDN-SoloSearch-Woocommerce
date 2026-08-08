<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HTTP client for SoloSearch's public API (suite - the panel, not
 * suite-search/the widget host). Endpoints are added here as SoloSearch's API
 * grows; requestReindex() is the first one - mirrors suite-magento's
 * Model/SoloSearchClient.php.
 */
class SoloSearchClient {

    // Keeps a slow/unreachable SoloSearch API from stalling feed generation -
    // this request runs synchronously right after generate(), inside the
    // same cron run (or WP-CLI invocation) that already did the real work.
    const REQUEST_TIMEOUT_SECONDS = 5;

    /**
     * Asks SoloSearch to re-fetch and reindex this feed immediately, instead
     * of waiting for its own schedule (POST
     * {api_url}/api/v1/search-engines/{uuid}/reindex, Bearer token auth).
     *
     * Best-effort: any failure (missing config, network error, non-200
     * response - e.g. rate limited, no fetchable feeds, invalid token) is
     * logged and swallowed, never thrown. A reindex notification failing must
     * not fail feed generation itself, which already succeeded by the time
     * this runs.
     *
     * @return bool
     */
    public function requestReindex(): bool {
        $config = new Config();

        if ( ! $config->canNotifyReindex() ) {
            Logger::info( __METHOD__ . ': skipped, missing api_token/search_engine_id config.' );
            return false;
        }

        $searchEngineId = $config->getSearchEngineId();
        $url            = $config->getApiUrl() . '/api/v1/search-engines/' . rawurlencode( $searchEngineId ) . '/reindex';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $config->getApiToken(),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( __METHOD__ . ": request to {$url} failed - " . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status ) {
            Logger::error( __METHOD__ . ": unexpected status {$status} from {$url} - " . wp_remote_retrieve_body( $response ) );
            return false;
        }

        Logger::info( __METHOD__ . ": reindex requested successfully ({$url})" );

        return true;
    }
}
