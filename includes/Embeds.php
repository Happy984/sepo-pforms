<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * Forms in page builders: the "Sepo form" and "Sepo forms list" blocks, and
 * matching Elementor widgets when Elementor is active.
 */
final class Embeds
{
    public static function hook(): void
    {
        add_action('init', [self::class, 'blocks']);
        add_action('enqueue_block_editor_assets', [self::class, 'editorAssets']);
        add_action('elementor/elements/categories_registered', static function ($manager): void {
            $manager->add_category('sepo-forms', ['title' => __('Sepo forms', 'sepo-forms'), 'icon' => 'eicon-form-horizontal']);
        });
        add_action('elementor/widgets/register', static function ($manager): void {
            $manager->register(new Elementor\FormWidget());
            $manager->register(new Elementor\FormsListWidget());
        });
        add_action('elementor/frontend/after_register_styles', [Frontend::class, 'register']);
        add_action('elementor/frontend/after_register_scripts', [Frontend::class, 'register']);
    }

    public static function blocks(): void
    {
        register_block_type('sepo/form', [
            'api_version'     => 2,
            'title'           => __('Sepo form', 'sepo-forms'),
            'category'        => 'widgets',
            'attributes'      => [
                'formId'    => ['type' => 'number', 'default' => 0],
                'showTitle' => ['type' => 'boolean', 'default' => true],
            ],
            'render_callback' => static fn(array $a): string => (int) ($a['formId'] ?? 0) > 0
                ? Frontend::renderForm((int) $a['formId'], ['showTitle' => !empty($a['showTitle'])])
                : '',
        ]);
        register_block_type('sepo/forms-list', [
            'api_version'     => 2,
            'title'           => __('Sepo forms list', 'sepo-forms'),
            'category'        => 'widgets',
            'attributes'      => [
                'perPage' => ['type' => 'number', 'default' => 12],
                'view'    => ['type' => 'string', 'default' => 'grid'],
            ],
            'render_callback' => static fn(array $a): string => Frontend::indexShortcode(['per_page' => (int) ($a['perPage'] ?? 12), 'view' => (string) ($a['view'] ?? 'grid')]),
        ]);
    }

    public static function editorAssets(): void
    {
        Frontend::register();
        wp_enqueue_style('sepo-forms');
        wp_enqueue_script('sepo-forms-blocks', SPF_URL . 'assets/js/blocks.js', ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n'], Plugin::assetVersion('assets/js/blocks.js'), true);
        wp_add_inline_script('sepo-forms-blocks', 'window.SepoFormsBlocks=' . wp_json_encode(['forms' => self::choices()]) . ';', 'before');
        wp_set_script_translations('sepo-forms-blocks', 'sepo-forms');
    }

    /** Published forms for pickers. @return list<array{value:int,label:string}> */
    public static function choices(): array
    {
        $out = [];
        foreach (get_posts(['post_type' => PostType::TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC']) as $p) {
            $out[] = ['value' => (int) $p->ID, 'label' => get_the_title($p) ?: '#' . $p->ID];
        }
        return $out;
    }
}
