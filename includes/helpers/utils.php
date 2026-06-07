<?php
// Small helper utilities for admin forms and sanitization.

function wp_secured_nonce_field() {
    wp_nonce_field( 'wp_secured_save_settings', 'wp_secured_nonce' );
}

function wp_secured_verify_nonce() {
    if ( ! isset( $_POST['wp_secured_nonce'] ) ) {
        return false;
    }
    return wp_verify_nonce( $_POST['wp_secured_nonce'], 'wp_secured_save_settings' );
}

function wp_secured_escape_attr( $value ) {
    return esc_attr( $value );
}