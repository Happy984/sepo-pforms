<?php

/**
 * Plugin Name:       Sepo Forms
 * Description:       Multi-step forms with sections, required questions, guest or signed-in submissions, file uploads, response analytics, a searchable submissions table with CSV export, and one-click copy to Google Forms. Runs on WordPress alone.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Euneolink Solutions
 * Author URI:        https://sepodesk.com
 * Plugin URI:        https://edu.sepodesk.com
 * License:           GPL-2.0-or-later
 * Text Domain:       sepo-forms
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Sepo Forms needs PHP 8.1 or newer. The plugin is installed but not running.', 'sepo-forms')
            . '</p></div>';
    });
    return;
}

define('SPF_VERSION', '1.0.0');
define('SPF_FILE', __FILE__);
define('SPF_DIR', plugin_dir_path(__FILE__));
define('SPF_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'SepoForms\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = SPF_DIR . 'includes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, ['SepoForms\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SepoForms\\Plugin', 'deactivate']);

add_action('plugins_loaded', ['SepoForms\\Plugin', 'boot']);
