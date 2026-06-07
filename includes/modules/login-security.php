<?php
/**
 * awp-login-security.php
 * Basic login hardening: simple rate-limiting / lockouts by IP and username.
 *
 * Note: This is a lightweight implementation intended for module-style protection.
 * For production hardened rate-limiting consider a dedicated plugin or integration with firewall.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

class WP_Secured_Module_AWP_Login_Security extends WP_Secured_Module {
    protected $max_attempts = 5;
    protected $decay_seconds = 900; // 15 minutes

    public function __construct() {
        $this->id = 'login_security';
        $this->label = 'Login Security (rate limit)';
        $this->description = 'Adds a lightweight rate-limit and temporary lockout for failed logins per IP and username.';
        $this->type = 'hardening';
        $this->weight = 20;
        $this->default_active = false;
    }

    public function register_hooks() {
        add_filter( 'authenticate', [ $this, 'check_lockout' ], 30, 3 );
        add_action( 'wp_login_failed', [ $this, 'note_login_failure' ], 10, 1 );
        add_action( 'wp_login', [ $this, 'clear_login_failures' ], 10, 2 );
    }

    protected function ip_key() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
        return 'awp_login_fail_ip_' . md5( $ip );
    }

    protected function user_key( $username ) {
        return 'awp_login_fail_user_' . md5( strtolower( $username ) );
    }

    public function note_login_failure( $username ) {
        $ip_key = $this->ip_key();
        $user_key = $this->user_key( $username );

        $this->increment_counter( $ip_key );
        $this->increment_counter( $user_key );
    }

    protected function increment_counter( $key ) {
        $data = get_transient( $key );
        if ( ! is_array( $data ) ) {
            $data = [ 'count' => 0, 'first' => time() ];
        }
        $data['count'] = max( 0, $data['count'] ) + 1;
        // set transient with decay window from first attempt (sliding)
        set_transient( $key, $data, $this->decay_seconds );
    }

    public function clear_login_failures( $user_login, $user ) {
        // clear by username and IP on successful login
        delete_transient( $this->user_key( $user_login ) );
        delete_transient( $this->ip_key() );
    }

    public function check_lockout( $user, $username, $password ) {
        // If previous auth handlers returned an error, keep it (do not override)
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        // Check counters
        $ip_key = $this->ip_key();
        $user_key = $this->user_key( $username );

        $ip = get_transient( $ip_key );
        $userT = get_transient( $user_key );

        $ip_count = is_array( $ip ) ? (int) $ip['count'] : 0;
        $user_count = is_array( $userT ) ? (int) $userT['count'] : 0;

        if ( $ip_count >= $this->max_attempts || $user_count >= $this->max_attempts ) {
            return new WP_Error(
                'too_many_attempts',
                sprintf( __( 'Too many failed login attempts. Please try again in %d minutes.' ), ceil( $this->decay_seconds / 60 ) )
            );
        }

        return $user;
    }
}

return new WP_Secured_Module_AWP_Login_Security();