<?php

declare(strict_types=1);

namespace SepoForms\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use SepoForms\Embeds;
use SepoForms\Frontend;

defined('ABSPATH') || exit;

/** Elementor widget: one form. */
final class FormWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'sepo-form';
    }

    public function get_title(): string
    {
        return __('Sepo form', 'sepo-forms');
    }

    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return ['sepo-forms'];
    }

    /** @return list<string> */
    public function get_keywords(): array
    {
        return ['form', 'survey', 'feedback', 'sepo'];
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
        $options = ['0' => __('Choose a form…', 'sepo-forms')];
        foreach (Embeds::choices() as $c) {
            $options[(string) $c['value']] = $c['label'];
        }
        $this->start_controls_section('content', ['label' => __('Form', 'sepo-forms')]);
        $this->add_control('form', ['label' => __('Form', 'sepo-forms'), 'type' => Controls_Manager::SELECT, 'options' => $options, 'default' => '0']);
        $this->add_control('show_title', ['label' => __('Show the form title', 'sepo-forms'), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('accent', [
            'label'     => __('Accent colour', 'sepo-forms'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .sf-scope' => '--sf-accent: {{VALUE}};'],
        ]);
        $this->add_responsive_control('max_width', [
            'label'      => __('Largest width', 'sepo-forms'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => ['px' => ['min' => 320, 'max' => 1400]],
            'selectors'  => ['{{WRAPPER}} .sf-shell-wrapper' => 'max-width: {{SIZE}}{{UNIT}};'],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $id = (int) ($s['form'] ?? 0);
        if ($id <= 0) {
            if (\Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p class="sf-empty">' . esc_html__('Choose a form in the widget settings.', 'sepo-forms') . '</p>';
            }
            return;
        }
        echo Frontend::renderForm($id, ['showTitle' => ($s['show_title'] ?? '') === 'yes']); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }
}
