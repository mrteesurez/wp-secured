<?php
/**
 * Disable RSS and other feeds.
 */

class WP_Secured_Module_Disable_RSS extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_rss';
        $this->label = 'Disable RSS Feeds';
        $this->description = 'Turns off RSS/Atom feeds to reduce scraping and content exposure.';
        $this->type = 'hardening';
        $this->weight = 2;
    }

    public function register_hooks() {
        // Redirect feed requests to homepage with 410
        add_action( 'do_feed', [ $this, 'disable_feed' ], 1, 1 );
        add_action( 'do_feed_rdf', [ $this, 'disable_feed' ], 1, 1 );
        add_action( 'do_feed_rss', [ $this, 'disable_feed' ], 1, 1 );
        add_action( 'do_feed_rss2', [ $this, 'disable_feed' ], 1, 1 );
        add_action( 'do_feed_atom', [ $this, 'disable_feed' ], 1, 1 );
    }

    public function disable_feed() {
        wp_die( 'No feed available, please visit the homepage.', 'No Feed', [ 'response' => 410 ] );
    }
}

return new WP_Secured_Module_Disable_RSS();