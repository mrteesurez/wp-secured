<?php
/**
 * Disable login via email address. Require username-based login only.
 */

class WP_Secured_Module_Disable_Login_Email extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_login_email';
        $this->label = 'Disable Email Login';
        $this->description = 'Prevents authentication using an email address — only usernames are accepted.';
        $this->type = 'hardening';
        $this->weight = 6;
    }

    public function register_hooks() {
        add_filter( 'authenticate', [ $this, 'block_email_login' ], 20, 3 );
    }

    public function block_email_login( $user, $username, $password ) {
        if ( is_string( $username ) && strpos( $username, '@' ) !== false ) {
            return new WP_Error( 'email_login_disabled', __( 'Login via email address has been disabled. Use your username.' ) );
        }
        return $user;
    }
}

return new WP_Secured_Module_Disable_Login_Email();