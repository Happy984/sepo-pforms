<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * A form's questions and settings.
 *
 * Questions are kept in sections. Each section is one step of the form:
 *
 *   sections: [ { title, help, questions: [ { id, question, type, required,
 *                 options[], image, help, placeholder } ] } ]
 *
 * Question ids are whole numbers, unique within the form and never reused,
 * because answers are stored by id. Renaming or moving a question keeps its
 * answers; deleting one leaves its old answers in place but unlisted.
 *
 * The flat question list the renderers use carries `group`, `_groupTitle`
 * and `_groupHelp` on every question, so code that only has the list (the
 * Google Forms client, the analytics) still knows each question's section.
 *
 * Forms can also be written as text, one `key: value` per line — the same
 * syntax as the starter templates and the "Import from text" box:
 *
 *   title: Course feedback
 *   requireLogin: true
 *   group1: About you
 *   groupHelp1: Tell us a little about yourself.
 *   question1: Your name
 *   questionRequired1: true
 *   question2: How did you hear about us?
 *   questionType2: multipleChoice
 *   questionOptions2: Friend | Social media | Search engine
 */
final class FormDefinition
{
    public const META_SECTIONS = '_spf_sections';
    public const META_SETTINGS = '_spf_settings';

    public const TYPES = ['text', 'longtext', 'multiplechoice', 'multipleselect', 'numeric', 'date', 'email', 'phone', 'url'];
    public const MAX_QUESTIONS = 200;
    public const MAX_OPTIONS = 20;
    public const MAX_SECTIONS = 50;
    public const MAX_TOPICS = 20;

    /**
     * The spellings each setting may take in imported text. The first
     * spelling is the one the plugin writes.
     */
    public const ALIASES = [
        'title'                    => ['title'],
        'description'              => ['description'],
        'requireLogin'             => ['requireLogin', 'require_login'],
        'collectEmail'             => ['collectEmail', 'collect_email'],
        'allowMultipleSubmissions' => ['allowMultipleSubmissions', 'allow_multiple_submissions'],
        'closed'                   => ['closed'],
        'submitButtonText'         => ['submitButtonText', 'submit_button_text'],
        'successMessage'           => ['successMessage', 'success_message'],
        'successRedirect'          => ['successRedirect', 'success_redirect'],
        'firstGroupTitle'          => ['firstGroupTitle', 'first_group_title'],
        'visibility'               => ['visibility'],
        'private'                  => ['private'],
        'order'                    => ['order'],
        'upload'                   => ['upload'],
        'uploadTitle'              => ['uploadTitle', 'upload_title'],
        'uploadDescription'        => ['uploadDescription', 'upload_description'],
        'uploadMaxSizeMB'          => ['uploadMaxSizeMB', 'upload_max_size_mb'],
        'uploadTopics'             => ['uploadTopics', 'upload_topics'],
    ];

    /** @var array<int,array<string,mixed>> */
    private static array $cache = [];

    // ------------------------------------------------------------ settings

    /** @return array<string,mixed> */
    public static function defaultSettings(): array
    {
        return [
            'requireLogin'             => false,
            'collectEmail'             => false,
            'allowMultipleSubmissions' => true,
            'closed'                   => false,
            'submitButtonText'         => __('Submit', 'sepo-forms'),
            'successMessage'           => __('Thanks — your response has been recorded.', 'sepo-forms'),
            'successRedirect'          => '',
            'firstGroupTitle'          => __('Details', 'sepo-forms'),
            'visibility'               => 'public',
            'order'                    => 500,
            'upload'                   => false,
            'uploadTitle'              => '',
            'uploadDescription'        => '',
            'uploadMaxSizeMB'          => 5,
            'uploadTopics'             => [],
        ];
    }

    /**
     * Clean settings from the builder or an import.
     *
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function sanitizeSettings(array $in): array
    {
        $d = self::defaultSettings();
        $b = static fn($v, bool $def): bool => $v === null ? $def : self::toBool($v, $def);
        $s = [
            'requireLogin'             => $b($in['requireLogin'] ?? null, false),
            'collectEmail'             => $b($in['collectEmail'] ?? null, false),
            'allowMultipleSubmissions' => $b($in['allowMultipleSubmissions'] ?? null, true),
            'closed'                   => $b($in['closed'] ?? null, false),
            'submitButtonText'         => sanitize_text_field((string) ($in['submitButtonText'] ?? '')) ?: $d['submitButtonText'],
            'successMessage'           => sanitize_textarea_field((string) ($in['successMessage'] ?? '')) ?: $d['successMessage'],
            'successRedirect'          => esc_url_raw(trim((string) ($in['successRedirect'] ?? ''))),
            'firstGroupTitle'          => sanitize_text_field((string) ($in['firstGroupTitle'] ?? '')) ?: $d['firstGroupTitle'],
            'visibility'               => (strtolower((string) ($in['visibility'] ?? '')) === 'private' || $b($in['private'] ?? null, false)) ? 'private' : 'public',
            'order'                    => isset($in['order']) && is_numeric($in['order']) ? (int) $in['order'] : 500,
            'upload'                   => $b($in['upload'] ?? null, false),
            'uploadTitle'              => sanitize_text_field((string) ($in['uploadTitle'] ?? '')),
            'uploadDescription'        => sanitize_textarea_field((string) ($in['uploadDescription'] ?? '')),
            'uploadMaxSizeMB'          => max(1, min(Files::maxUploadMb(), (int) ($in['uploadMaxSizeMB'] ?? 5) ?: 5)),
            'uploadTopics'             => self::sanitizeTopics($in['uploadTopics'] ?? []),
        ];
        return $s;
    }

    /**
     * File upload slots. Accepts a list of {id,label,category} or the text
     * form "id:Label:category | id:Label".
     *
     * @param mixed $raw
     * @return list<array{id:string,label:string,category:string}>
     */
    public static function sanitizeTopics($raw): array
    {
        $rows = [];
        if (is_string($raw)) {
            foreach (explode('|', $raw) as $chunk) {
                $parts = array_map('trim', explode(':', trim($chunk)));
                if (($parts[0] ?? '') !== '') {
                    $rows[] = ['id' => $parts[0], 'label' => $parts[1] ?? '', 'category' => $parts[2] ?? ''];
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $key => $v) {
                if (is_array($v)) {
                    $rows[] = ['id' => (string) ($v['id'] ?? $key), 'label' => (string) ($v['label'] ?? ''), 'category' => (string) ($v['category'] ?? '')];
                } elseif (is_string($v)) {
                    $parts = array_map('trim', explode(':', $v));
                    $rows[] = is_string($key) && !is_numeric($key)
                        ? ['id' => $key, 'label' => $parts[0] ?? '', 'category' => $parts[1] ?? '']
                        : ['id' => $parts[0] ?? '', 'label' => $parts[1] ?? '', 'category' => $parts[2] ?? ''];
                }
            }
        }
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $label = sanitize_text_field($r['label']);
            $id = sanitize_key($r['id'] !== '' ? $r['id'] : $label);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = ['id' => $id, 'label' => $label !== '' ? $label : $id, 'category' => sanitize_text_field($r['category'])];
            if (count($out) >= self::MAX_TOPICS) {
                break;
            }
        }
        return $out;
    }

    /** @param mixed $v */
    public static function toBool($v, bool $default = false): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v !== 0;
        }
        if (!is_string($v)) {
            return $default;
        }
        $s = strtolower(trim($v));
        if ($s === '') {
            return $default;
        }
        return !in_array($s, ['0', 'false', 'no', 'off', 'n'], true);
    }

    // ------------------------------------------------------------ questions

    public static function normalizeType(string $type): string
    {
        $t = strtolower(trim($type));
        return in_array($t, self::TYPES, true) ? $t : 'text';
    }

    /** @return array<string,string> */
    public static function typeLabels(): array
    {
        return [
            'text'           => __('Short text', 'sepo-forms'),
            'longtext'       => __('Paragraph', 'sepo-forms'),
            'multiplechoice' => __('Multiple choice', 'sepo-forms'),
            'multipleselect' => __('Checkboxes', 'sepo-forms'),
            'numeric'        => __('Number', 'sepo-forms'),
            'date'           => __('Date', 'sepo-forms'),
            'email'          => __('Email', 'sepo-forms'),
            'phone'          => __('Phone', 'sepo-forms'),
            'url'            => __('URL', 'sepo-forms'),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::typeLabels()[$type] ?? ucfirst($type);
    }

    /**
     * Clean sections from the builder or an import. Existing question ids
     * are kept; new questions get the next free id.
     *
     * @param mixed $raw
     * @return list<array{title:string,help:string,questions:list<array<string,mixed>>}>
     */
    public static function sanitizeSections($raw): array
    {
        $sections = [];
        $used = [];
        $pending = [];
        $count = 0;
        foreach (array_slice(is_array($raw) ? array_values($raw) : [], 0, self::MAX_SECTIONS) as $si => $sec) {
            if (!is_array($sec)) {
                continue;
            }
            $qs = [];
            foreach ((array) ($sec['questions'] ?? []) as $q) {
                if (!is_array($q) || $count >= self::MAX_QUESTIONS) {
                    continue;
                }
                $text = sanitize_text_field((string) ($q['question'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $type = self::normalizeType((string) ($q['type'] ?? 'text'));
                $options = [];
                foreach ((array) ($q['options'] ?? []) as $o) {
                    $o = sanitize_text_field((string) $o);
                    if ($o !== '') {
                        $options[] = $o;
                    }
                }
                $options = array_slice($options, 0, self::MAX_OPTIONS);
                if (in_array($type, ['multiplechoice', 'multipleselect'], true) && $options === []) {
                    $type = 'text';
                }
                $id = (int) ($q['id'] ?? 0);
                $clean = [
                    'id'          => $id,
                    'question'    => $text,
                    'type'        => $type,
                    'required'    => self::toBool($q['required'] ?? false),
                    'options'     => in_array($type, ['multiplechoice', 'multipleselect'], true) ? $options : [],
                    'image'       => esc_url_raw(trim((string) ($q['image'] ?? ''))),
                    'help'        => sanitize_text_field((string) ($q['help'] ?? '')),
                    'placeholder' => sanitize_text_field((string) ($q['placeholder'] ?? '')),
                ];
                if ($id > 0 && !isset($used[$id])) {
                    $used[$id] = true;
                } else {
                    $pending[] = [count($sections), count($qs)];
                }
                $qs[] = $clean;
                $count++;
            }
            $title = sanitize_text_field((string) ($sec['title'] ?? ''));
            if ($qs === [] && $title === '') {
                continue;
            }
            $sections[] = ['title' => $title, 'help' => sanitize_textarea_field((string) ($sec['help'] ?? '')), 'questions' => $qs];
        }
        $next = $used ? max(array_keys($used)) + 1 : 1;
        foreach ($pending as [$s, $q]) {
            if (isset($sections[$s]['questions'][$q])) {
                $sections[$s]['questions'][$q]['id'] = $next++;
            }
        }
        return array_values(array_filter($sections, static fn($s) => $s['questions'] !== [] || $s['title'] !== ''));
    }

    // ------------------------------------------------------------ load / save

    /**
     * Everything about one form.
     *
     * @return array{id:int,title:string,description:string,settings:array<string,mixed>,sections:list<array<string,mixed>>,questions:list<array<string,mixed>>,groups:list<array<string,mixed>>}
     */
    public static function load(int $postId): array
    {
        if (isset(self::$cache[$postId])) {
            return self::$cache[$postId];
        }
        $post = get_post($postId);
        $settings = get_post_meta($postId, self::META_SETTINGS, true);
        $settings = array_merge(self::defaultSettings(), is_array($settings) ? $settings : []);
        $sections = get_post_meta($postId, self::META_SECTIONS, true);
        $sections = is_array($sections) ? $sections : [];

        $settings['title'] = $post ? get_the_title($post) : '';
        $settings['description'] = $post ? (string) $post->post_excerpt : '';
        if ($settings['title'] === '') {
            $settings['title'] = __('Form', 'sepo-forms');
        }

        $questions = self::flatten($sections);
        return self::$cache[$postId] = [
            'id'          => $postId,
            'title'       => $settings['title'],
            'description' => $settings['description'],
            'settings'    => $settings,
            'sections'    => $sections,
            'questions'   => $questions,
            'groups'      => self::buildGroups($sections, $settings),
        ];
    }

    /**
     * @param list<array<string,mixed>> $sections
     * @param array<string,mixed> $settings
     */
    public static function save(int $postId, array $sections, array $settings): void
    {
        update_post_meta($postId, self::META_SECTIONS, self::sanitizeSections($sections));
        update_post_meta($postId, self::META_SETTINGS, self::sanitizeSettings($settings));
        unset(self::$cache[$postId]);
    }

    public static function forget(int $postId): void
    {
        unset(self::$cache[$postId]);
    }

    /**
     * @param list<array<string,mixed>> $sections
     * @return list<array<string,mixed>>
     */
    public static function flatten(array $sections): array
    {
        $out = [];
        foreach (array_values($sections) as $g => $sec) {
            foreach ((array) ($sec['questions'] ?? []) as $q) {
                $q['group'] = $g;
                $q['_groupTitle'] = (string) ($sec['title'] ?? '');
                $q['_groupHelp'] = (string) ($sec['help'] ?? '');
                $out[] = $q;
            }
        }
        return $out;
    }

    /**
     * Steps of the form: one per section. A section with no title takes the
     * "first step title" for the first step and "Section N" after that.
     *
     * @param list<array<string,mixed>> $sections
     * @param array<string,mixed> $settings
     * @return list<array{title:string,help:string,questions:list<array<string,mixed>>}>
     */
    public static function buildGroups(array $sections, array $settings): array
    {
        $groups = [];
        foreach (self::flatten($sections) as $q) {
            $g = (int) $q['group'];
            $groups[$g] ??= ['title' => $q['_groupTitle'], 'help' => $q['_groupHelp'], 'questions' => []];
            $groups[$g]['questions'][] = $q;
        }
        ksort($groups);
        $groups = array_values($groups);
        foreach ($groups as $i => &$g) {
            if (trim($g['title']) === '') {
                /* translators: %d: section number */
                $g['title'] = $i === 0 ? (string) $settings['firstGroupTitle'] : sprintf(__('Section %d', 'sepo-forms'), $i + 1);
            }
        }
        unset($g);
        return $groups;
    }

    // ------------------------------------------------------------ answers

    /**
     * Problems with a set of answers, by question id.
     *
     * @param list<array<string,mixed>> $questions
     * @param array<int|string,mixed> $answers
     * @return array<int,string>
     */
    public static function validate(array $questions, array $answers): array
    {
        $errors = [];
        foreach ($questions as $q) {
            $id = (int) $q['id'];
            $ans = $answers[$id] ?? ($answers[(string) $id] ?? null);
            $empty = $ans === null || $ans === '' || (is_array($ans) && $ans === []);
            if (!empty($q['required']) && $empty) {
                $errors[$id] = __('This question is required.', 'sepo-forms');
                continue;
            }
            if ($empty) {
                continue;
            }
            $one = is_array($ans) ? '' : trim((string) $ans);
            switch ($q['type']) {
                case 'email':
                    if (!is_email($one)) {
                        $errors[$id] = __('Please enter a valid email address.', 'sepo-forms');
                    }
                    break;
                case 'url':
                    if (!filter_var($one, FILTER_VALIDATE_URL)) {
                        $errors[$id] = __('Please enter a valid web address.', 'sepo-forms');
                    }
                    break;
                case 'numeric':
                    if (!is_numeric($one)) {
                        $errors[$id] = __('Please enter a number.', 'sepo-forms');
                    }
                    break;
                case 'date':
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $one)) {
                        $errors[$id] = __('Please enter a date.', 'sepo-forms');
                    }
                    break;
                case 'multiplechoice':
                    if (is_array($ans) || !ctype_digit($one) || !isset($q['options'][(int) $one])) {
                        $errors[$id] = __('Please choose one of the options.', 'sepo-forms');
                    }
                    break;
                case 'multipleselect':
                    foreach ((array) $ans as $pick) {
                        if (!is_scalar($pick) || !ctype_digit((string) $pick) || !isset($q['options'][(int) $pick])) {
                            $errors[$id] = __('Please choose from the options.', 'sepo-forms');
                            break;
                        }
                    }
                    break;
            }
        }
        return $errors;
    }

    /**
     * Answers cleaned for storage: only this form's questions, choice answers
     * as option numbers, text trimmed and length-limited.
     *
     * @param list<array<string,mixed>> $questions
     * @param array<int|string,mixed> $answers
     * @return array<int,mixed>
     */
    public static function cleanAnswers(array $questions, array $answers): array
    {
        $out = [];
        foreach ($questions as $q) {
            $id = (int) $q['id'];
            $ans = $answers[$id] ?? ($answers[(string) $id] ?? null);
            if ($ans === null || $ans === '' || $ans === []) {
                continue;
            }
            if ($q['type'] === 'multipleselect') {
                $out[$id] = array_values(array_unique(array_map(static fn($v) => (string) (int) $v, (array) $ans)));
            } elseif ($q['type'] === 'longtext') {
                $out[$id] = mb_substr(sanitize_textarea_field((string) $ans), 0, 10000);
            } else {
                $out[$id] = mb_substr(sanitize_text_field(is_array($ans) ? implode(', ', $ans) : (string) $ans), 0, 1000);
            }
        }
        return $out;
    }

    /**
     * A stored answer as readable text: choice numbers become their labels.
     *
     * @param array<string,mixed> $q
     * @param mixed $val
     */
    public static function answerToText(array $q, $val): string
    {
        if ($val === null || $val === '' || $val === []) {
            return '';
        }
        if (in_array($q['type'] ?? '', ['multiplechoice', 'multipleselect'], true)) {
            $labels = [];
            foreach ((array) $val as $i) {
                $labels[] = is_numeric($i) && isset($q['options'][(int) $i]) ? (string) $q['options'][(int) $i] : (string) $i;
            }
            return implode(', ', $labels);
        }
        return is_array($val) ? implode(', ', array_map('strval', $val)) : (string) $val;
    }

    // ------------------------------------------------------------ text format

    /**
     * Read a form written as text. A leading "---" block (front matter) is
     * accepted, so a form copied from a Markdown file imports as it is.
     *
     * @return array{title:string,description:string,settings:array<string,mixed>,sections:list<array<string,mixed>>}
     */
    public static function fromText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lookup = [];
        foreach (self::ALIASES as $concept => $spellings) {
            foreach ($spellings as $s) {
                $lookup[strtolower($s)] = $concept;
            }
        }

        $raw = [];
        $sections = [];
        $current = null;
        $sec = -1;
        $meta = [];

        $openSection = static function (string $title) use (&$sections, &$sec): void {
            $sections[] = ['title' => $title, 'help' => '', 'questions' => []];
            $sec = count($sections) - 1;
        };
        $flush = static function () use (&$current, &$sections, &$sec, $openSection): void {
            if ($current === null) {
                return;
            }
            if ($sec < 0) {
                $openSection('');
            }
            $sections[$sec]['questions'][] = $current;
            $current = null;
        };

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '---' || $line[0] === '#') {
                continue;
            }
            if (!preg_match('/^([A-Za-z][A-Za-z0-9_]*)\s*:\s*(.*)$/', $line, $m)) {
                continue;
            }
            $key = $m[1];
            $value = trim($m[2], " \t\"'");

            if (preg_match('/^group(\d+)$/i', $key)) {
                $flush();
                $openSection($value);
                continue;
            }
            if (preg_match('/^grouphelp(\d+)$/i', $key)) {
                if ($sec < 0) {
                    $openSection('');
                }
                if ($current === null) {
                    $sections[$sec]['help'] = $value;
                }
                continue;
            }
            if (preg_match('/^question(\d+)$/i', $key, $n)) {
                $flush();
                $current = ['id' => (int) $n[1], 'question' => $value, 'type' => 'text', 'required' => false, 'options' => [], 'image' => '', 'help' => '', 'placeholder' => ''];
                continue;
            }
            if (preg_match('/^question(Type|Required|Options|Image|Help|Placeholder|Group)(\d+)$/i', $key, $n)) {
                $field = strtolower($n[1]);
                $qid = (int) $n[2];
                if ($current === null || (int) $current['id'] !== $qid) {
                    // Settings written as front matter keys ("questionType3: date") for a question already flushed.
                    $meta[$field][$qid] = $value;
                    continue;
                }
                match ($field) {
                    'type'        => $current['type'] = self::normalizeType($value),
                    'required'    => $current['required'] = self::toBool($value),
                    'options'     => $current['options'] = array_slice(array_values(array_filter(array_map('trim', explode('|', $value)), 'strlen')), 0, self::MAX_OPTIONS),
                    'image'       => $current['image'] = $value,
                    'help'        => $current['help'] = $value,
                    'placeholder' => $current['placeholder'] = $value,
                    default       => null,
                };
                continue;
            }
            $concept = $lookup[strtolower($key)] ?? null;
            if ($concept !== null && !array_key_exists($concept, $raw)) {
                $raw[$concept] = $value;
            }
        }
        $flush();

        // Late per-question keys (front-matter style forms list them after every question).
        foreach ($sections as &$s) {
            foreach ($s['questions'] as &$q) {
                $id = (int) $q['id'];
                foreach ($meta as $field => $byId) {
                    if (!isset($byId[$id])) {
                        continue;
                    }
                    $v = $byId[$id];
                    match ($field) {
                        'type'        => $q['type'] = self::normalizeType($v),
                        'required'    => $q['required'] = self::toBool($v),
                        'options'     => $q['options'] = array_slice(array_values(array_filter(array_map('trim', explode('|', $v)), 'strlen')), 0, self::MAX_OPTIONS),
                        'image'       => $q['image'] = $v,
                        'help'        => $q['help'] = $v,
                        'placeholder' => $q['placeholder'] = $v,
                        default       => null,
                    };
                }
            }
            unset($q);
        }
        unset($s);

        return [
            'title'       => sanitize_text_field((string) ($raw['title'] ?? '')),
            'description' => sanitize_textarea_field((string) ($raw['description'] ?? '')),
            'settings'    => self::sanitizeSettings($raw),
            'sections'    => self::sanitizeSections($sections),
        ];
    }

    /**
     * Write a form as text, in the syntax fromText() reads. Settings equal to
     * their defaults are left out.
     *
     * @param array<string,mixed> $form From load().
     */
    public static function toText(array $form): string
    {
        $lines = ['title: ' . $form['title']];
        if ($form['description'] !== '') {
            $lines[] = 'description: ' . str_replace("\n", ' ', $form['description']);
        }
        $d = self::defaultSettings();
        foreach ($form['settings'] as $k => $v) {
            if (in_array($k, ['title', 'description'], true) || !array_key_exists($k, $d) || $v === $d[$k]) {
                continue;
            }
            if ($k === 'uploadTopics') {
                $v = implode(' | ', array_map(static fn($t) => $t['id'] . ':' . $t['label'] . ($t['category'] !== '' ? ':' . $t['category'] : ''), $v));
            }
            $lines[] = $k . ': ' . (is_bool($v) ? ($v ? 'true' : 'false') : str_replace("\n", ' ', (string) $v));
        }
        $lines[] = '';
        foreach ($form['sections'] as $i => $s) {
            $n = $i + 1;
            if ($s['title'] !== '' || $i > 0) {
                $lines[] = 'group' . $n . ': ' . ($s['title'] !== '' ? $s['title'] : 'Section ' . $n);
            }
            if ($s['help'] !== '') {
                $lines[] = 'groupHelp' . $n . ': ' . str_replace("\n", ' ', $s['help']);
            }
            foreach ($s['questions'] as $q) {
                $id = $q['id'];
                $lines[] = 'question' . $id . ': ' . $q['question'];
                if ($q['type'] !== 'text') {
                    $lines[] = 'questionType' . $id . ': ' . self::writtenType($q['type']);
                }
                if ($q['required']) {
                    $lines[] = 'questionRequired' . $id . ': true';
                }
                if ($q['options']) {
                    $lines[] = 'questionOptions' . $id . ': ' . implode(' | ', $q['options']);
                }
                foreach (['help' => 'questionHelp', 'placeholder' => 'questionPlaceholder', 'image' => 'questionImage'] as $f => $key) {
                    if ($q[$f] !== '') {
                        $lines[] = $key . $id . ': ' . $q[$f];
                    }
                }
            }
            $lines[] = '';
        }
        return rtrim(implode("\n", $lines)) . "\n";
    }

    private static function writtenType(string $type): string
    {
        return ['longtext' => 'longText', 'multiplechoice' => 'multipleChoice', 'multipleselect' => 'multipleSelect'][$type] ?? $type;
    }
}
