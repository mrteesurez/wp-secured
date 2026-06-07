<?php
/**
 * Attempt to prevent directory browsing by creating index.html and .htaccess in key directories.
 * This is a best-effort module; true directory listing prevention is server-config dependent.
 */

class WP_Secured_Module_Disable_Directory_Browsing extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_directory_browsing';
        $this->label = 'Prevent Directory Browsing';
        $this->description = 'Attempts to harden directories (wp-content, uploads) by adding index files and .htaccess rules.';
        $this->type = 'hardening';
        $this->weight = 5;
        $this->default_active = true;
    }

    public function register_hooks() {
        add_action( 'admin_init', [ $this, 'ensure_index_and_htaccess' ] );
    }

    public function ensure_index_and_htaccess() {
        $targets = [
            WP_CONTENT_DIR,
        ];
        $uploads = wp_get_upload_dir();
        if ( ! empty( $uploads['basedir'] ) ) {
            $targets[] = $uploads['basedir'];
        }

        foreach ( $targets as $dir ) {
            $dir = trailingslashit( $dir );
            if ( ! is_dir( $dir ) ) {
                continue;
            }
            $index = $dir . 'index.html';
            if ( ! file_exists( $index ) ) {
                @file_put_contents( $index, '<!-- Protected -->' );
            }
            $htaccess = $dir . '.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                $contents = "Options -Indexes\n<IfModule mod_headers.c>\n  Header set X-Content-Type-Options nosniff\n</IfModule>\n";
                @file_put_contents( $htaccess, $contents );
            }
        }
    }
}

return new WP_Secured_Module_Disable_Directory_Browsing();