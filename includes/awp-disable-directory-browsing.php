<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// No specific code needed as this is usually done via .htaccess or server settings.
$options = get_option('awp_secured_options');
if (isset($options['directory-browsing']) && $options['directory-browsing'] == 1) {
    // Assuming .htaccess is writable
    $htaccess_file = ABSPATH . '.htaccess';
    if (is_writable($htaccess_file)) {
        $rules = "\n# Disable directory browsing\nOptions -Indexes\n";
        file_put_contents($htaccess_file, $rules, FILE_APPEND);
    }
}
