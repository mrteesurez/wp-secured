<?php
/**
 * Remove detailed login error messages to reduce info disclosure.
 */

class WP_Secured_Module_Disable_Login_Hints extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_login_hints';
        $this->label = 'Disable Login Hints';
        $this->description = 'Replace login error messages with a generic message to avoid disclosing whether the user or password was incorrect.';
        $this->type = 'hardening';
        $this->weight = 8;
    }

    public function register_hooks() {
        add_filter( 'login_errors', [ $this, 'generic_login_error' ] );
        // Also remove auth cookies hint incidentally
        add_filter( 'authenticate', [ $this, 'suppress_auth_errors' ], 30, 3 );
    }

    public function generic_login_error( $error ) {
        // Only show generic message
        if ( ! empty( $error ) ) {
            return '<strong>ERROR</strong>: The login information is incorrect.';
        }
        return $error;
    }

    public function suppress_auth_errors( $user, $username, $password ) {
        // If WP returned WP_Error, map to generic error
        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: The login information is incorrect.' ) );
        }
        return $user;
    }
}

return new WP_Secured_Module_Disable_Login_Hints();