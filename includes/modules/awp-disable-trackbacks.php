<?php
/**
 * Disable trackbacks/pingbacks site-wide.
 */

class WP_Secured_Module_Disable_Trackbacks extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_trackbacks';
        $this->label = 'Disable Trackbacks / Pingbacks';
        $this->description = 'Disables XML-RPC pingbacks and trackbacks to reduce spam and DDoS vectors.';
        $this->type = 'hardening';
        $this->weight = 10;
        $this->default_active = true;
    }

    public function register_hooks() {
        // Close pings on new posts
        add_filter( 'default_ping_status', '__return_false' );
        add_filter( 'xmlrpc_methods', [ $this, 'remove_pingback_methods' ] );
        // Reject pingback.ping requests at xmlrpc
        add_filter( 'pre_option_default_ping_status', '__return_false' );
    }

    public function remove_pingback_methods( $methods ) {
        if ( isset( $methods['pingback.ping'] ) ) {
            unset( $methods['pingback.ping'] );
        }
        return $methods;
    }
}

return new WP_Secured_Module_Disable_Trackbacks();