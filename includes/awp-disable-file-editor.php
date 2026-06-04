<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('awp_secured_options');
if (isset($options['file-editor']) && $options['file-editor'] == 1) {
    define('DISALLOW_FILE_EDIT', true);
}
