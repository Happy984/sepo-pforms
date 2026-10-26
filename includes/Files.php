<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * Files people attach to a response.
 *
 * A form with uploads switched on lists upload slots ("topics"), each with a
 * label and an optional category. Files go into a private folder under
 * uploads, with random names, and are recorded on the response. Only people
 * who can edit the form can download them, through admin-post.php.
 */
final class Files
{
    public const DIR = 'sepo-forms-private';

    public static function baseDir(): string
    {
        $u = wp_upload_dir(null, false);
        return trailingslashit($u['basedir']) . self::DIR;
    }

    /** Make the private folder and stop the web server listing or serving it. */
    public static function protectDir(): void
    {
        $dir = self::baseDir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!is_file($dir . '/index.php')) {
            @file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
        if (!is_file($dir . '/web.config')) {
            @file_put_contents($dir . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
        }
    }

    /** The largest upload the server accepts, in whole megabytes. */
    public static function maxUploadMb(): int
    {
        return max(1, (int) floor(wp_max_upload_size() / 1048576));
    }

    /**
     * Check and store the files sent with a response.
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $files $_FILES['spf_files'] in PHP's nested shape.
     * @return list<array<string,mixed>>
     */
    public static function store(int $formId, array $settings, array $files): array
    {
        if (empty($settings['upload']) || empty($files['name']) || !is_array($files['name'])) {
            return [];
        }
        $topics = [];
        foreach ((array) $settings['uploadTopics'] as $t) {
            $topics[$t['id']] = $t;
        }
        $max = (int) $settings['uploadMaxSizeMB'] * 1048576;
        $allowed = (array) apply_filters('sepo_forms_upload_mimes', get_allowed_mime_types());

        $stored = [];
        $dir = self::baseDir() . '/' . $formId;
        foreach ($files['name'] as $topicId => $name) {
            $topicId = sanitize_key((string) $topicId);
            if (!isset($topics[$topicId]) || (string) $name === '') {
                continue;
            }
            $err = (int) ($files['error'][$topicId] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $label = $topics[$topicId]['label'];
            if ($err !== UPLOAD_ERR_OK) {
                /* translators: %s: upload slot */
                throw new \RuntimeException(sprintf(__('The file for "%s" did not upload. Please try again.', 'sepo-forms'), $label));
            }
            $tmp = (string) $files['tmp_name'][$topicId];
            $size = (int) $files['size'][$topicId];
            if ($size > $max) {
                /* translators: 1: upload slot, 2: size in MB */
                throw new \RuntimeException(sprintf(__('The file for "%1$s" is larger than %2$d MB.', 'sepo-forms'), $label, (int) $settings['uploadMaxSizeMB']));
            }
            if (!is_uploaded_file($tmp) && !apply_filters('sepo_forms_allow_local_file', false, $tmp)) {
                throw new \RuntimeException(__('A file could not be read.', 'sepo-forms'));
            }
            $check = wp_check_filetype_and_ext($tmp, (string) $name, $allowed);
            if (empty($check['ext']) || empty($check['type'])) {
                /* translators: %s: upload slot */
                throw new \RuntimeException(sprintf(__('The file for "%s" is not a type this site accepts.', 'sepo-forms'), $label));
            }
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            $file = bin2hex(random_bytes(12)) . '.' . $check['ext'];
            if (!@move_uploaded_file($tmp, $dir . '/' . $file) && !@rename($tmp, $dir . '/' . $file)) {
                throw new \RuntimeException(__('A file could not be saved. Please try again.', 'sepo-forms'));
            }
            @chmod($dir . '/' . $file, 0640);
            $stored[] = [
                'topic'    => $topicId,
                'label'    => $label,
                'category' => $topics[$topicId]['category'],
                'name'     => sanitize_file_name((string) $name),
                'file'     => $file,
                'size'     => $size,
                'type'     => (string) $check['type'],
            ];
        }
        return $stored;
    }

    /**
     * The files on a response, with download links for managers.
     *
     * @param array<string,mixed> $record
     * @return list<array{label:string,name:string,size:string,url:string}>
     */
    public static function listFor(array $record, int $formId): array
    {
        $out = [];
        foreach ((array) ($record['meta']['files'] ?? []) as $i => $f) {
            $out[] = [
                'label' => (string) ($f['label'] ?? ''),
                'name'  => (string) ($f['name'] ?? ''),
                'size'  => size_format((int) ($f['size'] ?? 0)),
                'url'   => add_query_arg([
                    'action'   => 'sepo_forms_file',
                    'form'     => $formId,
                    'response' => $record['id'],
                    'file'     => $i,
                    '_wpnonce' => wp_create_nonce('sepo_forms_file_' . $formId),
                ], admin_url('admin-post.php')),
            ];
        }
        return $out;
    }

    public static function downloadRequest(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification -- checked below
        $formId = isset($_GET['form']) ? absint($_GET['form']) : 0;
        $id = isset($_GET['response']) ? sanitize_text_field(wp_unslash((string) $_GET['response'])) : '';
        $i = isset($_GET['file']) ? absint($_GET['file']) : -1;
        // phpcs:enable
        if (!PostType::canManage($formId) || !check_admin_referer('sepo_forms_file_' . $formId)) {
            wp_die(esc_html__('You cannot download this file.', 'sepo-forms'), '', ['response' => 403]);
        }
        $row = Submissions::get($formId, $id);
        $f = $row['meta']['files'][$i] ?? null;
        $path = is_array($f) ? self::baseDir() . '/' . $formId . '/' . basename((string) $f['file']) : '';
        if ($path === '' || !is_file($path)) {
            wp_die(esc_html__('The file is no longer available.', 'sepo-forms'), '', ['response' => 404]);
        }
        nocache_headers();
        header('Content-Type: ' . (preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', (string) $f['type']) ? $f['type'] : 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $f['name']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** @param array<string,mixed> $record */
    public static function deleteFor(array $record): void
    {
        foreach ((array) ($record['meta']['files'] ?? []) as $f) {
            $path = self::baseDir() . '/' . (int) $record['form_id'] . '/' . basename((string) ($f['file'] ?? ''));
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
