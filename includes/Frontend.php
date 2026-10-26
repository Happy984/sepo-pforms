<?php

declare(strict_types=1);

namespace SepoForms;

use SepoForms\Render\Gate;
use SepoForms\Render\Index;
use SepoForms\Render\Manager;

defined('ABSPATH') || exit;

/**
 * Forms on the site: each form's own page, the [sepo_form] and [sepo_forms]
 * shortcodes, and the scripts and styles they need.
 */
final class Frontend
{
    public static function hook(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'register']);
        add_action('enqueue_block_assets', [self::class, 'register']);
        add_filter('the_content', [self::class, 'content'], 20);
        add_action('init', static function (): void {
            add_shortcode('sepo_form', [self::class, 'formShortcode']);
            add_shortcode('sepo_forms', [self::class, 'indexShortcode']);
        });
        add_filter('body_class', static function (array $c): array {
            if (is_singular(PostType::TYPE)) {
                $c[] = 'sepo-form-page';
            }
            return $c;
        });
        // Responses carry personal data: keep form pages out of shared caches for signed-in managers.
        add_action('template_redirect', static function (): void {
            if (is_singular(PostType::TYPE) && PostType::canManage((int) get_queried_object_id())) {
                nocache_headers();
            }
        });
    }

    public static function register(): void
    {
        if (wp_style_is('sepo-forms', 'registered')) {
            return;
        }
        wp_register_style('sepo-forms', SPF_URL . 'assets/css/forms.css', [], Plugin::assetVersion('assets/css/forms.css'));
        wp_register_script('sepo-forms', SPF_URL . 'assets/js/forms.js', [], Plugin::assetVersion('assets/js/forms.js'), true);
        $accent = Settings::get('accent');
        if ($accent !== '') {
            wp_add_inline_style('sepo-forms', '.sf-scope{--sf-accent:' . $accent . ';--sf-on-accent:' . Settings::textOn($accent) . ';}');
        }
        // Printed with the script wherever it loads, including page builders that load it on their own.
        wp_add_inline_script('sepo-forms', 'window.SepoFormsConfig=' . wp_json_encode(self::config()) . ';', 'before');
    }

    /** Load the styles and script, and the configuration the script reads. */
    public static function enqueue(): void
    {
        self::register();
        wp_enqueue_style('sepo-forms');
        wp_enqueue_script('sepo-forms');
    }

    /** @return array<string,mixed> */
    private static function config(): array
    {
        return [
            'api'   => esc_url_raw(rest_url(Rest::NS)),
            // Only signed-in visitors get a nonce: a cached page would carry a stale one for guests.
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'i18n'  => [
                'step'          => __('Step %1$d of %2$d', 'sepo-forms'),
                'next'          => __('Next: %s', 'sepo-forms'),
                'last'          => __('Last step', 'sepo-forms'),
                'required'      => __('This question is required.', 'sepo-forms'),
                'requiredStep'  => __('Please answer the required questions before continuing.', 'sepo-forms'),
                'requiredSend'  => __('Please answer the required questions before submitting.', 'sepo-forms'),
                'email'         => __('Please enter a valid email address.', 'sepo-forms'),
                'url'           => __('Please enter a valid web address.', 'sepo-forms'),
                'number'        => __('Please enter a number.', 'sepo-forms'),
                'tooBig'        => __('This file is larger than %d MB.', 'sepo-forms'),
                'chooseFile'    => __('Choose a file or drag it here', 'sepo-forms'),
                'submitting'    => __('Submitting…', 'sepo-forms'),
                'submitted'     => __('Submitted', 'sepo-forms'),
                'recorded'      => __('Your response has been recorded.', 'sepo-forms'),
                'couldNot'      => __('Could not submit', 'sepo-forms'),
                'tryAgain'      => __('Please try again.', 'sepo-forms'),
                'network'       => __('Network error. Check your connection and try again.', 'sepo-forms'),
                'leave'         => __('You have unsaved answers. Leave anyway?', 'sepo-forms'),
                'loading'       => __('Loading…', 'sepo-forms'),
                'loadFail'      => __('Could not load this tab:', 'sepo-forms'),
                'search'        => __('Search submissions…', 'sepo-forms'),
                'noMatch'       => __('No submissions match your search.', 'sepo-forms'),
                'showing'       => __('Showing %1$d–%2$d of %3$d', 'sepo-forms'),
                'none'          => __('No entries', 'sepo-forms'),
                'prev'          => __('Previous', 'sepo-forms'),
                'nextPage'      => __('Next', 'sepo-forms'),
                'exportCsv'     => __('Export CSV', 'sepo-forms'),
                'exportShown'   => __('Download what is shown', 'sepo-forms'),
                'exportAll'     => __('Download everything', 'sepo-forms'),
                'columns'       => __('Columns', 'sepo-forms'),
                'view'          => __('View', 'sepo-forms'),
                'remove'        => __('Remove', 'sepo-forms'),
                'actions'       => __('Actions', 'sepo-forms'),
                'removeTitle'   => __('Remove submission', 'sepo-forms'),
                'removeBody'    => __('Are you sure you want to remove this submission? This action cannot be undone.', 'sepo-forms'),
                'cancel'        => __('Cancel', 'sepo-forms'),
                'close'         => __('Close', 'sepo-forms'),
                'response'      => __('Response', 'sepo-forms'),
                'removed'       => __('Submission removed.', 'sepo-forms'),
                'removeFail'    => __('Failed to remove the submission.', 'sepo-forms'),
                'building'      => __('Building…', 'sepo-forms'),
                'buildAnother'  => __('Build another', 'sepo-forms'),
                'createGoogle'  => __('Create on Google Forms', 'sepo-forms'),
                'googleOk'      => __('Form created.', 'sepo-forms'),
                'googleEdit'    => __('Open in the Google Forms editor', 'sepo-forms'),
                'googleLive'    => __('Open the live form', 'sepo-forms'),
                'googleSkipped' => __('Skipped:', 'sepo-forms'),
                'googleRefused' => __('Google refused the request.', 'sepo-forms'),
                'open'          => __('Open', 'sepo-forms'),
                'closed'        => __('Closed', 'sepo-forms'),
                'private'       => __('Private', 'sepo-forms'),
                'signIn'        => __('Sign-in', 'sepo-forms'),
                'openForm'      => __('Open form', 'sepo-forms'),
                'noForms'       => __('No matching forms.', 'sepo-forms'),
                'questionsN'    => __('%d questions', 'sepo-forms'),
                'files'         => __('Files', 'sepo-forms'),
            ],
        ];
    }

    // ------------------------------------------------------------ rendering

    /**
     * A form ready to fill in, with the gates applied: private forms are for
     * their managers only, and sign-in forms ask visitors to log in.
     *
     * @param array{showTitle?:bool} $opts
     */
    public static function renderForm(int $formId, array $opts = []): string
    {
        $post = get_post($formId);
        if (!$post || $post->post_type !== PostType::TYPE || ($post->post_status !== 'publish' && !PostType::canManage($formId))) {
            return '';
        }
        self::enqueue();
        $form = FormDefinition::load($formId);
        $s = $form['settings'];
        $manager = PostType::canManage($formId);

        if ($s['visibility'] === 'private' && !$manager) {
            return '<div class="sf-scope">' . Gate::private() . '</div>';
        }
        if ($s['requireLogin'] && !is_user_logged_in()) {
            return '<div class="sf-scope">' . Gate::login() . '</div>';
        }
        $already = !$s['allowMultipleSubmissions'] && is_user_logged_in() && Submissions::hasUserSubmitted($formId, get_current_user_id());

        try {
            $html = Manager::shell($form, ['showTitle' => $opts['showTitle'] ?? true, 'alreadySubmitted' => $already]);
        } catch (\Throwable $e) {
            error_log('[sepo-forms] render: ' . $e->getMessage());
            $html = '<div class="sf-error">' . esc_html__('This form could not be shown. Please try again later.', 'sepo-forms') . '</div>';
        }
        return '<div class="sf-scope">' . $html . '</div>';
    }

    /** The form on its own page, in place of the (empty) post content. */
    public static function content(string $content): string
    {
        if (!is_singular(PostType::TYPE) || !in_the_loop() || !is_main_query() || get_post_type() !== PostType::TYPE) {
            return $content;
        }
        static $done = [];
        $id = (int) get_the_ID();
        if (isset($done[$id])) {
            return $content;
        }
        $done[$id] = true;
        // The theme already prints the title above the content.
        $showTitle = (bool) apply_filters('sepo_forms_show_title_on_form_page', false, $id);
        return self::renderForm($id, ['showTitle' => $showTitle]);
    }

    /** [sepo_form id="123"] or [sepo_form slug="course-feedback"] */
    public static function formShortcode($atts): string
    {
        $a = shortcode_atts(['id' => '', 'slug' => '', 'title' => 'yes'], (array) $atts, 'sepo_form');
        $id = absint($a['id']);
        if ($id === 0 && $a['slug'] !== '') {
            $p = get_page_by_path(sanitize_title((string) $a['slug']), OBJECT, PostType::TYPE);
            $id = $p ? (int) $p->ID : 0;
        }
        if ($id === 0) {
            return current_user_can('edit_posts') ? '<p class="sf-error">' . esc_html__('Choose a form: [sepo_form id="…"]', 'sepo-forms') . '</p>' : '';
        }
        return self::renderForm($id, ['showTitle' => !in_array(strtolower((string) $a['title']), ['no', '0', 'false'], true)]);
    }

    /** [sepo_forms per_page="12" view="grid|list"] */
    public static function indexShortcode($atts): string
    {
        $a = shortcode_atts(['per_page' => Index::PER_PAGE, 'view' => 'grid'], (array) $atts, 'sepo_forms');
        self::enqueue();
        return '<div class="sf-scope">' . Index::render(['perPage' => (int) $a['per_page'], 'view' => (string) $a['view']]) . '</div>';
    }
}
