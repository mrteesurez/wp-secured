<?php
/**
 * awp-disable-xmlrpc.php
 * Disable XML-RPC and related methods.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

class WP_Secured_Module_AWP_XMLRPC extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'xmlrpc'; // keep id stable for existing installs
        $this->label = 'Disable XML-RPC';
        $this->description = 'Disables xmlrpc.php and removes common XML-RPC methods (e.g. pingback.ping).';
        $this->type = 'hardening';
        $this->weight = 10;
        $this->default_active = true;
    }

    public function register_hooks() {
        // Disable xmlrpc by filter
        add_filter( 'xmlrpc_enabled', '__return_false' );

        // Remove pingback methods and common vectors
        add_filter( 'xmlrpc_methods', [ $this, 'remove_xmlrpc_methods' ] );

        // Remove X-Pingback header (reduces pingback discovery)
        add_filter( 'wp_headers', [ $this, 'remove_pingback_header' ] );

        // Block direct access to xmlrpc.php early (if xmlrpc is requested)
        add_action( 'template_redirect', [ $this, 'block_xmlrpc_requests' ], 0 );
    }

    public function remove_xmlrpc_methods( $methods ) {
        // Remove pingback support and other potentially risky methods
        $candidates = [
            'pingback.ping',
            'pingback.extensions.getPingbacks',
            'wp.getUsersBlogs',
            'wp.getUsersBlogs', // duplicated safe-check
        ];
        foreach ( $candidates as $m ) {
            if ( isset( $methods[ $m ] ) ) {
                unset( $methods[ $m ] );
            }
        }
        return $methods;
    }

    public function remove_pingback_header( $headers ) {
        if ( isset( $headers['X-Pingback'] ) ) {
            unset( $headers['X-Pingback'] );
        }
        return $headers;
    }

    public function block_xmlrpc_requests() {
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            // Return 403 if someone tries to call xmlrpc even if server bypasses filter
            status_header( 403 );
            wp_die( 'XML-RPC disabled', 'Forbidden', [ 'response' => 403 ] );
        }

        // also detect direct access to xmlrpc.php via REQUEST_URI
        if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'xmlrpc.php' ) ) {
            status_header( 403 );
            wp_die( 'XML-RPC disabled', 'Forbidden', [ 'response' => 403 ] );
        }
    }
}

return new WP_Secured_Module_AWP_XMLRPC();