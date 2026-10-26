<?php

declare(strict_types=1);

namespace SepoForms\Google;

use SepoForms\Settings as PluginSettings;

defined('ABSPATH') || exit;

/**
 * The site-wide Google Forms connection: one OAuth client and refresh token
 * for every form, set under Forms → Settings.
 *
 * isConfigured() checks the values are present and the switch is on; it does
 * not call Google. A wrong but present credential shows the button and fails
 * on click with Google's reason.
 */
final class Settings
{
    public static function isConfigured(): bool
    {
        return (bool) PluginSettings::get('google_enabled') && self::client() !== null;
    }

    public static function client(): ?Client
    {
        $s = PluginSettings::all();
        $id = trim((string) $s['google_client_id']);
        $secret = trim((string) $s['google_secret']);
        $token = trim((string) $s['google_token']);
        if ($id === '' || $secret === '' || $token === '') {
            return null;
        }
        return new Client($id, $secret, $token);
    }

    /** Disconnect: clear the credential and switch the integration off. */
    public static function forget(): void
    {
        PluginSettings::update(['google_client_id' => '', 'google_secret' => '', 'google_token' => '', 'google_enabled' => 0]);
    }
}
