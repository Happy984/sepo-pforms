<?php

declare(strict_types=1);

namespace SepoForms\Render;

defined('ABSPATH') || exit;

/**
 * Small server-drawn charts for the response summary: donut with legend,
 * horizontal bars, and stat tiles. Pure SVG and HTML; the styles live in
 * forms.css under .sp-chart-*.
 */
final class Charts
{
    /**
     * Donut chart. The viewBox is 42 units with radius 15.915, so the
     * circumference is 100 and a slice's stroke length is its percentage.
     * The wrapper (.sp-chart-donut-wrap) sets the drawn size.
     *
     * @param list<array{label:string,count:int,color:string}> $segments
     */
    public static function donut(array $segments, int $total): string
    {
        $shown = array_values(array_filter($segments, static fn($s) => (int) ($s['count'] ?? 0) > 0));
        if ($shown === [] || $total <= 0) {
            return self::empty(__('No data', 'sepo-forms'));
        }
        $r = 15.91549430918954;
        $sw = 6;
        $svg = '<svg viewBox="0 0 42 42" class="sp-chart-donut" role="img" aria-label="' . esc_attr__('Response distribution', 'sepo-forms') . '">';
        $svg .= '<circle class="sp-chart-track" cx="21" cy="21" r="' . $r . '" fill="transparent" stroke-width="' . $sw . '"></circle>';
        $start = 0.0;
        foreach ($shown as $s) {
            $pct = (int) $s['count'] / $total * 100;
            $offset = fmod((25 - $start) + 100, 100);
            $svg .= '<circle cx="21" cy="21" r="' . $r . '" fill="transparent" stroke="' . esc_attr((string) $s['color']) . '" stroke-width="' . $sw . '" '
                . 'stroke-dasharray="' . round($pct, 3) . ' ' . round(100 - $pct, 3) . '" stroke-dashoffset="' . round($offset, 3) . '"></circle>';
            $start += $pct;
        }
        $svg .= '<text x="21" y="20.6" text-anchor="middle" class="sp-chart-donut-num">' . (int) $total . '</text>'
            . '<text x="21" y="25" text-anchor="middle" class="sp-chart-donut-lbl">' . esc_html__('responses', 'sepo-forms') . '</text></svg>';
        return $svg;
    }

    /** @param list<array{label:string,count:int,pct?:float,color:string}> $segments */
    public static function bar(array $segments): string
    {
        $max = 0;
        foreach ($segments as $s) {
            $max = max($max, (int) ($s['count'] ?? 0));
        }
        if ($max <= 0) {
            return self::empty(__('No responses yet.', 'sepo-forms'));
        }
        $html = '<div class="sp-chart-bars">';
        foreach ($segments as $s) {
            $count = (int) ($s['count'] ?? 0);
            $w = round($count / $max * 100, 1);
            $label = esc_html((string) ($s['label'] ?? ''));
            $html .= '<div class="sp-chart-bar-row">'
                . '<div class="sp-chart-bar-label" title="' . $label . '">' . $label . '</div>'
                . '<div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:' . $w . '%;background:' . esc_attr((string) $s['color']) . '"></div></div>'
                . '<div class="sp-chart-bar-val">' . $count . ' <span class="sp-chart-bar-pct">(' . esc_html((string) ($s['pct'] ?? 0)) . '%)</span></div>'
                . '</div>';
        }
        return $html . '</div>';
    }

    /** @param list<array{label:string,count:int,pct?:float,color:string}> $segments */
    public static function legend(array $segments): string
    {
        $html = '<ul class="sp-chart-legend">';
        foreach ($segments as $s) {
            if ((int) ($s['count'] ?? 0) <= 0) {
                continue;
            }
            $html .= '<li class="sp-chart-legend-item">'
                . '<span class="sp-chart-legend-dot" style="background:' . esc_attr((string) $s['color']) . '"></span>'
                . '<span class="sp-chart-legend-label">' . esc_html((string) $s['label']) . '</span>'
                . '<span class="sp-chart-legend-val">' . (int) $s['count'] . ' &middot; ' . esc_html((string) ($s['pct'] ?? 0)) . '%</span>'
                . '</li>';
        }
        return $html . '</ul>';
    }

    /** @param array<string,mixed> $stats */
    public static function numericStats(array $stats): string
    {
        if ($stats === []) {
            return '<div class="sp-chart-empty">' . esc_html__('No numeric responses yet.', 'sepo-forms') . '</div>';
        }
        return self::tiles([
            [__('Average', 'sepo-forms'), $stats['avg'] ?? 0],
            [__('Min', 'sepo-forms'), $stats['min'] ?? 0],
            [__('Max', 'sepo-forms'), $stats['max'] ?? 0],
            [__('Responses', 'sepo-forms'), $stats['count'] ?? 0],
        ]);
    }

    /** @param array<string,mixed> $stats */
    public static function dateStats(array $stats): string
    {
        if ($stats === []) {
            return '<div class="sp-chart-empty">' . esc_html__('No date responses yet.', 'sepo-forms') . '</div>';
        }
        return self::tiles([
            [__('Responses', 'sepo-forms'), $stats['count'] ?? 0],
            [__('Earliest', 'sepo-forms'), $stats['earliest'] ?? '-'],
            [__('Latest', 'sepo-forms'), $stats['latest'] ?? '-'],
        ]);
    }

    public static function textSummary(int $count): string
    {
        return '<div class="sp-chart-text"><span class="sp-chart-count">' . $count . '</span> '
            . esc_html(_n('text response', 'text responses', $count, 'sepo-forms'))
            . '<div class="sp-chart-hint">' . esc_html__('Switch to Individual to read them.', 'sepo-forms') . '</div></div>';
    }

    public static function empty(string $message): string
    {
        return '<div class="sp-chart-empty">' . esc_html($message) . '</div>';
    }

    /** @return list<string> */
    public static function palette(): array
    {
        return ['#2ea043', '#0073aa', '#d29922', '#8b949e', '#1d475a', '#a371f7', '#db61a2', '#3fb950', '#e3654a', '#58a6ff', '#f0883e', '#6e7681'];
    }

    /** @param list<array{0:string,1:mixed}> $items */
    private static function tiles(array $items): string
    {
        $html = '<div class="sp-chart-numstats">';
        foreach ($items as [$label, $value]) {
            $html .= '<div class="sp-chart-numstat"><span class="v">' . esc_html((string) $value) . '</span><span class="k">' . esc_html($label) . '</span></div>';
        }
        return $html . '</div>';
    }
}
