<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['xmlrpc']) && $options['xmlrpc'] == 1) {
    add_filter('xmlrpc_enabled', '__return_false');
}
