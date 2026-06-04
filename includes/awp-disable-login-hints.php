<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['login-hints']) && $options['login-hints'] == 1) {
    add_filter('login_errors', 'awp_secured_no_login_hints');

    function awp_secured_no_login_hints($error) {
        return __('Invalid login credentials.', 'awp-secured');
    }
}
