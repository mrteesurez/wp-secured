<?php
/**
 * awp-file-protection.php
 * File/editor protection: disable theme/plugin editor and reduce risky caps; attempt safe server-hardening steps.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

class WP_Secured_Module_AWP_File_Protection extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'file_protection';
        $this->label = 'File Protection';
        $this->description = 'Disables the file editor and reduces file-related capabilities; attempts to place basic protections in uploads/wp-content.';
        $this->type = 'hardening';
        $this->weight = 20;
        $this->default_active = true;
    }

    public function register_hooks() {
        // Remove capability to edit plugin/theme files via the editor
        add_filter( 'user_has_cap', [ $this, 'disable_file_editor' ], 10, 3 );

        // Prevent direct file editing map meta caps if present
        add_filter( 'map_meta_cap', [ $this, 'map_meta_cap_disable_file_edit' ], 10, 4 );

        // Attempt to write protective files in wp-content/uploads (best-effort)
        add_action( 'admin_init', [ $this, 'create_protective_files' ] );
    }

    public function disable_file_editor( $allcaps, $caps, $args ) {
        // Revoke edit_files capability if present
        if ( isset( $allcaps['edit_files'] ) ) {
            $allcaps['edit_files'] = false;
        }
        // Also revoke plugin/theme editing caps
        if ( isset( $allcaps['edit_plugins'] ) ) {
            $allcaps['edit_plugins'] = false;
        }
        if ( isset( $allcaps['edit_themes'] ) ) {
            $allcaps['edit_themes'] = false;
        }
        return $allcaps;
    }

    public function map_meta_cap_disable_file_edit( $caps, $cap, $user_id, $args ) {
        // When WP checks meta caps for editing plugin/theme files, return a capability that will fail
        if ( in_array( $cap, [ 'edit_plugins', 'edit_themes', 'edit_files' ], true ) ) {
            return [ 'do_not_allow' ];
        }
        return $caps;
    }

    public function create_protective_files() {
        if ( ! function_exists( 'wp_get_upload_dir' ) ) {
            return;
        }
        $uploads = wp_get_upload_dir();
        $targets = [];

        if ( ! empty( $uploads['basedir'] ) ) {
            $targets[] = trailingslashit( $uploads['basedir'] );
        }
        if ( defined( 'WP_CONTENT_DIR' ) ) {
            $targets[] = trailingslashit( WP_CONTENT_DIR );
        }

        foreach ( $targets as $dir ) {
            if ( ! is_dir( $dir ) ) {
                continue;
            }
            // ensure index.html
            $index = $dir . 'index.html';
            if ( ! file_exists( $index ) ) {
                @file_put_contents( $index, '<!-- Protected -->' );
            }
            // best-effort .htaccess to deny PHP execution in uploads
            $ht = $dir . '.htaccess';
            if ( ! file_exists( $ht ) ) {
                $rules = "<IfModule mod_php7.c>\n<FilesMatch \"\\.(php|phtml)$\">\n  Deny from all\n</FilesMatch>\n</IfModule>\n";
                @file_put_contents( $ht, $rules );
            }
        }
    }
}

return new WP_Secured_Module_AWP_File_Protection();