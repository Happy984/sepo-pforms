<?php

declare(strict_types=1);

namespace SepoForms\Render;

defined('ABSPATH') || exit;

/**
 * Tabs in the underline, pills or cards style. Each tab set only controls its
 * own direct-child buttons and panels, so tab sets can nest. The behaviour is
 * in forms.js; the styles are in forms.css under .sp-tabs-lite-*.
 */
final class Tabs
{
    /**
     * @param array{id:string,style?:string,defaultTab?:int,className?:string,tabs:list<array{label:string,icon?:string,badge?:string,content:string}>} $o
     */
    public static function render(array $o): string
    {
        $tabs = $o['tabs'];
        if ($tabs === []) {
            return '';
        }
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $o['id']) ?: 'tabs-' . wp_generate_password(6, false);
        $style = in_array($o['style'] ?? 'underline', ['underline', 'pills', 'cards'], true) ? $o['style'] : 'underline';
        $default = (int) ($o['defaultTab'] ?? 0);
        if ($default < 0 || $default >= count($tabs)) {
            $default = 0;
        }
        $html = '<div class="sp-tabs-lite sp-tabs-lite-' . $style . ' ' . esc_attr($o['className'] ?? '') . '" data-tabs-id="' . esc_attr($id) . '" data-default-tab="' . $default . '">';
        $html .= '<div class="sp-tabs-lite-nav" role="tablist">';
        foreach ($tabs as $i => $t) {
            $on = $i === $default;
            $html .= '<button type="button" class="sp-tabs-lite-btn' . ($on ? ' active' : '') . '" role="tab" id="sp-tab-' . $id . '-' . $i . '" aria-controls="sp-panel-' . $id . '-' . $i . '" aria-selected="' . ($on ? 'true' : 'false') . '" tabindex="' . ($on ? '0' : '-1') . '" data-tab-index="' . $i . '">'
                . (!empty($t['icon']) ? '<span class="sp-tabs-lite-icon">' . Icons::get($t['icon']) . '</span>' : '')
                . '<span class="sp-tabs-lite-label">' . esc_html($t['label']) . '</span>'
                . (isset($t['badge']) && $t['badge'] !== '' ? '<span class="sp-tabs-lite-badge">' . esc_html((string) $t['badge']) . '</span>' : '')
                . '</button>';
        }
        $html .= '</div><div class="sp-tabs-lite-panels">';
        foreach ($tabs as $i => $t) {
            $on = $i === $default;
            $html .= '<div class="sp-tabs-lite-panel' . ($on ? ' active' : '') . '" role="tabpanel" id="sp-panel-' . $id . '-' . $i . '" aria-labelledby="sp-tab-' . $id . '-' . $i . '" data-panel-index="' . $i . '"' . ($on ? '' : ' hidden') . '>'
                . $t['content'] . '</div>';
        }
        return $html . '</div></div>';
    }
}
