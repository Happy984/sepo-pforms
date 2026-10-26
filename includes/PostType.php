<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * Forms are posts of type sepo_form. Their title is the form title; the
 * questions and settings are post meta written by the form builder.
 *
 * Addresses: /forms/<slug>/ shows one form. The list of forms is a page
 * (made on activation at /forms/) holding the [sepo_forms] shortcode.
 */
final class PostType
{
    public const TYPE = 'sepo_form';

    public static function hook(): void
    {
        add_action('init', [self::class, 'register']);
        add_filter('manage_' . self::TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [self::class, 'column'], 10, 2);
        add_filter('post_row_actions', [self::class, 'rowActions'], 10, 2);
        // A form deleted for good takes its responses and their files with it; the bin keeps them.
        add_action('before_delete_post', static function (int $postId): void {
            if (get_post_type($postId) === self::TYPE) {
                Submissions::deleteForm($postId);
            }
        });
    }

    public static function register(): void
    {
        register_post_type(self::TYPE, [
            'labels' => [
                'name'               => __('Forms', 'sepo-forms'),
                'singular_name'      => __('Form', 'sepo-forms'),
                'add_new'            => __('Add form', 'sepo-forms'),
                'add_new_item'       => __('Add form', 'sepo-forms'),
                'edit_item'          => __('Edit form', 'sepo-forms'),
                'new_item'           => __('New form', 'sepo-forms'),
                'view_item'          => __('View form', 'sepo-forms'),
                'view_items'         => __('View forms', 'sepo-forms'),
                'search_items'       => __('Search forms', 'sepo-forms'),
                'not_found'          => __('No forms found.', 'sepo-forms'),
                'not_found_in_trash' => __('No forms in the bin.', 'sepo-forms'),
                'all_items'          => __('All forms', 'sepo-forms'),
                'menu_name'          => __('Forms', 'sepo-forms'),
            ],
            'public'        => true,
            'show_in_rest'  => false,
            'has_archive'   => false,
            'rewrite'       => ['slug' => 'forms', 'with_front' => false],
            'menu_icon'     => 'dashicons-feedback',
            'menu_position' => 26,
            'supports'      => ['title', 'excerpt', 'author', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap'  => true,
        ]);
    }

    /** Can the current user manage this form (see its responses and settings)? */
    public static function canManage(int $formId): bool
    {
        return $formId > 0 && current_user_can('edit_post', $formId);
    }

    /** @param array<string,string> $cols @return array<string,string> */
    public static function columns(array $cols): array
    {
        $out = [];
        foreach ($cols as $k => $v) {
            $out[$k] = $v;
            if ($k === 'title') {
                $out['spf_status']    = __('Status', 'sepo-forms');
                $out['spf_questions'] = __('Questions', 'sepo-forms');
                $out['spf_responses'] = __('Responses', 'sepo-forms');
            }
        }
        return $out;
    }

    public static function column(string $col, int $postId): void
    {
        if ($col === 'spf_questions') {
            echo (int) count(FormDefinition::load($postId)['questions']);
        } elseif ($col === 'spf_responses') {
            $n = Submissions::count($postId);
            $url = add_query_arg(['post_type' => self::TYPE, 'page' => Admin\SubmissionsPage::SLUG, 'form' => $postId], admin_url('edit.php'));
            echo '<a href="' . esc_url($url) . '">' . esc_html(number_format_i18n($n)) . '</a>';
        } elseif ($col === 'spf_status') {
            $s = FormDefinition::load($postId)['settings'];
            $badges = [$s['closed'] ? '<span class="spf-badge spf-badge--closed">' . esc_html__('Closed', 'sepo-forms') . '</span>' : '<span class="spf-badge spf-badge--open">' . esc_html__('Open', 'sepo-forms') . '</span>'];
            if ($s['visibility'] === 'private') {
                $badges[] = '<span class="spf-badge spf-badge--private">' . esc_html__('Private', 'sepo-forms') . '</span>';
            }
            if ($s['requireLogin']) {
                $badges[] = '<span class="spf-badge">' . esc_html__('Sign-in', 'sepo-forms') . '</span>';
            }
            echo implode(' ', $badges); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
        }
    }

    /** @param array<string,string> $actions @return array<string,string> */
    public static function rowActions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type === self::TYPE && current_user_can('edit_post', $post->ID)) {
            $url = add_query_arg(['post_type' => self::TYPE, 'page' => Admin\SubmissionsPage::SLUG, 'form' => $post->ID], admin_url('edit.php'));
            $actions['spf_responses'] = '<a href="' . esc_url($url) . '">' . esc_html__('Responses', 'sepo-forms') . '</a>';
        }
        return $actions;
    }
}
