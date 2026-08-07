<?php
/**
 * Uninstall handler - runs only when the plugin is deleted from the Plugins
 * screen (not on deactivation). Removes every option this plugin created.
 *
 * Option names are duplicated here on purpose instead of requiring Config.php:
 * uninstall.php runs in a minimal WordPress context and must not depend on
 * our own autoloader having been registered.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$solosearch_woo_options = array(
    'solosearch_woo_enable',
    'solosearch_woo_api_url',
    'solosearch_woo_api_token',
    'solosearch_woo_widget_enable',
    'solosearch_woo_widget_search_engine_id',
    'solosearch_woo_widget_script_url',
    'solosearch_woo_widget_input_selector',
    'solosearch_woo_widget_locale',
    'solosearch_woo_widget_template_set_id',
);

foreach ( $solosearch_woo_options as $solosearch_woo_option ) {
    delete_option( $solosearch_woo_option );
}
