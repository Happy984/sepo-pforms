<?php

declare(strict_types=1);

namespace SepoForms;

use SepoForms\Google\Settings as GoogleSettings;
use SepoForms\Render\FormView;
use SepoForms\Render\Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * /wp-json/sepo-forms/v1
 *
 *   POST /submit                 anyone (each form's own rules apply)
 *   POST /submissions/remove     form managers
 *   GET  /submissions            form managers — rows as JSON
 *   GET  /submissions/stats      form managers
 *   GET  /submission             form managers — one response as HTML
 *   GET  /tab                    form managers — the Submissions tab as HTML
 *   GET  /schema                 form managers — settings, questions, sections
 *   POST /google/build           form managers — copy to Google Forms
 *
 * Success: {"status":"success","data":{…}}; failure: a WP_Error whose message
 * is written for the person who sees it.
 */
final class Rest
{
    public const NS = 'sepo-forms/v1';
    private const RATE_LIMIT = 20;

    public static function hook(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(self::NS, '/submit', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'submit'],
            // Guests may answer; the form's own sign-in rule is checked in the handler.
            'permission_callback' => '__return_true',
        ]);
        $manager = static fn(WP_REST_Request $r): bool => PostType::canManage((int) $r->get_param('form'));
        foreach ([
            ['POST', '/submissions/remove', 'remove'],
            ['GET', '/submissions', 'rows'],
            ['GET', '/submissions/stats', 'stats'],
            ['GET', '/submission', 'one'],
            ['GET', '/tab', 'tab'],
            ['GET', '/schema', 'schema'],
            ['POST', '/google/build', 'google'],
        ] as [$method, $path, $cb]) {
            register_rest_route(self::NS, $path, [
                'methods'             => $method,
                'callback'            => [self::class, $cb],
                'permission_callback' => $manager,
                'args'                => ['form' => ['required' => true, 'type' => 'integer']],
            ]);
        }
    }

    /** @param array<string,mixed> $data */
    private static function ok(array $data = []): WP_REST_Response
    {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    private static function error(string $message, int $status, string $code = 'sepo_forms_error'): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /** @return array<string,mixed>|WP_Error */
    private static function form(WP_REST_Request $r)
    {
        $id = (int) $r->get_param('form');
        $post = get_post($id);
        if (!$post || $post->post_type !== PostType::TYPE) {
            return self::error(__('Form not found.', 'sepo-forms'), 404);
        }
        return FormDefinition::load($id);
    }

    // ------------------------------------------------------------ submit

    /** @return WP_REST_Response|WP_Error */
    public static function submit(WP_REST_Request $r)
    {
        $formId = (int) $r->get_param('form');
        $post = get_post($formId);
        if (!$post || $post->post_type !== PostType::TYPE || $post->post_status !== 'publish') {
            return self::error(__('Form not found.', 'sepo-forms'), 404);
        }
        // A filled-in hidden field means a robot: answer as if it worked, store nothing.
        if (trim((string) $r->get_param('sf_website')) !== '') {
            return self::ok(['message' => __('Thanks — your response has been recorded.', 'sepo-forms'), 'redirect' => '']);
        }
        $ipKey = 'spf_rl_' . md5(self::ip() . '|' . $formId . wp_salt('nonce'));
        $hits = (int) get_transient($ipKey);
        if ($hits >= self::RATE_LIMIT) {
            return self::error(__('Too many responses from your connection. Please wait a few minutes and try again.', 'sepo-forms'), 429);
        }

        $form = FormDefinition::load($formId);
        $s = $form['settings'];
        $userId = get_current_user_id();

        if ($form['questions'] === []) {
            return self::error(__('This form has no questions.', 'sepo-forms'), 422);
        }
        if ($s['visibility'] === 'private' && !PostType::canManage($formId)) {
            return self::error(__('This form is private.', 'sepo-forms'), 403);
        }
        if ($s['closed']) {
            return self::error(__('This form is closed and no longer accepting responses.', 'sepo-forms'), 422);
        }
        if ($s['requireLogin'] && $userId === 0) {
            return self::error(__('You must be logged in to submit this form.', 'sepo-forms'), 422);
        }
        if (!$s['allowMultipleSubmissions'] && $userId > 0 && Submissions::hasUserSubmitted($formId, $userId)) {
            return self::error(__('You have already submitted this form.', 'sepo-forms'), 422);
        }

        $answers = $r->get_param('answers');
        if (is_string($answers)) {
            $answers = json_decode($answers, true);
        }
        $answers = is_array($answers) ? $answers : [];
        $errors = FormDefinition::validate($form['questions'], $answers);

        $email = trim((string) $r->get_param('email'));
        if ($userId > 0) {
            $email = $email !== '' ? $email : (string) wp_get_current_user()->user_email;
        } elseif ($s['collectEmail'] && !is_email($email)) {
            return self::error(__('Please enter a valid email address.', 'sepo-forms'), 422, 'sepo_forms_email');
        }
        if ($errors !== []) {
            return new WP_Error('sepo_forms_invalid', __('Please fix the highlighted questions and try again.', 'sepo-forms'), ['status' => 422, 'errors' => $errors]);
        }

        try {
            $files = Files::store($formId, $s, isset($_FILES['spf_files']) && is_array($_FILES['spf_files']) ? $_FILES['spf_files'] : []); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked per file
            $record = Submissions::add($formId, FormDefinition::cleanAnswers($form['questions'], $answers), $userId, [
                'email'      => $email,
                'meta'       => $files ? ['files' => $files] : [],
                'ip'         => self::ip(),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            ]);
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage(), 422);
        }
        set_transient($ipKey, $hits + 1, 10 * MINUTE_IN_SECONDS);

        $redirect = (string) $s['successRedirect'];
        return self::ok([
            'message'  => (string) $s['successMessage'],
            'redirect' => $redirect !== '' ? wp_validate_redirect($redirect, '') : '',
            'id'       => $record['id'],
        ]);
    }

    /** The visitor's address, as the server saw it. */
    private static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return (string) (filter_var((string) apply_filters('sepo_forms_ip', $ip), FILTER_VALIDATE_IP) ?: '');
    }

    // ------------------------------------------------------------ managers

    /** @return WP_REST_Response|WP_Error */
    public static function remove(WP_REST_Request $r)
    {
        $id = sanitize_text_field((string) $r->get_param('submission_id'));
        if ($id === '') {
            return self::error(__('Form and submission ID are required.', 'sepo-forms'), 422);
        }
        if (!Submissions::delete((int) $r->get_param('form'), $id)) {
            return self::error(__('Submission not found.', 'sepo-forms'), 404);
        }
        return self::ok(['count' => Submissions::count((int) $r->get_param('form'))]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function rows(WP_REST_Request $r)
    {
        $form = self::form($r);
        if ($form instanceof WP_Error) {
            return $form;
        }
        $rows = Submissions::tableRows(Submissions::forForm((int) $form['id']), $form['questions'], (int) $form['id']);
        $search = strtolower(trim((string) $r->get_param('search')));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
                foreach ($row as $k => $v) {
                    if ($k[0] !== '_' && str_contains(strtolower((string) $v), $search)) {
                        return true;
                    }
                }
                return false;
            }));
        }
        $per = max(1, min(200, (int) ($r->get_param('per_page') ?: 20)));
        $page = max(1, (int) ($r->get_param('page') ?: 1));
        $total = count($rows);
        return self::ok([
            'rows'         => array_slice($rows, ($page - 1) * $per, $per),
            'total'        => $total,
            'per_page'     => $per,
            'current_page' => $page,
            'last_page'    => (int) max(1, ceil($total / $per)),
        ]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function stats(WP_REST_Request $r)
    {
        $subs = Submissions::forForm((int) $r->get_param('form'));
        $byDay = [];
        foreach ($subs as $s) {
            $d = wp_date('Y-m-d', (int) $s['submitted_at']);
            $byDay[$d] = ($byDay[$d] ?? 0) + 1;
        }
        ksort($byDay);
        return self::ok(Submissions::stats($subs) + ['submissions_by_day' => $byDay]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function one(WP_REST_Request $r)
    {
        $form = self::form($r);
        if ($form instanceof WP_Error) {
            return $form;
        }
        $rec = Submissions::get((int) $form['id'], sanitize_text_field((string) $r->get_param('submission_id')));
        if ($rec === null) {
            return self::error(__('Submission not found.', 'sepo-forms'), 404);
        }
        $files = Files::listFor($rec, (int) $form['id']);
        $html = '<div class="sf-response-meta"><span>' . esc_html(Submissions::respondent($rec)) . '</span><span>' . esc_html(wp_date('M j, Y g:i a', $rec['submitted_at'])) . '</span></div>'
            . FormView::answersHtml($form, $rec);
        if ($files) {
            $html .= '<div class="sf-response-files"><strong>' . esc_html__('Files', 'sepo-forms') . '</strong><ul>';
            foreach ($files as $f) {
                $html .= '<li><a href="' . esc_url($f['url']) . '">' . esc_html($f['label'] . ': ' . $f['name']) . '</a> <span class="sf-muted">' . esc_html($f['size']) . '</span></li>';
            }
            $html .= '</ul></div>';
        }
        return self::ok(['html' => $html]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function tab(WP_REST_Request $r)
    {
        $form = self::form($r);
        if ($form instanceof WP_Error) {
            return $form;
        }
        $kind = (string) $r->get_param('kind');
        $html = match ($kind) {
            'settings' => Manager::settingsTab($form),
            default    => Manager::submissionsTabCached($form),
        };
        return self::ok(['html' => $html, 'count' => Submissions::count((int) $form['id'])]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function schema(WP_REST_Request $r)
    {
        $form = self::form($r);
        if ($form instanceof WP_Error) {
            return $form;
        }
        return self::ok(['settings' => $form['settings'], 'questions' => $form['questions'], 'groups' => $form['groups'], 'text' => FormDefinition::toText($form)]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function google(WP_REST_Request $r)
    {
        $form = self::form($r);
        if ($form instanceof WP_Error) {
            return $form;
        }
        $client = GoogleSettings::isConfigured() ? GoogleSettings::client() : null;
        if ($client === null) {
            return self::error(__('Google Forms is not connected. Set it up under Forms → Settings.', 'sepo-forms'), 503);
        }
        if ($form['questions'] === []) {
            return self::error(__('This form has no questions yet.', 'sepo-forms'), 422);
        }
        $skipped = [];
        if ($form['settings']['upload'] && $form['settings']['uploadTopics']) {
            $skipped[] = __('File upload slots (the Google Forms API cannot create them)', 'sepo-forms');
        }
        $result = $client->build(['title' => $form['title'], 'description' => $form['description'], 'questions' => $form['questions']]);
        if (!$result['ok']) {
            return self::error($result['error'] !== '' ? $result['error'] : __('Google rejected the request.', 'sepo-forms'), 502);
        }
        $history = (array) get_post_meta((int) $form['id'], '_spf_google', true);
        $history[] = ['form_id' => $result['form_id'], 'edit_uri' => $result['edit_uri'], 'responder_uri' => $result['responder_uri'], 'time' => time(), 'user' => get_current_user_id()];
        update_post_meta((int) $form['id'], '_spf_google', array_slice($history, -10));
        return self::ok([
            'form_id'       => $result['form_id'],
            'responder_uri' => $result['responder_uri'],
            'edit_uri'      => $result['edit_uri'],
            'skipped'       => array_values(array_unique(array_merge($skipped, $result['skipped']))),
        ]);
    }
}
