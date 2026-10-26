<?php

declare(strict_types=1);

namespace SepoForms\Admin;

use SepoForms\FormDefinition;
use SepoForms\Frontend;
use SepoForms\PostType;
use SepoForms\Render\Manager;
use SepoForms\Render\Tabs;
use SepoForms\Submissions;

defined('ABSPATH') || exit;

/**
 * Forms → Responses: the same Submissions, Settings and Google Forms tabs as
 * the form page, for one form at a time, inside wp-admin.
 */
final class SubmissionsPage
{
    public const SLUG = 'sepo-forms-responses';

    public static function hook(): void
    {
        add_action('admin_menu', static function (): void {
            $hook = add_submenu_page('edit.php?post_type=' . PostType::TYPE, __('Responses', 'sepo-forms'), __('Responses', 'sepo-forms'), 'edit_posts', self::SLUG, [self::class, 'render']);
            add_action('load-' . $hook, static fn() => Frontend::enqueue());
        });
    }

    public static function render(): void
    {
        $forms = get_posts(['post_type' => PostType::TYPE, 'post_status' => ['publish', 'draft', 'private', 'pending'], 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC']);
        $forms = array_values(array_filter($forms, static fn($p) => current_user_can('edit_post', $p->ID)));
        $current = isset($_GET['form']) ? absint($_GET['form']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        if ($current === 0 && $forms) {
            $current = (int) $forms[0]->ID;
        }
        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('Responses', 'sepo-forms') . '</h1>';
        if (!$forms) {
            echo '<p>' . esc_html__('Create a form first. Its responses will appear here.', 'sepo-forms') . '</p></div>';
            return;
        }
        echo '<form class="spf-responses-head" method="get"><input type="hidden" name="post_type" value="' . esc_attr(PostType::TYPE) . '"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">'
            . '<label><span class="screen-reader-text">' . esc_html__('Form', 'sepo-forms') . '</span><select name="form" onchange="this.form.submit()">';
        foreach ($forms as $p) {
            echo '<option value="' . (int) $p->ID . '"' . selected($current, (int) $p->ID, false) . '>' . esc_html(get_the_title($p) ?: __('(no title)', 'sepo-forms')) . ' (' . (int) Submissions::count((int) $p->ID) . ')</option>';
        }
        echo '</select></label><noscript><button class="button">' . esc_html__('Show', 'sepo-forms') . '</button></noscript>';
        if (PostType::canManage($current)) {
            echo '<span><a class="button" href="' . esc_url((string) get_edit_post_link($current)) . '">' . esc_html__('Edit form', 'sepo-forms') . '</a> '
                . '<a class="button" href="' . esc_url((string) get_permalink($current)) . '">' . esc_html__('View form', 'sepo-forms') . '</a></span>';
        }
        echo '</form>';
        if (!PostType::canManage($current)) {
            echo '<p>' . esc_html__('You cannot view the responses to this form.', 'sepo-forms') . '</p></div>';
            return;
        }
        $form = FormDefinition::load($current);
        $html = Tabs::render([
            'id'    => 'spf-admin-' . $current,
            'style' => 'underline',
            'tabs'  => [
                ['label' => __('Submissions', 'sepo-forms'), 'icon' => 'inbox', 'badge' => (string) Submissions::count($current), 'content' => Manager::lazy($current)],
                ['label' => __('Settings', 'sepo-forms'), 'icon' => 'settings', 'content' => Manager::settingsTab($form)],
                ['label' => __('Google Forms', 'sepo-forms'), 'icon' => 'share', 'content' => Manager::googleTab($form)],
            ],
        ]);
        echo '<div class="spf-admin-shell sf-scope"><div class="sf-shell-wrapper">' . $html . '</div></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }
}
