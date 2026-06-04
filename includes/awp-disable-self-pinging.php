<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['self-pinging']) && $options['self-pinging'] == 1) {
    add_action('pre_ping', 'awp_secured_disable_self_pings');

    function awp_secured_disable_self_pings(&$links) {
        $home = get_option('home');
        foreach ($links as $l => $link) {
            if (0 === strpos($link, $home)) {
                unset($links[$l]);
            }
        }
    }
}
