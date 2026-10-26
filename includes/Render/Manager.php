<?php

declare(strict_types=1);

namespace SepoForms\Render;

use SepoForms\FormDefinition;
use SepoForms\Google\Settings as GoogleSettings;
use SepoForms\PostType;
use SepoForms\Submissions;

defined('ABSPATH') || exit;

/**
 * Everything a form's managers see around the form: the Submissions tab
 * (stat tiles, a per-question Summary with charts, and an Individual table),
 * the Settings tab and the Google Forms tab.
 *
 * The Submissions tab reads and tallies every response, so the page only
 * carries a placeholder; forms.js fetches the tab from the REST API when it
 * is first opened, and the result is cached until a response is added or
 * removed or the questions change.
 */
final class Manager
{
    /**
     * The whole form page: the form alone for visitors, the form plus the
     * manager tabs for people who can edit it.
     *
     * @param array<string,mixed> $form
     * @param array{showTitle?:bool,alreadySubmitted?:bool,lazy?:bool,defaultTab?:int} $opts
     */
    public static function shell(array $form, array $opts = []): string
    {
        $manager = PostType::canManage((int) $form['id']);
        $formHtml = FormView::render($form, [
            'showTitle'        => $opts['showTitle'] ?? true,
            'isManager'        => $manager,
            'alreadySubmitted' => $opts['alreadySubmitted'] ?? false,
        ]);
        if (!$manager) {
            return '<div class="sf-shell-wrapper" data-form-id="' . (int) $form['id'] . '">' . $formHtml . '</div>';
        }
        $count = Submissions::count((int) $form['id']);
        $tabs = [
            ['label' => __('Form', 'sepo-forms'), 'icon' => 'form', 'content' => $formHtml],
            [
                'label'   => __('Submissions', 'sepo-forms'),
                'icon'    => 'inbox',
                'badge'   => (string) $count,
                'content' => ($opts['lazy'] ?? true) ? self::lazy((int) $form['id']) : self::submissionsTab($form),
            ],
            ['label' => __('Settings', 'sepo-forms'), 'icon' => 'settings', 'content' => self::settingsTab($form)],
            ['label' => __('Google Forms', 'sepo-forms'), 'icon' => 'share', 'content' => self::googleTab($form)],
        ];
        return '<div class="sf-shell-wrapper" data-form-id="' . (int) $form['id'] . '" data-manager="1">'
            . Tabs::render(['id' => 'sf-main-' . $form['id'], 'style' => 'underline', 'defaultTab' => $opts['defaultTab'] ?? 0, 'tabs' => $tabs])
            . '</div>';
    }

    public static function lazy(int $formId): string
    {
        return '<div class="sf-tab-lazy" data-sf-tab-kind="submissions" data-sf-tab-form="' . $formId . '"><div class="sf-tab-spinner">' . esc_html__('Loading submissions…', 'sepo-forms') . '</div></div>';
    }

    // ------------------------------------------------------------ submissions

    /** @param array<string,mixed> $form */
    public static function submissionsTabCached(array $form): string
    {
        $subs = Submissions::forForm((int) $form['id']);
        $newest = $subs ? (int) $subs[0]['submitted_at'] : 0;
        $key = 'spf_subs_' . md5($form['id'] . '|' . md5((string) wp_json_encode($form['questions'])) . '|' . count($subs) . '|' . $newest . '|' . get_locale() . '|' . SPF_VERSION);
        $html = get_transient($key);
        if (is_string($html) && $html !== '') {
            return $html;
        }
        $html = self::submissionsTab($form);
        set_transient($key, $html, 5 * MINUTE_IN_SECONDS);
        return $html;
    }

    /** @param array<string,mixed> $form */
    public static function submissionsTab(array $form): string
    {
        $formId = (int) $form['id'];
        $subs = Submissions::forForm($formId);
        if ($subs === []) {
            return '<div class="sf-empty">' . esc_html__('No submissions yet.', 'sepo-forms') . '</div>';
        }
        $stats = Submissions::stats($subs);
        $h = '<div class="sf-stats">'
            . self::stat((string) $stats['total_submissions'], __('Responses', 'sepo-forms'))
            . self::stat((string) $stats['unique_users'], __('Signed-in users', 'sepo-forms'))
            . self::stat((string) $stats['guest_submissions'], __('Guests', 'sepo-forms'));
        if ($stats['last_submission'] > 0) {
            $h .= self::stat(wp_date('M j, Y', $stats['last_submission']), __('Latest', 'sepo-forms'), true);
        }
        $h .= '</div>';

        $h .= Tabs::render([
            'id'    => 'sf-subs-' . $formId,
            'style' => 'pills',
            'tabs'  => [
                ['label' => __('Summary', 'sepo-forms'), 'icon' => 'chart', 'content' => self::analytics($form['questions'], $subs)],
                ['label' => __('Individual', 'sepo-forms'), 'icon' => 'list', 'content' => self::table($form, $subs)],
            ],
        ]);
        return $h;
    }

    private static function stat(string $value, string $label, bool $small = false): string
    {
        return '<div class="sf-stat"><span class="val' . ($small ? ' val--sm' : '') . '">' . esc_html($value) . '</span><span class="lbl">' . esc_html($label) . '</span></div>';
    }

    /**
     * @param array<string,mixed> $form
     * @param list<array<string,mixed>> $subs
     */
    private static function table(array $form, array $subs): string
    {
        $formId = (int) $form['id'];
        $columns = [
            ['field' => 'submitted_at', 'label' => __('Submitted', 'sepo-forms'), 'sortKey' => '_ts'],
            ['field' => 'respondent', 'label' => __('Respondent', 'sepo-forms')],
        ];
        foreach ($form['questions'] as $q) {
            $columns[] = ['field' => 'q_' . $q['id'], 'label' => (string) $q['question']];
        }
        $rows = Submissions::tableRows($subs, $form['questions'], $formId);
        $hasFiles = false;
        foreach ($rows as $r) {
            if (isset($r['_files'])) {
                $hasFiles = true;
                break;
            }
        }
        if ($hasFiles) {
            $columns[] = ['field' => '_files', 'label' => __('Files', 'sepo-forms'), 'kind' => 'files', 'sortable' => false];
        }
        $slug = (string) get_post_field('post_name', $formId);
        return '<div class="sp-table-wrapper" data-sp-table'
            . ' data-rows="' . esc_attr((string) wp_json_encode($rows)) . '"'
            . ' data-columns="' . esc_attr((string) wp_json_encode($columns)) . '"'
            . ' data-form-id="' . $formId . '"'
            . ' data-export-name="' . esc_attr(($slug !== '' ? $slug : 'form') . '_submissions') . '"'
            . ' data-export-url="' . esc_url(Submissions::exportUrl($formId)) . '"'
            . ' data-per-page="10"></div>';
    }

    /**
     * One question's answers, counted for its chart.
     *
     * @param array<string,mixed> $q
     * @param list<array<string,mixed>> $subs
     * @return array<string,mixed>
     */
    public static function tally(array $q, array $subs): array
    {
        $type = FormDefinition::normalizeType((string) ($q['type'] ?? 'text'));
        $opts = array_values((array) ($q['options'] ?? []));
        $id = (int) $q['id'];
        $values = [];
        foreach ($subs as $s) {
            $v = $s['answers'][$id] ?? ($s['answers'][(string) $id] ?? null);
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $values[] = $v;
        }
        $answered = count($values);
        $palette = Charts::palette();
        $result = ['type' => $type, 'question' => (string) $q['question'], 'answered' => $answered, 'total' => count($subs), 'chart' => 'text', 'segments' => [], 'stats' => []];

        if (in_array($type, ['multiplechoice', 'multipleselect'], true)) {
            $counts = [];
            $other = [];
            foreach ($values as $v) {
                foreach ((array) $v as $p) {
                    if (is_numeric($p) && isset($opts[(int) $p])) {
                        $counts[(int) $p] = ($counts[(int) $p] ?? 0) + 1;
                    } else {
                        $other[(string) $p] = ($other[(string) $p] ?? 0) + 1;
                    }
                }
            }
            $segments = [];
            $ci = 0;
            foreach ($opts as $i => $label) {
                $segments[] = ['label' => (string) $label, 'count' => (int) ($counts[$i] ?? 0), 'color' => $palette[$ci++ % count($palette)]];
            }
            foreach ($other as $label => $c) {
                $segments[] = ['label' => (string) $label, 'count' => (int) $c, 'color' => $palette[$ci++ % count($palette)]];
            }
            $base = max(1, $answered);
            foreach ($segments as &$seg) {
                $seg['pct'] = round($seg['count'] / $base * 100, 1);
            }
            unset($seg);
            $result['segments'] = $segments;
            $result['chart'] = $type === 'multiplechoice' ? 'pie' : 'bar';
            return $result;
        }

        if ($type === 'numeric') {
            $nums = [];
            foreach ($values as $v) {
                if (!is_array($v) && is_numeric($v)) {
                    $nums[] = (float) $v;
                }
            }
            if ($nums !== []) {
                $fmt = static fn(float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                $result['stats'] = ['count' => count($nums), 'avg' => $fmt(array_sum($nums) / count($nums)), 'min' => $fmt(min($nums)), 'max' => $fmt(max($nums))];
                $freq = [];
                foreach ($nums as $n) {
                    $freq[$fmt($n)] = ($freq[$fmt($n)] ?? 0) + 1;
                }
                if (count($freq) <= 12) {
                    ksort($freq, SORT_NUMERIC);
                    $ci = 0;
                    foreach ($freq as $k => $c) {
                        $result['segments'][] = ['label' => (string) $k, 'count' => $c, 'pct' => round($c / count($nums) * 100, 1), 'color' => $palette[$ci++ % count($palette)]];
                    }
                }
            }
            $result['chart'] = 'numeric';
            return $result;
        }

        if ($type === 'date') {
            $dates = [];
            foreach ($values as $v) {
                $t = is_array($v) ? false : strtotime((string) $v);
                if ($t !== false) {
                    $dates[] = $t;
                }
            }
            if ($dates !== []) {
                $result['stats'] = ['count' => count($dates), 'earliest' => wp_date('M j, Y', min($dates)), 'latest' => wp_date('M j, Y', max($dates))];
            }
            $result['chart'] = 'date';
        }
        return $result;
    }

    /**
     * @param list<array<string,mixed>> $questions
     * @param list<array<string,mixed>> $subs
     */
    public static function analytics(array $questions, array $subs): string
    {
        $cards = '';
        $n = 0;
        foreach ($questions as $q) {
            $n++;
            $t = self::tally($q, $subs);
            switch ($t['chart']) {
                case 'pie':
                    $body = $t['answered'] > 0
                        ? '<div class="sp-chart-pie-wrap"><div class="sp-chart-donut-wrap">' . Charts::donut($t['segments'], $t['answered']) . '</div>' . Charts::legend($t['segments']) . '</div>'
                        : Charts::empty(__('No responses yet.', 'sepo-forms'));
                    break;
                case 'bar':
                    $body = Charts::bar($t['segments']);
                    break;
                case 'numeric':
                    $body = Charts::numericStats($t['stats']) . ($t['segments'] ? '<div class="sp-chart-numdist">' . Charts::bar($t['segments']) . '</div>' : '');
                    break;
                case 'date':
                    $body = Charts::dateStats($t['stats']);
                    break;
                default:
                    $body = Charts::textSummary($t['answered']);
            }
            $cards .= '<div class="sf-qa-card"><div class="sf-qa-head">'
                . '<div class="sf-qa-q"><span class="sf-qa-num">Q' . $n . '</span> ' . esc_html($t['question']) . '</div>'
                . '<div class="sf-qa-meta"><span class="sf-qa-type">' . esc_html(FormDefinition::typeLabel($t['type'])) . '</span>'
                /* translators: 1: answered count, 2: total responses */
                . '<span class="sf-qa-resp">' . esc_html(sprintf(__('%1$d/%2$d answered', 'sepo-forms'), $t['answered'], $t['total'])) . '</span></div>'
                . '</div><div class="sf-qa-body">' . $body . '</div></div>';
        }
        return $cards === '' ? Charts::empty(__('Nothing to summarise yet.', 'sepo-forms')) : '<div class="sf-analytics">' . $cards . '</div>';
    }

    // ------------------------------------------------------------ settings

    /** @param array<string,mixed> $form */
    public static function settingsTab(array $form): string
    {
        $s = $form['settings'];
        $yes = static fn(bool $b): string => $b ? __('Yes', 'sepo-forms') : __('No', 'sepo-forms');
        $rows = [
            __('Title', 'sepo-forms')                  => $form['title'],
            __('Status', 'sepo-forms')                 => $s['closed'] ? __('Closed', 'sepo-forms') : __('Open', 'sepo-forms'),
            __('Visibility', 'sepo-forms')             => $s['visibility'] === 'private' ? __('Private', 'sepo-forms') : __('Public', 'sepo-forms'),
            __('Sign-in required', 'sepo-forms')       => $yes((bool) $s['requireLogin']),
            __('Collect email (guests)', 'sepo-forms') => $yes((bool) $s['collectEmail']),
            __('Multiple submissions', 'sepo-forms')   => $yes((bool) $s['allowMultipleSubmissions']),
            __('Questions', 'sepo-forms')              => (string) count($form['questions']),
            __('Steps', 'sepo-forms')                  => (string) count($form['groups']),
            __('Submit button', 'sepo-forms')          => (string) $s['submitButtonText'],
            __('After submitting', 'sepo-forms')       => $s['successRedirect'] !== '' ? (string) $s['successRedirect'] : (string) $s['successMessage'],
            __('File uploads', 'sepo-forms')           => $s['upload'] && $s['uploadTopics'] ? implode(', ', array_column($s['uploadTopics'], 'label')) : __('Off', 'sepo-forms'),
        ];
        $h = '<div class="sf-settings-grid">';
        foreach ($rows as $k => $v) {
            $h .= '<div class="sf-setting"><div class="k">' . esc_html($k) . '</div><div class="v">' . esc_html($v) . '</div></div>';
        }
        $h .= '</div>';
        if ($form['groups']) {
            $h .= '<div class="sf-note"><strong>' . esc_html__('Sections', 'sepo-forms') . '</strong><ul class="sf-sections-list">';
            foreach ($form['groups'] as $g) {
                $h .= '<li><div class="t">' . esc_html((string) $g['title']) . '</div>'
                    /* translators: %d: number of questions */
                    . '<div class="h">' . esc_html(sprintf(_n('%d question', '%d questions', count($g['questions']), 'sepo-forms'), count($g['questions']))) . ((string) $g['help'] !== '' ? ' · ' . esc_html((string) $g['help']) : '') . '</div></li>';
            }
            $h .= '</ul></div>';
        }
        $edit = get_edit_post_link((int) $form['id'], 'raw');
        if ($edit) {
            $h .= '<p class="sf-note sf-note--plain">' . esc_html__('Change the questions and settings in the form builder.', 'sepo-forms')
                . ' <a class="sf-link" href="' . esc_url($edit) . '">' . esc_html__('Edit this form', 'sepo-forms') . ' &rarr;</a></p>';
        }
        return $h;
    }

    // ------------------------------------------------------------ Google

    /** @param array<string,mixed> $form */
    public static function googleTab(array $form): string
    {
        $configured = GoogleSettings::isConfigured();
        $settingsUrl = admin_url('edit.php?post_type=' . PostType::TYPE . '&page=sepo-forms-settings');
        $h = '<div class="sf-google" data-form-id="' . (int) $form['id'] . '"><div class="sf-google-card">';
        if ($configured) {
            $h .= '<h3 class="sf-google-title">' . esc_html__('Copy this form to Google Forms', 'sepo-forms') . '</h3>'
                . '<p class="sf-google-note">' . esc_html__('Builds the same questions, sections and section instructions in your Google account as a new form. Google Forms cannot take file uploads through its API, so upload slots are skipped — you will be told which, if any.', 'sepo-forms') . '</p>'
                . '<button type="button" class="sf-google-btn sf-google-btn--primary" data-sf-google-build>' . esc_html__('Create on Google Forms', 'sepo-forms') . '</button>'
                . '<div class="sf-google-result" data-sf-google-result hidden></div>'
                . (current_user_can('manage_options') ? '<p class="sf-google-footnote">' . esc_html__('The connection is set up once for the whole site, under', 'sepo-forms') . ' <a href="' . esc_url($settingsUrl) . '">' . esc_html__('Forms → Settings', 'sepo-forms') . '</a>.</p>' : '');
        } else {
            $h .= '<h3 class="sf-google-title">' . esc_html__('Google Forms is not connected', 'sepo-forms') . '</h3>'
                . '<p class="sf-google-note">' . esc_html__('The connection is set up once for the whole site, not per form.', 'sepo-forms') . ' '
                . (current_user_can('manage_options')
                    ? esc_html__('Add the OAuth client and refresh token under', 'sepo-forms') . ' <a href="' . esc_url($settingsUrl) . '">' . esc_html__('Forms → Settings', 'sepo-forms') . '</a>, ' . esc_html__('switch the integration on there, and this button appears.', 'sepo-forms')
                    : esc_html__('Ask a site administrator to connect it.', 'sepo-forms'))
                . '</p>';
        }
        return $h . '</div></div>';
    }
}
