<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['rss']) && $options['rss'] == 1) {
    add_action('do_feed', 'awp_secured_disable_feeds', 1);
    add_action('do_feed_rdf', 'awp_secured_disable_feeds', 1);
    add_action('do_feed_rss', 'awp_secured_disable_feeds', 1);
    add_action('do_feed_rss2', 'awp_secured_disable_feeds', 1);
    add_action('do_feed_atom', 'awp_secured_disable_feeds', 1);
    add_action('do_feed_rss2_comments', 'awp_secured_disable_feeds', 1);
    add_action('do_feed_atom_comments', 'awp_secured_disable_feeds', 1);

    function awp_secured_disable_feeds() {
        wp_die(__('No feed available, please visit our <a href="'. esc_url(home_url('/')) .'">homepage</a>!', 'awp-secured'));
    }
}
