<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['api']) && $options['api'] == 1) {
    add_filter('rest_authentication_errors', 'awp_secured_restrict_rest_api');

    function awp_secured_restrict_rest_api($result) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', __('REST API restricted to logged-in users.', 'awp-secured'), array('status' => rest_authorization_required_code()));
        }
        return $result;
    }

    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('template_redirect', 'rest_output_link_header', 11);
}
