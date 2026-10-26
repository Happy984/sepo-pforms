<?php

declare(strict_types=1);

namespace SepoForms\Admin;

use SepoForms\Google\Client;
use SepoForms\Google\Settings as GoogleSettings;
use SepoForms\PostType;
use SepoForms\Settings;

defined('ABSPATH') || exit;

/** Forms → Settings: look, the forms list page, the Google Forms connection, uninstall. */
final class SettingsPage
{
    public const SLUG = 'sepo-forms-settings';

    public static function hook(): void
    {
        add_action('admin_menu', static function (): void {
            add_submenu_page('edit.php?post_type=' . PostType::TYPE, __('Forms settings', 'sepo-forms'), __('Settings', 'sepo-forms'), 'manage_options', self::SLUG, [self::class, 'render']);
        });
        add_action('admin_post_sepo_forms_settings', [self::class, 'save']);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = Settings::all();
        $connected = GoogleSettings::isConfigured();
        $mask = static fn(string $v): string => $v === '' ? '' : str_repeat('•', 8) . substr($v, -4);
        ?>
        <div class="wrap spf-settings-page">
          <h1><?php esc_html_e('Forms settings', 'sepo-forms'); ?></h1>
          <?php if (isset($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'sepo-forms'); ?></p></div>
          <?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="sepo_forms_settings">
            <?php wp_nonce_field('sepo_forms_settings'); ?>

            <h2><?php esc_html_e('Look and pages', 'sepo-forms'); ?></h2>
            <table class="form-table" role="presentation">
              <tr><th scope="row"><label for="spf-accent"><?php esc_html_e('Accent colour', 'sepo-forms'); ?></label></th>
                <td><input type="text" id="spf-accent" name="accent" value="<?php echo esc_attr((string) $s['accent']); ?>" placeholder="#1d475a" class="regular-text" pattern="#?[0-9a-fA-F]{3,6}">
                  <p class="description"><?php esc_html_e('Header strip, Next button, current step and tabs. Leave empty for the default dark teal.', 'sepo-forms'); ?></p></td></tr>
              <tr><th scope="row"><label for="spf-index"><?php esc_html_e('Forms list page', 'sepo-forms'); ?></label></th>
                <td><?php wp_dropdown_pages(['name' => 'index_page', 'id' => 'spf-index', 'selected' => (int) $s['index_page'], 'show_option_none' => esc_html__('— None —', 'sepo-forms'), 'option_none_value' => '0']); ?>
                  <p class="description"><?php esc_html_e('The page that lists every form. Any page with the [sepo_forms] shortcode or the "Sepo forms list" block works.', 'sepo-forms'); ?></p></td></tr>
            </table>

            <h2><?php esc_html_e('Google Forms', 'sepo-forms'); ?>
              <span class="spf-status <?php echo $connected ? 'spf-status--on' : 'spf-status--off'; ?>"><?php echo $connected ? esc_html__('Connected', 'sepo-forms') : esc_html__('Not connected', 'sepo-forms'); ?></span></h2>
            <p><?php esc_html_e('One connection for the whole site. Form managers then get a "Create on Google Forms" button on each form\'s Google Forms tab.', 'sepo-forms'); ?></p>
            <ol class="spf-steps">
              <li><?php esc_html_e('In the Google Cloud console, enable the Google Forms API and the Google Drive API, and create an OAuth client of type "Web application".', 'sepo-forms'); ?></li>
              <li><?php echo esc_html(sprintf(/* translators: %s: URL */ __('Add %s as an authorised redirect URI.', 'sepo-forms'), 'https://developers.google.com/oauthplayground')); ?></li>
              <li><?php echo esc_html(sprintf(/* translators: 1: scope, 2: scope */ __('In the OAuth 2.0 Playground, use your own client, tick the scopes %1$s and %2$s, and exchange the code for a refresh token.', 'sepo-forms'), Client::SCOPE_FORMS, Client::SCOPE_DRIVE)); ?></li>
              <li><?php esc_html_e('Paste the client ID, secret and refresh token below and switch the integration on.', 'sepo-forms'); ?></li>
            </ol>
            <table class="form-table" role="presentation">
              <tr><th scope="row"><?php esc_html_e('Integration', 'sepo-forms'); ?></th>
                <td><label><input type="checkbox" name="google_enabled" value="1" <?php checked((bool) $s['google_enabled']); ?>> <?php esc_html_e('Show the "Create on Google Forms" button', 'sepo-forms'); ?></label></td></tr>
              <tr><th scope="row"><label for="spf-gid"><?php esc_html_e('OAuth client ID', 'sepo-forms'); ?></label></th>
                <td><input type="text" id="spf-gid" name="google_client_id" value="<?php echo esc_attr((string) $s['google_client_id']); ?>" class="large-text" autocomplete="off"></td></tr>
              <tr><th scope="row"><label for="spf-gsecret"><?php esc_html_e('OAuth client secret', 'sepo-forms'); ?></label></th>
                <td><input type="password" id="spf-gsecret" name="google_secret" value="" placeholder="<?php echo esc_attr($mask((string) $s['google_secret'])); ?>" class="regular-text" autocomplete="new-password">
                  <p class="description"><?php esc_html_e('Leave empty to keep the saved value.', 'sepo-forms'); ?></p></td></tr>
              <tr><th scope="row"><label for="spf-gtoken"><?php esc_html_e('Refresh token', 'sepo-forms'); ?></label></th>
                <td><input type="password" id="spf-gtoken" name="google_token" value="" placeholder="<?php echo esc_attr($mask((string) $s['google_token'])); ?>" class="large-text" autocomplete="new-password">
                  <p class="description"><?php esc_html_e('Leave empty to keep the saved value.', 'sepo-forms'); ?></p></td></tr>
              <?php if ($s['google_client_id'] !== '' || $s['google_secret'] !== '' || $s['google_token'] !== '') : ?>
              <tr><th scope="row"><?php esc_html_e('Disconnect', 'sepo-forms'); ?></th>
                <td><label><input type="checkbox" name="google_forget" value="1"> <?php esc_html_e('Remove the saved credentials and switch the integration off', 'sepo-forms'); ?></label></td></tr>
              <?php endif; ?>
            </table>

            <h2><?php esc_html_e('Uninstall', 'sepo-forms'); ?></h2>
            <table class="form-table" role="presentation">
              <tr><th scope="row"><?php esc_html_e('Delete data', 'sepo-forms'); ?></th>
                <td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked((bool) $s['delete_on_uninstall']); ?>> <?php esc_html_e('When the plugin is deleted, also delete every form, response and uploaded file', 'sepo-forms'); ?></label></td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('sepo_forms_settings')) {
            wp_die(esc_html__('You cannot change these settings.', 'sepo-forms'), '', ['response' => 403]);
        }
        // phpcs:disable WordPress.Security.NonceVerification -- checked above
        $accent = trim(sanitize_text_field(wp_unslash((string) ($_POST['accent'] ?? ''))));
        if ($accent !== '' && $accent[0] !== '#') {
            $accent = '#' . $accent;
        }
        $values = [
            'accent'              => (string) (sanitize_hex_color($accent) ?? ''),
            'index_page'          => absint($_POST['index_page'] ?? 0),
            'google_enabled'      => empty($_POST['google_enabled']) ? 0 : 1,
            'google_client_id'    => trim(sanitize_text_field(wp_unslash((string) ($_POST['google_client_id'] ?? '')))),
            'delete_on_uninstall' => empty($_POST['delete_on_uninstall']) ? 0 : 1,
        ];
        foreach (['google_secret', 'google_token'] as $k) {
            $v = trim(sanitize_text_field(wp_unslash((string) ($_POST[$k] ?? ''))));
            if ($v !== '') {
                $values[$k] = $v;
            }
        }
        Settings::update($values);
        if (!empty($_POST['google_forget'])) {
            GoogleSettings::forget();
        }
        // phpcs:enable
        wp_safe_redirect(add_query_arg(['post_type' => PostType::TYPE, 'page' => self::SLUG, 'updated' => 1], admin_url('edit.php')));
        exit;
    }
}
