<?php
/**
 * Remove the URL field from the comment form to reduce spam and malicious links.
 */

class WP_Secured_Module_Disable_Comments_URL extends WP_Secured_Module {
    public function __construct() {
        $this->id = 'disable_comments_url';
        $this->label = 'Remove Comment URL Field';
        $this->description = 'Removes the website/url field from the comment form to reduce spammy links.';
        $this->type = 'hardening';
        $this->weight = 3;
    }

    public function register_hooks() {
        add_filter( 'comment_form_default_fields', [ $this, 'remove_url_field' ] );
    }

    public function remove_url_field( $fields ) {
        if ( isset( $fields['url'] ) ) {
            unset( $fields['url'] );
        }
        return $fields;
    }
}

return new WP_Secured_Module_Disable_Comments_URL();