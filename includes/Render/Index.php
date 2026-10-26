<?php

declare(strict_types=1);

namespace SepoForms\Render;

use SepoForms\FormDefinition;
use SepoForms\PostType;

defined('ABSPATH') || exit;

/**
 * The list of forms: search, grid or list view, and pages of cards. The
 * cards are drawn by forms.js from the data in the container, so searching
 * and paging need no page loads.
 */
final class Index
{
    public const PER_PAGE = 12;

    /** @param array{perPage?:int,view?:string} $o */
    public static function render(array $o = []): string
    {
        $posts = get_posts([
            'post_type'      => PostType::TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        $forms = [];
        foreach ($posts as $p) {
            $f = FormDefinition::load($p->ID);
            $private = $f['settings']['visibility'] === 'private';
            if ($private && !current_user_can('edit_post', $p->ID)) {
                continue;
            }
            $forms[] = [
                'title'   => $f['title'],
                'desc'    => wp_strip_all_tags($f['description']),
                'closed'  => (bool) $f['settings']['closed'],
                'private' => $private,
                'login'   => (bool) $f['settings']['requireLogin'],
                'url'     => (string) get_permalink($p),
                'order'   => (int) $f['settings']['order'],
                'count'   => count($f['questions']),
            ];
        }
        usort($forms, static fn($a, $b) => [$b['order'], $a['title']] <=> [$a['order'], $b['title']]);

        if ($forms === []) {
            return '<div class="sf-shell-wrapper"><div class="sf-empty">' . esc_html__('There are no forms to fill in at the moment.', 'sepo-forms') . '</div></div>';
        }
        $view = ($o['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
        $id = 'sf-index-' . wp_generate_password(5, false, false);
        return '<div class="forms-index-container" id="' . esc_attr($id) . '" data-sf-index data-forms="' . esc_attr((string) wp_json_encode($forms)) . '" data-per-page="' . max(1, (int) ($o['perPage'] ?? self::PER_PAGE)) . '" data-view="' . $view . '">'
            . '<div class="sf-index-toolbar">'
            . '<div class="sp-table-search sf-index-search"><span class="sp-table-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>'
            . '<input type="search" class="sp-table-search-input" data-sf-index-search placeholder="' . esc_attr__('Search forms…', 'sepo-forms') . '" aria-label="' . esc_attr__('Search forms', 'sepo-forms') . '"></div>'
            . '<div class="sf-view-toggle" role="group" aria-label="' . esc_attr__('Layout', 'sepo-forms') . '">'
            . '<button type="button" class="sf-view-btn" data-sf-view="grid" aria-label="' . esc_attr__('Grid', 'sepo-forms') . '"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></button>'
            . '<button type="button" class="sf-view-btn" data-sf-view="list" aria-label="' . esc_attr__('List', 'sepo-forms') . '">' . Icons::get('list', 15) . '</button>'
            . '</div></div>'
            . '<div class="forms-wrapper component-view-' . $view . '" data-sf-index-list aria-live="polite"></div>'
            . '<div class="sp-table-pagination" data-sf-index-pages></div>'
            . '</div>';
    }
}
