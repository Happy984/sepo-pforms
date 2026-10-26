<?php

declare(strict_types=1);

namespace SepoForms\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use SepoForms\Frontend;

defined('ABSPATH') || exit;

/** Elementor widget: the list of forms, with search and pages. */
final class FormsListWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'sepo-forms-list';
    }

    public function get_title(): string
    {
        return __('Sepo forms list', 'sepo-forms');
    }

    public function get_icon(): string
    {
        return 'eicon-bullet-list';
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return ['sepo-forms'];
    }

    /** @return list<string> */
    public function get_style_depends(): array
    {
        return ['sepo-forms'];
    }

    /** @return list<string> */
    public function get_script_depends(): array
    {
        return ['sepo-forms'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('List', 'sepo-forms')]);
        $this->add_control('per_page', ['label' => __('Forms per page', 'sepo-forms'), 'type' => Controls_Manager::NUMBER, 'min' => 3, 'max' => 48, 'default' => 12]);
        $this->add_control('view', ['label' => __('Layout', 'sepo-forms'), 'type' => Controls_Manager::SELECT, 'options' => ['grid' => __('Grid', 'sepo-forms'), 'list' => __('List', 'sepo-forms')], 'default' => 'grid']);
        $this->add_control('accent', [
            'label'     => __('Accent colour', 'sepo-forms'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .sf-scope' => '--sf-accent: {{VALUE}};'],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        echo Frontend::indexShortcode(['per_page' => (int) ($s['per_page'] ?? 12), 'view' => (string) ($s['view'] ?? 'grid')]); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }
}
