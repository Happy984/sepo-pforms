<?php

declare(strict_types=1);

namespace SepoForms\Admin;

use SepoForms\FormDefinition;
use SepoForms\Files;
use SepoForms\Plugin;
use SepoForms\PostType;
use SepoForms\Rest;
use SepoForms\Submissions;
use SepoForms\Templates;
use WP_Post;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * The form editing screen: the question builder, the form's settings, its
 * file upload slots, and a box with the shortcode and response links.
 */
final class Builder
{
    private const NONCE = 'spf_builder';

    public static function hook(): void
    {
        add_action('add_meta_boxes_' . PostType::TYPE, [self::class, 'boxes']);
        add_action('save_post_' . PostType::TYPE, [self::class, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_filter('enter_title_here', static fn($t, $post) => $post->post_type === PostType::TYPE ? __('Form title', 'sepo-forms') : $t, 10, 2);
        add_action('rest_api_init', static function (): void {
            register_rest_route(Rest::NS, '/parse', [
                'methods'             => 'POST',
                'callback'            => static fn(WP_REST_Request $r) => new \WP_REST_Response(['status' => 'success', 'data' => FormDefinition::fromText((string) $r->get_param('text'))]),
                'permission_callback' => static fn() => current_user_can('edit_posts'),
            ]);
        });
    }

    public static function assets(string $hook): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== PostType::TYPE) {
            return;
        }
        wp_enqueue_style('sepo-forms-admin', SPF_URL . 'assets/css/admin.css', [], Plugin::assetVersion('assets/css/admin.css'));
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('sepo-forms-builder', SPF_URL . 'assets/js/builder.js', [], Plugin::assetVersion('assets/js/builder.js'), true);
        $post = get_post();
        $form = $post ? FormDefinition::load((int) $post->ID) : null;
        wp_add_inline_script('sepo-forms-builder', 'window.SepoFormsBuilder=' . wp_json_encode([
            'sections'  => $form ? $form['sections'] : [],
            'types'     => FormDefinition::typeLabels(),
            'templates' => ($form && $form['questions'] === []) ? Templates::forBuilder() : [],
            'api'       => esc_url_raw(rest_url(Rest::NS)),
            'nonce'     => wp_create_nonce('wp_rest'),
            'text'      => $form && $form['questions'] ? FormDefinition::toText($form) : '',
            'i18n'      => [
                'section'       => __('Section', 'sepo-forms'),
                'sectionTitle'  => __('Section title', 'sepo-forms'),
                'sectionHelp'   => __('Instruction for this section (optional)', 'sepo-forms'),
                'firstHint'     => __('Leave empty to use the first step title from the settings.', 'sepo-forms'),
                'addQuestion'   => __('Add question', 'sepo-forms'),
                'addSection'    => __('Add section', 'sepo-forms'),
                'question'      => __('Question', 'sepo-forms'),
                'questionPh'    => __('Type the question', 'sepo-forms'),
                'type'          => __('Type', 'sepo-forms'),
                'required'      => __('Required', 'sepo-forms'),
                'options'       => __('Choices, one per line', 'sepo-forms'),
                'help'          => __('Help text', 'sepo-forms'),
                'placeholder'   => __('Placeholder', 'sepo-forms'),
                'image'         => __('Image', 'sepo-forms'),
                'chooseImage'   => __('Choose image', 'sepo-forms'),
                'removeImage'   => __('Remove image', 'sepo-forms'),
                'more'          => __('More options', 'sepo-forms'),
                'moveUp'        => __('Move up', 'sepo-forms'),
                'moveDown'      => __('Move down', 'sepo-forms'),
                'duplicate'     => __('Duplicate', 'sepo-forms'),
                'delete'        => __('Delete', 'sepo-forms'),
                'deleteSection' => __('Delete section', 'sepo-forms'),
                'confirmDelete' => __('Delete this section and its questions?', 'sepo-forms'),
                'empty'         => __('No questions yet. Add one, start from a template, or import a form written as text.', 'sepo-forms'),
                'templates'     => __('Start from a template', 'sepo-forms'),
                'import'        => __('Import from text', 'sepo-forms'),
                'copyText'      => __('Copy as text', 'sepo-forms'),
                'copied'        => __('Copied', 'sepo-forms'),
                'importTitle'   => __('Import a form written as text', 'sepo-forms'),
                'importHelp'    => __('Paste lines such as "question1: Your name" and "questionType1: email". Front matter at the top (title, requireLogin, …) is read too. The questions replace the ones in the builder.', 'sepo-forms'),
                'importGo'      => __('Import', 'sepo-forms'),
                'cancel'        => __('Cancel', 'sepo-forms'),
                'use'           => __('Use this template', 'sepo-forms'),
                'blank'         => __('Blank form', 'sepo-forms'),
                'importedNote'  => __('Imported. Review the questions, then update the form to save.', 'sepo-forms'),
                'nothing'       => __('No questions were found in that text.', 'sepo-forms'),
                'answersKept'   => __('Answers are stored by question, so editing a question keeps its responses. Deleting it hides them.', 'sepo-forms'),
                'qCount'        => __('%d questions', 'sepo-forms'),
            ],
        ]) . ';', 'before');
    }

    public static function boxes(WP_Post $post): void
    {
        remove_meta_box('postexcerpt', PostType::TYPE, 'normal');
        add_meta_box('spf_builder', __('Questions', 'sepo-forms'), [self::class, 'builderBox'], PostType::TYPE, 'normal', 'high');
        add_meta_box('spf_uploads', __('File uploads', 'sepo-forms'), [self::class, 'uploadsBox'], PostType::TYPE, 'normal', 'default');
        add_meta_box('spf_settings', __('Form settings', 'sepo-forms'), [self::class, 'settingsBox'], PostType::TYPE, 'side', 'default');
        add_meta_box('spf_use', __('Use this form', 'sepo-forms'), [self::class, 'useBox'], PostType::TYPE, 'side', 'high');
    }

    public static function builderBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE, '_spf_nonce');
        $form = FormDefinition::load((int) $post->ID);
        echo '<div class="spf-desc"><label for="spf-excerpt"><strong>' . esc_html__('Description', 'sepo-forms') . '</strong></label>'
            . '<textarea id="spf-excerpt" name="excerpt" rows="2" placeholder="' . esc_attr__('Shown under the title at the top of the form.', 'sepo-forms') . '">' . esc_textarea((string) $post->post_excerpt) . '</textarea></div>';
        echo '<input type="hidden" name="spf_sections" id="spf-sections" value="' . esc_attr((string) wp_json_encode($form['sections'])) . '">';
        echo '<div id="spf-builder" class="spf-builder"><p class="description">' . esc_html__('Loading the builder…', 'sepo-forms') . '</p></div>';
    }

    public static function uploadsBox(WP_Post $post): void
    {
        $s = FormDefinition::load((int) $post->ID)['settings'];
        $lines = implode("\n", array_map(static fn($t) => $t['label'] . ($t['category'] !== '' ? ' | ' . $t['category'] : ''), (array) $s['uploadTopics']));
        ?>
        <div class="spf-uploads">
          <label class="spf-check"><input type="checkbox" name="spf_settings[upload]" value="1" <?php checked($s['upload']); ?> data-spf-toggle="spf-upload-fields"> <?php esc_html_e('Let people attach files to their response', 'sepo-forms'); ?></label>
          <div id="spf-upload-fields" class="spf-grid2" <?php echo $s['upload'] ? '' : 'hidden'; ?>>
            <p><label><?php esc_html_e('Heading', 'sepo-forms'); ?><input type="text" class="widefat" name="spf_settings[uploadTitle]" value="<?php echo esc_attr((string) $s['uploadTitle']); ?>" placeholder="<?php esc_attr_e('Attach files', 'sepo-forms'); ?>"></label></p>
            <p><label><?php esc_html_e('Largest file (MB)', 'sepo-forms'); ?><input type="number" min="1" max="<?php echo (int) Files::maxUploadMb(); ?>" class="small-text" name="spf_settings[uploadMaxSizeMB]" value="<?php echo (int) $s['uploadMaxSizeMB']; ?>"></label>
              <span class="description"><?php echo esc_html(sprintf(/* translators: %d: MB */ __('This server accepts up to %d MB.', 'sepo-forms'), Files::maxUploadMb())); ?></span></p>
            <p class="spf-span2"><label><?php esc_html_e('Instructions', 'sepo-forms'); ?><textarea class="widefat" rows="2" name="spf_settings[uploadDescription]"><?php echo esc_textarea((string) $s['uploadDescription']); ?></textarea></label></p>
            <p class="spf-span2"><label><?php esc_html_e('Upload slots, one per line', 'sepo-forms'); ?><textarea class="widefat code" rows="4" name="spf_topics" placeholder="<?php esc_attr_e("CV | Required\nCover letter", 'sepo-forms'); ?>"><?php echo esc_textarea($lines); ?></textarea></label>
              <span class="description"><?php esc_html_e('Each line is one file people can attach: a label, then optionally | and a short tag shown beside it. Files are stored privately; only people who can edit this form can download them.', 'sepo-forms'); ?></span></p>
          </div>
        </div>
        <?php
    }

    public static function settingsBox(WP_Post $post): void
    {
        $s = FormDefinition::load((int) $post->ID)['settings'];
        $check = static function (string $key, string $label, string $help = '') use ($s): void {
            echo '<label class="spf-check"><input type="checkbox" name="spf_settings[' . esc_attr($key) . ']" value="1" ' . checked((bool) $s[$key], true, false) . '> ' . esc_html($label) . '</label>';
            if ($help !== '') {
                echo '<span class="spf-help">' . esc_html($help) . '</span>';
            }
        };
        echo '<input type="hidden" name="spf_settings[_present]" value="1">';
        echo '<div class="spf-settings">';
        $check('closed', __('Closed — stop taking responses', 'sepo-forms'));
        $check('requireLogin', __('Only signed-in people can answer', 'sepo-forms'));
        $check('allowMultipleSubmissions', __('Allow more than one response per person', 'sepo-forms'), __('Applies to signed-in people.', 'sepo-forms'));
        $check('collectEmail', __('Ask guests for their email', 'sepo-forms'));
        echo '<label class="spf-check"><input type="checkbox" name="spf_settings[visibility]" value="private" ' . checked($s['visibility'], 'private', false) . '> ' . esc_html__('Private — only editors can open it', 'sepo-forms') . '</label>';
        echo '<hr>';
        $text = static function (string $key, string $label, string $type = 'text') use ($s): void {
            echo '<p><label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" class="widefat" name="spf_settings[' . esc_attr($key) . ']" value="' . esc_attr((string) $s[$key]) . '"></label></p>';
        };
        $text('submitButtonText', __('Submit button', 'sepo-forms'));
        echo '<p><label>' . esc_html__('Thank-you message', 'sepo-forms') . '<textarea class="widefat" rows="3" name="spf_settings[successMessage]">' . esc_textarea((string) $s['successMessage']) . '</textarea></label></p>';
        $text('successRedirect', __('Or send people to this address', 'sepo-forms'), 'url');
        $text('firstGroupTitle', __('First step title', 'sepo-forms'));
        echo '<p><label>' . esc_html__('Order in the forms list', 'sepo-forms') . '<input type="number" class="small-text" name="spf_settings[order]" value="' . (int) $s['order'] . '"></label><span class="spf-help">' . esc_html__('Higher numbers come first.', 'sepo-forms') . '</span></p>';
        echo '</div>';
    }

    public static function useBox(WP_Post $post): void
    {
        $id = (int) $post->ID;
        $n = Submissions::count($id);
        $code = '[sepo_form id="' . $id . '"]';
        echo '<div class="spf-use">';
        echo '<p class="spf-use-count"><strong>' . esc_html(number_format_i18n($n)) . '</strong> ' . esc_html(_n('response', 'responses', $n, 'sepo-forms')) . '</p>';
        if ($post->post_status !== 'auto-draft') {
            echo '<p><a class="button" href="' . esc_url(add_query_arg(['post_type' => PostType::TYPE, 'page' => SubmissionsPage::SLUG, 'form' => $id], admin_url('edit.php'))) . '">' . esc_html__('View responses', 'sepo-forms') . '</a> ';
            if ($n > 0) {
                echo '<a class="button" href="' . esc_url(Submissions::exportUrl($id)) . '">' . esc_html__('Export CSV', 'sepo-forms') . '</a>';
            }
            echo '</p>';
        }
        echo '<p><label for="spf-code">' . esc_html__('Put it in any page, post or Elementor shortcode widget:', 'sepo-forms') . '</label>'
            . '<span class="spf-code"><input id="spf-code" type="text" readonly value="' . esc_attr($code) . '" onfocus="this.select()"><button type="button" class="button" data-spf-copy="' . esc_attr($code) . '">' . esc_html__('Copy', 'sepo-forms') . '</button></span></p>';
        echo '<p class="spf-help">' . esc_html__('In the block editor, add the "Sepo form" block. In Elementor, use the "Sepo form" widget.', 'sepo-forms') . '</p>';
        echo '</div>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if (!isset($_POST['_spf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_spf_nonce'])), self::NONCE)) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || !current_user_can('edit_post', $postId)) {
            return;
        }
        $sections = json_decode(wp_unslash((string) ($_POST['spf_sections'] ?? '')), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned by FormDefinition
        $raw = isset($_POST['spf_settings']) && is_array($_POST['spf_settings']) ? wp_unslash($_POST['spf_settings']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned by FormDefinition
        // Unticked boxes are absent from the post.
        foreach (['closed', 'requireLogin', 'allowMultipleSubmissions', 'collectEmail', 'upload'] as $k) {
            $raw[$k] = !empty($raw[$k]);
        }
        $raw['visibility'] = ($raw['visibility'] ?? '') === 'private' ? 'private' : 'public';
        $topics = [];
        foreach (preg_split('/\R/', (string) wp_unslash($_POST['spf_topics'] ?? '')) ?: [] as $line) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned by FormDefinition
            $parts = array_map('trim', explode('|', $line, 2));
            if (($parts[0] ?? '') !== '') {
                $topics[] = ['id' => sanitize_key(remove_accents($parts[0])), 'label' => $parts[0], 'category' => $parts[1] ?? ''];
            }
        }
        $raw['uploadTopics'] = $topics;
        FormDefinition::save($postId, is_array($sections) ? $sections : [], $raw);
    }
}
