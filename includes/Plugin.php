<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * Wires the plugin together and keeps the database in step with the code.
 */
final class Plugin
{
    public const VERSION_OPTION = 'sepo_forms_version';

    public static function activate(): void
    {
        Submissions::install();
        Files::protectDir();
        PostType::register();
        Settings::ensureIndexPage();
        flush_rewrite_rules();
        update_option(self::VERSION_OPTION, SPF_VERSION);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function boot(): void
    {
        add_action('init', static function (): void {
            load_plugin_textdomain('sepo-forms', false, dirname(plugin_basename(SPF_FILE)) . '/languages');
        }, 1);

        PostType::hook();
        Frontend::hook();
        Rest::hook();
        Embeds::hook();

        if (is_admin()) {
            Admin\Builder::hook();
            Admin\SettingsPage::hook();
            Admin\SubmissionsPage::hook();
        }
        add_action('admin_post_sepo_forms_export', [Submissions::class, 'exportRequest']);
        add_action('admin_post_sepo_forms_file', [Files::class, 'downloadRequest']);

        // A new version may add columns or rewrite rules: bring both up to date once.
        add_action('init', static function (): void {
            if (get_option(self::VERSION_OPTION) !== SPF_VERSION) {
                Submissions::install();
                Files::protectDir();
                flush_rewrite_rules(false);
                update_option(self::VERSION_OPTION, SPF_VERSION);
            }
        }, 99);
    }

    /** The plugin version plus the file's change time, so browsers fetch an edited file at once. */
    public static function assetVersion(string $rel): string
    {
        $file = SPF_DIR . $rel;
        return SPF_VERSION . (is_file($file) ? '.' . filemtime($file) : '');
    }
}
