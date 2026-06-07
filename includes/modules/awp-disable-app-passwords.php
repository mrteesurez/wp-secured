<?php
/**
 * Disable Application Passwords (WordPress feature) to prevent external credential use.
 */

class WP_Secured_Module_Disable_App_Passwords extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_app_passwords';
        $this->label = 'Disable App Passwords';
        $this->description = 'Disables the Application Passwords feature for all users.';
        $this->type = 'hardening';
        $this->weight = 12;
    }

    public function register_hooks() {
        add_filter( 'wp_is_application_passwords_available', '__return_false' );
        add_filter( 'allow_password_reset', [ $this, 'allow_password_reset_for_app_passwords' ], 10, 2 );
    }

    public function allow_password_reset_for_app_passwords( $allow, $user_id ) {
        // keep normal resets enabled; no special handling required here
        return $allow;
    }
}

return new WP_Secured_Module_Disable_App_Passwords();