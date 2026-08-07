<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single source of truth for reading plugin settings - nothing else in the
 * plugin should call get_option() directly for our own option keys. Mirrors
 * suite-magento's Helper/Config.php: one place that knows every option name
 * and its default, everything else asks this class instead.
 *
 * Feed Generation and Field Mapping settings are added here once the feed
 * generator itself exists - defining their shape ahead of that logic would
 * just be guessing.
 */
class Config {

    const OPTION_ENABLE           = 'solosearch_woo_enable';
    const OPTION_API_URL          = 'solosearch_woo_api_url';
    const OPTION_API_TOKEN        = 'solosearch_woo_api_token';
    const OPTION_WIDGET_ENABLE    = 'solosearch_woo_widget_enable';
    const OPTION_SEARCH_ENGINE_ID = 'solosearch_woo_widget_search_engine_id';
    const OPTION_SCRIPT_URL       = 'solosearch_woo_widget_script_url';
    const OPTION_INPUT_SELECTOR   = 'solosearch_woo_widget_input_selector';
    const OPTION_LOCALE           = 'solosearch_woo_widget_locale';
    const OPTION_TEMPLATE_SET_ID  = 'solosearch_woo_widget_template_set_id';

    const DEFAULT_API_URL    = 'https://app.solosearch.app';
    const DEFAULT_SCRIPT_URL = 'https://search.solosearch.app/widget.js';

    /**
     * Feed generation master switch.
     */
    public function isEnabled(): bool {
        return 'yes' === get_option( self::OPTION_ENABLE, 'no' );
    }

    /**
     * Base URL of the suite panel API (reindex endpoint lives under this).
     */
    public function getApiUrl(): string {
        $url = get_option( self::OPTION_API_URL, self::DEFAULT_API_URL );
        return $url ? untrailingslashit( (string) $url ) : self::DEFAULT_API_URL;
    }

    /**
     * Bearer token for the search engine's reindex endpoint - same token
     * shown on the engine's General tab in the SoloSearch panel, under API
     * Token. Stored in plain text (WordPress has no built-in secret
     * encryption comparable to Magento's Backend\Encrypted), same as this
     * author's other plugins store API keys.
     */
    public function getApiToken(): string {
        return (string) get_option( self::OPTION_API_TOKEN, '' );
    }

    public function isWidgetEnabled(): bool {
        return 'yes' === get_option( self::OPTION_WIDGET_ENABLE, 'no' );
    }

    public function getSearchEngineId(): string {
        return (string) get_option( self::OPTION_SEARCH_ENGINE_ID, '' );
    }

    public function getScriptUrl(): string {
        $url = get_option( self::OPTION_SCRIPT_URL, self::DEFAULT_SCRIPT_URL );
        return $url ? (string) $url : self::DEFAULT_SCRIPT_URL;
    }

    /**
     * CSS selector for the shop's own search field. Empty means "let the
     * widget use its own default (#search)" - never invent a value here.
     */
    public function getInputSelector(): string {
        return (string) get_option( self::OPTION_INPUT_SELECTOR, '' );
    }

    /**
     * Empty means "use the engine's own default language" - never invent a
     * value here, the widget script omits the data-locale attribute entirely
     * when this is blank.
     */
    public function getLocale(): string {
        return (string) get_option( self::OPTION_LOCALE, '' );
    }

    /**
     * Empty means "use the engine's active/default template set" - advanced,
     * most stores never set this.
     */
    public function getTemplateSetId(): string {
        return (string) get_option( self::OPTION_TEMPLATE_SET_ID, '' );
    }

    /**
     * True only when everything the reindex API call needs is actually
     * configured - mirrors Magento's SoloSearchClient silently skipping the
     * notification (not erroring) whenever config is incomplete.
     */
    public function canNotifyReindex(): bool {
        return '' !== $this->getApiToken() && '' !== $this->getSearchEngineId();
    }
}
