<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['version']) && $options['version'] == 1) {
    remove_action('wp_head', 'wp_generator');
}
