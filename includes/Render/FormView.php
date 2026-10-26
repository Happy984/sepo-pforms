<?php

declare(strict_types=1);

namespace SepoForms\Render;

use SepoForms\FormDefinition;

defined('ABSPATH') || exit;

/**
 * The fill-in form: a header card, a stepper with a progress bar, one page
 * per section, Back / Next / Submit, and the result message. Behaviour is in
 * forms.js (stepping, required checks, sending); styles in forms.css (.sf-*).
 */
final class FormView
{
    /**
     * @param array<string,mixed> $form From FormDefinition::load().
     * @param array{showTitle?:bool,isManager?:bool,alreadySubmitted?:bool,instance?:string} $opts
     */
    public static function render(array $form, array $opts = []): string
    {
        $s = $form['settings'];
        $groups = $form['groups'];
        $questions = $form['questions'];
        if ($questions === []) {
            return '<div class="sf-error">' . esc_html__('This form has no questions yet.', 'sepo-forms') . '</div>';
        }
        $total = max(1, count($groups));
        $instance = $opts['instance'] ?? ('sf' . $form['id'] . '-' . wp_generate_password(4, false, false));

        $html = '<div class="sf-shell" data-form-id="' . (int) $form['id'] . '" data-total-steps="' . $total . '" data-instance="' . esc_attr($instance) . '"'
            . ' data-submit-text="' . esc_attr((string) $s['submitButtonText']) . '">';
        $html .= '<div class="sf-panel is-active">';
        $html .= self::header($form, $total, !empty($opts['isManager']), $opts['showTitle'] ?? true);

        if (!empty($s['closed'])) {
            $html .= '<div class="sf-closed-banner">' . esc_html__('This form is closed and is no longer accepting responses.', 'sepo-forms') . '</div>';
        } elseif (!empty($opts['alreadySubmitted'])) {
            $html .= '<div class="sf-closed-banner">' . esc_html__('You have already submitted this form.', 'sepo-forms') . '</div>';
        } else {
            $html .= self::progress($groups, $total);
            $html .= self::form($groups, $s, $instance);
        }
        return $html . '</div></div>';
    }

    /** @param array<string,mixed> $form */
    private static function header(array $form, int $total, bool $manager, bool $showTitle): string
    {
        $n = count($form['questions']);
        $h = '<div class="sf-header">';
        if ($showTitle) {
            $h .= '<h2 class="sf-title">' . esc_html($form['title']) . '</h2>';
        }
        if ($form['description'] !== '') {
            $h .= '<p class="sf-desc">' . nl2br(esc_html($form['description'])) . '</p>';
        }
        $h .= '<div class="sf-meta">'
            /* translators: %d: number of questions */
            . '<span class="sf-meta-item">' . Icons::get('questions') . ' ' . esc_html(sprintf(_n('%d question', '%d questions', $n, 'sepo-forms'), $n)) . '</span>'
            /* translators: %d: number of steps */
            . '<span class="sf-meta-item">' . Icons::get('steps') . ' ' . esc_html(sprintf(_n('%d step', '%d steps', $total, 'sepo-forms'), $total)) . '</span>';
        if ($manager) {
            $h .= '<span class="sf-meta-item sf-meta-manager">' . Icons::get('key') . ' ' . esc_html__('Manager', 'sepo-forms') . '</span>';
        }
        return $h . '</div></div>';
    }

    /** @param list<array<string,mixed>> $groups */
    private static function progress(array $groups, int $total): string
    {
        $h = '<div class="sf-progress"><div class="sf-stepper">';
        foreach ($groups as $i => $g) {
            $h .= '<div class="sf-step' . ($i === 0 ? ' is-current' : '') . '" data-sf-step-index="' . $i . '">'
                . '<span class="sf-step-dot">' . ($i + 1) . '</span>'
                . '<span class="sf-step-label">' . esc_html((string) $g['title']) . '</span></div>';
            if ($i < $total - 1) {
                $h .= '<span class="sf-step-sep" aria-hidden="true"></span>';
            }
        }
        $h .= '</div>';
        $h .= '<div class="sf-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) round(100 / $total) . '"><span data-sf-progress-fill style="width:' . (int) round(100 / $total) . '%"></span></div>';
        /* translators: 1: step number, 2: number of steps */
        $h .= '<div class="sf-progress-text"><span data-sf-progress-label>' . esc_html(sprintf(__('Step %1$d of %2$d', 'sepo-forms'), 1, $total)) . '</span>';
        /* translators: %s: next step title */
        $h .= '<span data-sf-progress-next class="sf-next-hint">' . ($total > 1 ? esc_html(sprintf(__('Next: %s', 'sepo-forms'), (string) ($groups[1]['title'] ?? ''))) : '') . '</span></div>';
        return $h . '</div>';
    }

    /**
     * @param list<array<string,mixed>> $groups
     * @param array<string,mixed> $s
     */
    private static function form(array $groups, array $s, string $instance): string
    {
        $total = count($groups);
        $h = '<form class="sf-form" novalidate enctype="multipart/form-data" data-sf-form>';
        foreach ($groups as $i => $g) {
            $h .= '<section class="sf-step-page" data-sf-page="' . $i . '"' . ($i === 0 ? '' : ' hidden') . '>';
            $h .= '<div class="sf-group-title">' . esc_html((string) $g['title']) . '</div>';
            if ((string) $g['help'] !== '') {
                $h .= '<p class="sf-group-help">' . nl2br(esc_html((string) $g['help'])) . '</p>';
            }
            foreach ($g['questions'] as $q) {
                $h .= '<div class="sf-question" data-question-id="' . (int) $q['id'] . '"' . (!empty($q['required']) ? ' data-required="1"' : '') . '>' . self::question($q, $instance) . '</div>';
            }
            $h .= '</section>';
        }

        $last = '';
        if (!empty($s['collectEmail']) && !is_user_logged_in()) {
            $id = $instance . '-email';
            $last .= '<div class="sf-question sf-question--email" data-required="1"><label for="' . esc_attr($id) . '" class="sf-q-text">' . esc_html__('Your email', 'sepo-forms') . ' <span class="sf-req">*</span></label>'
                . '<div class="sf-field"><input type="email" name="sf_email" id="' . esc_attr($id) . '" class="sf-input" placeholder="you@example.com" autocomplete="email" required></div>'
                . '<div class="sf-q-error" data-sf-error hidden></div></div>';
        }
        if (!empty($s['upload']) && !empty($s['uploadTopics'])) {
            $last .= self::uploads($s, $instance);
        }
        if ($last !== '') {
            $h .= '<div class="sf-last-step" data-sf-last' . ($total > 1 ? ' hidden' : '') . '>' . $last . '</div>';
        }

        // Left empty by people; filled in by form-filling robots.
        $h .= '<div class="sf-hp" aria-hidden="true"><label>' . esc_html__('Leave this empty', 'sepo-forms') . '<input type="text" name="sf_website" tabindex="-1" autocomplete="off"></label></div>';

        $h .= '<div class="sf-step-error" data-sf-step-error role="alert" hidden></div>';
        $h .= '<div class="sf-nav">'
            . '<button type="button" class="sf-btn" data-sf-prev hidden>&larr; ' . esc_html__('Back', 'sepo-forms') . '</button>'
            . '<div class="sf-nav-spacer"></div>'
            . '<button type="button" class="sf-btn sf-btn-primary" data-sf-next' . ($total <= 1 ? ' hidden' : '') . '>' . esc_html__('Next', 'sepo-forms') . ' &rarr;</button>'
            . '<button type="submit" class="sf-btn sf-btn-success" data-sf-submit' . ($total > 1 ? ' hidden' : '') . '>' . esc_html((string) $s['submitButtonText']) . '</button>'
            . '</div>';
        $h .= '<div class="sf-result" data-sf-result role="status" hidden></div>';
        return $h . '</form>';
    }

    /** @param array<string,mixed> $q */
    private static function question(array $q, string $instance): string
    {
        $id = (int) $q['id'];
        $name = 'q_' . $id;
        $dom = $instance . '-q' . $id;
        $req = !empty($q['required']);
        $ph = $q['placeholder'] !== '' ? ' placeholder="' . esc_attr((string) $q['placeholder']) . '"' : '';
        $aria = ' aria-describedby="' . esc_attr($dom . '-err') . '"' . ($req ? ' aria-required="true"' : '');
        $h = '';
        if ($q['image'] !== '') {
            $h .= '<div class="sf-q-image"><img src="' . esc_url((string) $q['image']) . '" alt="" loading="lazy"></div>';
        }
        $choice = in_array($q['type'], ['multiplechoice', 'multipleselect'], true);
        $h .= $choice
            ? '<div class="sf-q-text" id="' . esc_attr($dom . '-label') . '">'
            : '<label class="sf-q-text" for="' . esc_attr($dom) . '">';
        $h .= esc_html((string) $q['question']) . ($req ? ' <span class="sf-req" aria-hidden="true">*</span>' : '');
        $h .= $choice ? '</div>' : '</label>';
        if ($q['help'] !== '') {
            $h .= '<div class="sf-q-help">' . esc_html((string) $q['help']) . '</div>';
        }
        switch ($q['type']) {
            case 'longtext':
                $h .= '<div class="sf-field"><textarea name="' . $name . '" id="' . esc_attr($dom) . '" class="sf-textarea" rows="4"' . $ph . $aria . '></textarea></div>';
                break;
            case 'multiplechoice':
            case 'multipleselect':
                $radio = $q['type'] === 'multiplechoice';
                $h .= '<div class="sf-options" role="' . ($radio ? 'radiogroup' : 'group') . '" aria-labelledby="' . esc_attr($dom . '-label') . '"' . $aria . '>';
                foreach ($q['options'] as $i => $opt) {
                    $h .= '<label class="sf-option"><input type="' . ($radio ? 'radio' : 'checkbox') . '" name="' . $name . ($radio ? '' : '[]') . '" value="' . (int) $i . '">'
                        . '<span class="sf-option-text">' . esc_html((string) $opt) . '</span></label>';
                }
                $h .= '</div>';
                break;
            default:
                $types = ['numeric' => 'number', 'email' => 'email', 'phone' => 'tel', 'url' => 'url', 'date' => 'date'];
                $type = $types[$q['type']] ?? 'text';
                $extra = $type === 'number' ? ' step="any" inputmode="decimal"' : ($type === 'email' ? ' autocomplete="email"' : ($type === 'tel' ? ' autocomplete="tel"' : ''));
                $h .= '<div class="sf-field"><input type="' . $type . '" name="' . $name . '" id="' . esc_attr($dom) . '" class="sf-input"' . ($type === 'date' ? '' : $ph) . $extra . $aria . '></div>';
        }
        return $h . '<div class="sf-q-error" id="' . esc_attr($dom . '-err') . '" data-sf-error hidden></div>';
    }

    /** @param array<string,mixed> $s */
    private static function uploads(array $s, string $instance): string
    {
        $max = (int) $s['uploadMaxSizeMB'];
        $h = '<div class="sf-upload-section" data-max-mb="' . $max . '">';
        $h .= '<div class="sf-upload-head"><span class="sf-upload-icon">' . Icons::get('upload', 18) . '</span><div>';
        $h .= '<div class="sf-upload-title">' . esc_html($s['uploadTitle'] !== '' ? (string) $s['uploadTitle'] : __('Attach files', 'sepo-forms')) . '</div>';
        if ($s['uploadDescription'] !== '') {
            $h .= '<div class="sf-upload-desc">' . nl2br(esc_html((string) $s['uploadDescription'])) . '</div>';
        }
        /* translators: %d: size in MB */
        $h .= '<div class="sf-upload-limit">' . esc_html(sprintf(__('Up to %d MB per file.', 'sepo-forms'), $max)) . '</div>';
        $h .= '</div></div><div class="sf-upload-slots">';
        foreach ($s['uploadTopics'] as $t) {
            $dom = $instance . '-f-' . $t['id'];
            $h .= '<div class="sf-upload-slot" data-sf-drop>'
                . '<div class="sf-upload-slot-top"><label class="sf-upload-label" for="' . esc_attr($dom) . '">' . esc_html($t['label']) . '</label>'
                . ($t['category'] !== '' ? '<span class="sf-upload-cat">' . esc_html($t['category']) . '</span>' : '') . '</div>'
                . '<input type="file" class="sf-upload-input" id="' . esc_attr($dom) . '" name="spf_files[' . esc_attr($t['id']) . ']">'
                . '<label class="sf-upload-drop" for="' . esc_attr($dom) . '"><span data-sf-file-name>' . esc_html__('Choose a file or drag it here', 'sepo-forms') . '</span></label>'
                . '<button type="button" class="sf-upload-clear" data-sf-file-clear hidden>' . esc_html__('Remove', 'sepo-forms') . '</button>'
                . '<div class="sf-q-error" data-sf-error hidden></div>'
                . '</div>';
        }
        return $h . '</div></div>';
    }

    /** One response, as label/answer pairs, for the response viewer. */
    public static function answersHtml(array $form, array $record): string
    {
        $h = '<dl class="sf-answers">';
        foreach ($form['questions'] as $q) {
            $v = FormDefinition::answerToText($q, $record['answers'][$q['id']] ?? ($record['answers'][(string) $q['id']] ?? ''));
            $h .= '<div class="sf-answer"><dt>' . esc_html((string) $q['question']) . '</dt><dd>' . ($v !== '' ? nl2br(esc_html($v)) : '<span class="sf-muted">—</span>') . '</dd></div>';
        }
        return $h . '</dl>';
    }
}
