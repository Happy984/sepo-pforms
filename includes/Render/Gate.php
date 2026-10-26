<?php

declare(strict_types=1);

namespace SepoForms\Render;

defined('ABSPATH') || exit;

/**
 * The card shown instead of a form the visitor cannot fill in: sign-in
 * required, or a private form.
 */
final class Gate
{
    /**
     * @param array{title:string,message?:string,tone?:string,actions?:list<array{label:string,href:string,primary?:bool}>} $o
     */
    public static function render(array $o): string
    {
        $buttons = '';
        foreach ($o['actions'] ?? [] as $a) {
            $buttons .= '<a class="sf-gate-btn' . (!empty($a['primary']) ? ' sf-gate-btn-primary' : '') . '" href="' . esc_url($a['href']) . '">' . esc_html($a['label']) . '</a>';
        }
        $tone = ($o['tone'] ?? '') === 'warn' ? ' sf-gate-warn' : '';
        return '<div class="sf-shell-wrapper"><div class="sf-gate' . $tone . '" role="region" aria-label="' . esc_attr($o['title']) . '">'
            . '<div class="sf-gate-card">'
            . '<span class="sf-gate-icon">' . Icons::get('lock', 22) . '</span>'
            . '<h2 class="sf-gate-title">' . esc_html($o['title']) . '</h2>'
            . (($o['message'] ?? '') !== '' ? '<p class="sf-gate-msg">' . esc_html($o['message']) . '</p>' : '')
            . ($buttons !== '' ? '<div class="sf-gate-actions">' . $buttons . '</div>' : '')
            . '</div></div></div>';
    }

    public static function login(): string
    {
        $back = is_singular() ? (string) get_permalink() : home_url(add_query_arg([]));
        $actions = [['label' => __('Log in', 'sepo-forms'), 'href' => wp_login_url($back), 'primary' => true]];
        if (get_option('users_can_register')) {
            $actions[] = ['label' => __('Create an account', 'sepo-forms'), 'href' => wp_registration_url()];
        }
        return self::render([
            'title'   => __('Sign-in required', 'sepo-forms'),
            'message' => __('Please log in to fill out this form.', 'sepo-forms'),
            'actions' => $actions,
        ]);
    }

    public static function private(): string
    {
        return self::render([
            'title'   => __('Not enough permissions', 'sepo-forms'),
            'message' => __('This form is private. You do not have permission to open it.', 'sepo-forms'),
            'tone'    => 'warn',
            'actions' => [['label' => __('Go to the home page', 'sepo-forms'), 'href' => home_url('/'), 'primary' => true]],
        ]);
    }
}
