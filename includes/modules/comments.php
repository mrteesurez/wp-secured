<?php
/**
 * awp-disable-comments.php
 * Site-wide comments hardening: disable comments, remove feeds and related links.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

class WP_Secured_Module_AWP_Disable_Comments extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'comments'; // keep id stable
        $this->label = 'Disable Comments';
        $this->description = 'Disables comments site-wide and removes comment-related outputs to reduce spam and attack surface.';
        $this->type = 'hardening';
        $this->weight = 5;
        $this->default_active = false;
    }

    public function register_hooks() {
        // Close comments on front-end and prevent new comments
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );
        add_filter( 'default_comment_status', '__return_false' );
        add_filter( 'pre_option_default_comment_status', '__return_false' );

        // Remove comment-reply and feed links
        add_action( 'wp_head', [ $this, 'remove_comment_meta_links' ], 1 );

        // Prevent comment form fields (defensive)
        add_filter( 'comment_form_fields', [ $this, 'disable_comment_form_fields' ] );
    }

    public function remove_comment_meta_links() {
        // remove actions that output comment feeds and discovery links
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'feed_links', 2 );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'wp_generator' );
    }

    public function disable_comment_form_fields( $fields ) {
        // Empty the comment form to avoid exposing fields (falls back to closed state)
        return [];
    }
}

return new WP_Secured_Module_AWP_Disable_Comments();