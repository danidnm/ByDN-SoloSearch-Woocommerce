<?php

namespace Bydn\SoloSearchWoo\Admin;

use Bydn\SoloSearchWoo\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds a "SoloSearch" tab under WooCommerce > Settings, using WooCommerce's
 * own settings framework (WC_Settings_Page) instead of a generic WP Settings
 * API page - this is how WooCommerce extensions are expected to add settings,
 * and it gets tab/section navigation, field rendering, saving and
 * sanitisation for free instead of us reimplementing all of that.
 *
 * Sections mirror suite-magento's system.xml groups: General, Feed
 * Generation, Field Mapping and Widget Embed.
 */
class Settings extends \WC_Settings_Page {

    public function __construct() {
        $this->id    = 'solosearch';
        $this->label = __( 'SoloSearch', 'solosearch-for-woocommerce' );

        parent::__construct();
    }

    protected function get_own_sections() {
        return array(
            ''        => __( 'General', 'solosearch-for-woocommerce' ),
            'feed'    => __( 'Feed Generation', 'solosearch-for-woocommerce' ),
            'mapping' => __( 'Field Mapping', 'solosearch-for-woocommerce' ),
            'widget'  => __( 'Widget Embed', 'solosearch-for-woocommerce' ),
        );
    }

    protected function get_settings_for_default_section() {
        return array(
            array(
                'title' => __( 'General', 'solosearch-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'solosearch_general_options',
            ),
            array(
                'title'    => __( 'Enable SoloSearch', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_ENABLE,
                'type'     => 'checkbox',
                'default'  => 'no',
                'desc_tip' => __( 'Master switch. When off, nothing runs - no feed generation (manual or automatic) and no widget embed, regardless of the settings on the other tabs.', 'solosearch-for-woocommerce' ),
            ),
            array(
                'title'    => __( 'SoloSearch API URL', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_API_URL,
                'type'     => 'text',
                'default'  => Config::DEFAULT_API_URL,
                'desc_tip' => __( 'Base URL of your SoloSearch panel. Only change this if you were told to point at a different environment.', 'solosearch-for-woocommerce' ),
            ),
            array(
                'title'    => __( 'API token', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_API_TOKEN,
                'type'     => 'password',
                'desc_tip' => __( "Found on your search engine's General tab in the SoloSearch panel, under API Token. Used to trigger a reindex after each feed generation.", 'solosearch-for-woocommerce' ),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'solosearch_general_options',
            ),
        );
    }

    protected function get_settings_for_feed_section() {
        return array(
            array(
                'title' => __( 'Feed Generation', 'solosearch-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'solosearch_feed_options',
                'desc'  => __( 'You can always trigger a generation manually with wp solosearch feed generate, regardless of the settings below.', 'solosearch-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Enable automatic generation', 'solosearch-for-woocommerce' ),
                'id'      => Config::OPTION_AUTO_GENERATION_ENABLE,
                'type'    => 'checkbox',
                'default' => 'yes',
                'desc_tip' => __( "Regenerates the feed automatically once a day at the time below. Doesn't affect manual generation.", 'solosearch-for-woocommerce' ),
            ),
            array(
                'title'    => __( 'Generation time', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_GENERATION_TIME,
                'type'     => 'time',
                'default'  => Config::DEFAULT_GENERATION_TIME,
                'desc_tip' => __( 'Server time, 24h format.', 'solosearch-for-woocommerce' ),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'solosearch_feed_options',
            ),
        );
    }

    protected function get_settings_for_mapping_section() {
        return array(
            array(
                'title' => __( 'Field Mapping', 'solosearch-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'solosearch_mapping_options',
                'desc'  => __( 'Send extra product data to SoloSearch, on top of the structural fields that are always included automatically (see Basic Concepts in the SoloSearch docs).', 'solosearch-for-woocommerce' ),
            ),
            array(
                'id'   => Config::OPTION_FIELD_MAPPING,
                'type' => FieldMappingField::TYPE,
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'solosearch_mapping_options',
            ),
        );
    }

    protected function get_settings_for_widget_section() {
        return array(
            array(
                'title' => __( 'Widget Embed', 'solosearch-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'solosearch_widget_options',
            ),
            array(
                'title'   => __( 'Enable widget embed', 'solosearch-for-woocommerce' ),
                'id'      => Config::OPTION_WIDGET_ENABLE,
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            array(
                'title'    => __( 'Search engine UUID', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_SEARCH_ENGINE_ID,
                'type'     => 'text',
                'desc_tip' => __( "Found on your search engine's General tab in the SoloSearch panel.", 'solosearch-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Widget script URL', 'solosearch-for-woocommerce' ),
                'id'      => Config::OPTION_SCRIPT_URL,
                'type'    => 'text',
                'default' => Config::DEFAULT_SCRIPT_URL,
            ),
            array(
                'title'    => __( 'Search input selector', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_INPUT_SELECTOR,
                'type'     => 'text',
                'desc_tip' => __( "CSS selector for your theme's search field. Leave empty to use the widget's own default (#search).", 'solosearch-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Locale override', 'solosearch-for-woocommerce' ),
                'id'      => Config::OPTION_LOCALE,
                'type'    => 'select',
                'default' => '',
                'options' => array(
                    ''   => __( 'Use engine default', 'solosearch-for-woocommerce' ),
                    'en' => 'English',
                    'es' => 'Español',
                    'fr' => 'Français',
                    'de' => 'Deutsch',
                    'it' => 'Italiano',
                ),
            ),
            array(
                'title'    => __( 'Template set UUID (advanced)', 'solosearch-for-woocommerce' ),
                'id'       => Config::OPTION_TEMPLATE_SET_ID,
                'type'     => 'text',
                'desc_tip' => __( "Optional. Forces a specific template set instead of the engine's active one. Leave empty unless you know you need this.", 'solosearch-for-woocommerce' ),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'solosearch_widget_options',
            ),
        );
    }
}
