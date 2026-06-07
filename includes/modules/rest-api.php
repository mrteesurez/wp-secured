<?php
/**
 * awp-rest-api.php
 * Simple REST API hardening: restrict unauthenticated access and prune endpoints.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

class WP_Secured_Module_AWP_REST_API extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'rest_api';
        $this->label = 'Restrict REST API';
        $this->description = 'Restricts public REST API access and hides sensitive endpoints from unauthenticated users.';
        $this->type = 'hardening';
        $this->weight = 15;
        $this->default_active = false;
    }

    public function register_hooks() {
        // Central gate: block or require auth for public access
        add_filter( 'rest_authentication_errors', [ $this, 'require_auth_for_sensitive_endpoints' ] );

        // Optionally remove user endpoints for non-authenticated requests
        add_filter( 'rest_endpoints', [ $this, 'filter_endpoints_for_public' ] );
    }

    /**
     * Return WP_Error to block REST for unauthenticated users on sensitive endpoints.
     */
    public function require_auth_for_sensitive_endpoints( $result ) {
        if ( ! empty( $result ) ) {
            return $result; // other auth handlers already returned an error
        }

        // If user is logged in permit
        if ( is_user_logged_in() ) {
            return $result;
        }

        // For anonymous users, optionally block specific endpoints
        $route = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        // Basic pattern: block core endpoints that expose user data
        if ( false !== stripos( $route, '/wp/v2/users' ) || false !== stripos( $route, '/wp/v2/comments' ) ) {
            return new WP_Error( 'rest_forbidden', 'REST API restricted', [ 'status' => 401 ] );
        }

        return $result;
    }

    /**
     * Remove or prune endpoints for unauthenticated users.
     */
    public function filter_endpoints_for_public( $endpoints ) {
        // If user is logged in, keep endpoints unchanged
        if ( is_user_logged_in() ) {
            return $endpoints;
        }

        // Remove endpoints that expose user lists or sensitive data
        $sensitive = [
            '/wp/v2/users',
            '/wp/v2/users/(?P<id>[\d]+)',
            '/wp/v2/users/me',
        ];

        foreach ( $sensitive as $route ) {
            if ( isset( $endpoints[ $route ] ) ) {
                unset( $endpoints[ $route ] );
            }
        }

        return $endpoints;
    }
}

return new WP_Secured_Module_AWP_REST_API();