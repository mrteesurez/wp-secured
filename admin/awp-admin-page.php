<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function awp_secured_menu() {
    add_management_page(
        'WP Secured',
        'WP Secured',
        'manage_options',
        'awp-secured',
        'awp_secured_settings_page'
    );
}

add_action('admin_menu', 'awp_secured_menu');

function awp_secured_settings_page() {
    ?>
    <div class="wrap">
        <h1>WP Secured Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('awp-secured-settings-group');
            do_settings_sections('awp-secured');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function awp_secured_settings_init() {
    register_setting('awp-secured-settings-group', 'awp_secured_options', 'awp_secured_options_validate');

    add_settings_section('awp-secured-main', 'Main Settings', 'awp_secured_section_text', 'awp-secured');

    $features = [
        'xmlrpc' => 'Disable XML-RPC',
        'api' => 'Disable API Requests, REST API User Endpoints, and REST API JSON Schema Links except for logged-in users',
        'gutenberg' => 'Disable Gutenberg',
        'comments-url' => 'Disable Comments URL Box',
        'rss' => 'Disable RSS Feed',
        'admin-bar' => 'Disable Admin Bar and Dashboard for All Users Except Admin',
        'version' => 'Disable WordPress Version Number',
        'directory-browsing' => 'Disable Directory Browsing',
        'php-execution' => 'Disable PHP Execution in Uploads/Media Library',
        'login-hints' => 'Disable Login Hints',
        'file-editor' => 'Disable File Editor (Plugin/Theme)',
        'app-passwords' => 'Disable Application Passwords',
        'trackbacks' => 'Disable Trackbacks and Pingbacks',
        'self-pinging' => 'Disable Self-Pinging',
        'comments' => 'Disable Comments Totally',
        'login-email' => 'Disable Login by Email'
    ];

    foreach ($features as $key => $label) {
        add_settings_field('awp_secured_' . $key, $label, 'awp_secured_field_cb', 'awp-secured', 'awp-secured-main', ['key' => $key, 'label' => $label]);
    }
}

add_action('admin_init', 'awp_secured_settings_init');

function awp_secured_section_text() {
    echo '<p>Select the features you want to disable.</p>';
}

function awp_secured_field_cb($args) {
    $options = get_option('awp_secured_options');
    $key = $args['key'];
    ?>
    <input type="checkbox" id="awp_secured_<?php echo $key; ?>" name="awp_secured_options[<?php echo $key; ?>]" value="1" <?php checked(1, isset($options[$key]) ? $options[$key] : 0, true); ?> />
    <label for="awp_secured_<?php echo $key; ?>"><?php echo $args['label']; ?></label>
    <?php
}

function awp_secured_options_validate($input) {
    $valid = [];
    $features = [
        'xmlrpc', 'api', 'gutenberg', 'comments-url', 'rss', 'admin-bar', 'version',
        'directory-browsing', 'php-execution', 'login-hints', 'file-editor',
        'app-passwords', 'trackbacks', 'self-pinging', 'comments', 'login-email'
    ];
    foreach ($features as $feature) {
        $valid[$feature] = (isset($input[$feature]) && $input[$feature] == 1) ? 1 : 0;
    }
    return $valid;
}
