<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['admin-bar']) && $options['admin-bar'] == 1) {
    add_action('after_setup_theme', 'awp_secured_remove_admin_bar');

    function awp_secured_remove_admin_bar() {
        if (!current_user_can('administrator') && !is_admin()) {
            show_admin_bar(false);
        }
    }

    add_action('admin_init', 'awp_secured_redirect_non_admin_users');
    function awp_secured_redirect_non_admin_users() {
        if (!current_user_can('administrator') && !wp_doing_ajax()) {
            wp_redirect(home_url());
            exit;
        }
    }
}
