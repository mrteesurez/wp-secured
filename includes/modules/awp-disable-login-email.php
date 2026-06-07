<?php
if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

// Use a unique class name to avoid collisions
if ( ! class_exists( 'WP_Secured_Module_AWP_Login_Email' ) ) {
    class WP_Secured_Module_AWP_Login_Email extends WP_Secured_Module {
        public function __construct() {
            $this->id = 'disable_login_email'; // or 'disable_login_email' or keep 'login_email' per your ID scheme
            $this->label = 'Disable Login by Email';
            $this->description = 'Prevents login via email address — only usernames are accepted.';
            $this->type = 'hardening';
            $this->weight = 8;
            $this->default_active = false;
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
}

// Return an instance (loader expects each module file to return an instance)
return new WP_Secured_Module_AWP_Login_Email();