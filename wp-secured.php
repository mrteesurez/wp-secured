<?php
/*
Plugin Name: WP Secured
Description: A WordPress security hardening plugin.
Version: 1.0.0
Author: mrteesurez
Text Domain: wp-secured
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP_SECURED_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SECURED_URL', plugin_dir_url( __FILE__ ) );

require_once WP_SECURED_DIR . 'includes/core/class-loader.php';

// Boot on plugins_loaded so WP APIs are available.
add_action( 'plugins_loaded', function () {
    $loader = WP_Secured_Loader::get_instance();
    $loader->init();
} );