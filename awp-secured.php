<?php
/**
 * Plugin Name:         WP Secured
 * Description:         Enhance the security and performance of your WordPress site with WP Secured, featuring multiple security enhancements and optimizations.
 * Author:              Mrteesurez
 * Author URI:          https://www toyaab.com
 *
 * Version:             1.0.0
 * Requires at least:   5.6.0
 * Requires PHP:        7.2
 *
 * License:             GPL v3
 *
 * Text Domain:         wp-secured
 *
 * WP Secured
 * Copyright (C) 2024, Toyaab
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category            Plugin
 * @copyright           Copyright © 2024 Mrteesurez
 * @author              Mrteesurez
 * @package             WP Secured
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin path
define('AWP_SECURED_PATH', plugin_dir_path(__FILE__));

// Include the admin settings page
require_once AWP_SECURED_PATH . 'admin/awp-admin-page.php';

// Include the code files
$features = [
    'xmlrpc', 'api', 'gutenberg', 'comments-url', 'rss', 'admin-bar', 'version',
    'directory-browsing', 'php-execution', 'login-hints', 'file-editor',
    'app-passwords', 'trackbacks', 'self-pinging', 'comments', 'login-email'
];

foreach ($features as $feature) {
    require_once AWP_SECURED_PATH . 'includes/awp-disable-' . $feature . '.php';
}
