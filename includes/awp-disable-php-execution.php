<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// No specific code needed as this is usually done via .htaccess
$options = get_option('awp_secured_options');
if (isset($options['php-execution']) && $options['php-execution'] == 1) {
    $htaccess_file = wp_upload_dir()['basedir'] . '/.htaccess';
    if (is_writable(dirname($htaccess_file))) {
        $rules = "<Files *.php>\n    deny from all\n</Files>\n";
        file_put_contents($htaccess_file, $rules, FILE_APPEND);
    }
}
