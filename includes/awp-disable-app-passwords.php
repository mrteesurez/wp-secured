<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['app-passwords']) && $options['app-passwords'] == 1) {
    add_filter('wp_is_application_passwords_available', '__return_false');
}
