<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['login-email']) && $options['login-email'] == 1) {
    remove_filter('authenticate', 'wp_authenticate_email_password', 20);
}
