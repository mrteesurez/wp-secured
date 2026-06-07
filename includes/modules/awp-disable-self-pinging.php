<?php
/**
 * Prevent self-pings when publishing posts.
 */

class WP_Secured_Module_Disable_Self_Pinging extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_self_pinging';
        $this->label = 'Disable Self-Pinging';
        $this->description = 'Prevents WordPress from sending pings to your own domain when linking internally.';
        $this->type = 'hardening';
        $this->weight = 3;
    }

    public function register_hooks() {
        add_action( 'pre_ping', [ $this, 'remove_self_pings' ] );
    }

    public function remove_self_pings( &$links ) {
        $home = home_url();
        foreach ( $links as $l => $link ) {
            if ( strpos( $link, $home ) === 0 ) {
                unset( $links[ $l ] );
            }
        }
    }
}

return new WP_Secured_Module_Disable_Self_Pinging();