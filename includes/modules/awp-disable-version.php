<?php
/**
 * Disable WordPress version in frontend and generator meta.
 */

class WP_Secured_Module_Disable_Version extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_version';
        $this->label = 'Hide WP Version';
        $this->description = 'Removes WordPress version output and generator meta tags.';
        $this->type = 'hardening';
        $this->weight = 5;
        $this->default_active = true;
    }

    public function register_hooks() {
        // Remove generator meta
        add_filter( 'the_generator', '__return_empty_string' );
        // Remove version from scripts/styles
        add_filter( 'style_loader_src', [ $this, 'remove_version_query' ], 999 );
        add_filter( 'script_loader_src', [ $this, 'remove_version_query' ], 999 );
        // Remove version header
        header_remove( 'X-Pingback' );
    }

    public function remove_version_query( $src ) {
        if ( false !== strpos( $src, 'ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }
}

return new WP_Secured_Module_Disable_Version();