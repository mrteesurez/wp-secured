<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['comments-url']) && $options['comments-url'] == 1) {
    add_filter('comment_form_default_fields', 'awp_secured_remove_url_field');

    function awp_secured_remove_url_field($fields) {
        if (isset($fields['url'])) {
            unset($fields['url']);
        }
        return $fields;
    }
}
