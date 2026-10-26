<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * Form responses, one row each in {prefix}sepo_form_submissions.
 *
 * A record, as the rest of the plugin sees it:
 *   id (public, "fsub_…"), form_id, user_id (0 for guests), email,
 *   answers (question id => answer), meta (files and extras), ip,
 *   user_agent, submitted_at (Unix time).
 *
 * Reads for one form are memoised for the request and cleared by every
 * write to that form.
 */
final class Submissions
{
    /** @var array<int,list<array<string,mixed>>> */
    private static array $cache = [];

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sepo_form_submissions';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::table();
        dbDelta("CREATE TABLE {$t} (
  row_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  id varchar(40) NOT NULL,
  form_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  email varchar(191) NOT NULL DEFAULT '',
  answers longtext NULL,
  meta longtext NULL,
  ip varchar(64) NOT NULL DEFAULT '',
  user_agent varchar(255) NOT NULL DEFAULT '',
  submitted_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (row_id),
  UNIQUE KEY id (id),
  KEY form_id (form_id),
  KEY user_id (user_id),
  KEY submitted_at (submitted_at)
) " . $wpdb->get_charset_collate() . ';');
    }

    // ------------------------------------------------------------ writes

    /**
     * @param array<int,mixed> $answers
     * @param array{email?:string,meta?:array<string,mixed>,ip?:string,user_agent?:string} $extra
     * @return array<string,mixed>
     */
    public static function add(int $formId, array $answers, int $userId, array $extra = []): array
    {
        global $wpdb;
        $record = [
            'id'           => 'fsub_' . time() . '_' . bin2hex(random_bytes(4)),
            'form_id'      => $formId,
            'user_id'      => $userId,
            'email'        => sanitize_email((string) ($extra['email'] ?? '')),
            'answers'      => $answers,
            'meta'         => (array) ($extra['meta'] ?? []),
            'ip'           => (string) ($extra['ip'] ?? ''),
            'user_agent'   => mb_substr((string) ($extra['user_agent'] ?? ''), 0, 255),
            'submitted_at' => time(),
        ];
        $ok = $wpdb->insert(self::table(), [ // phpcs:ignore WordPress.DB
            'id'           => $record['id'],
            'form_id'      => $formId,
            'user_id'      => $userId,
            'email'        => $record['email'],
            'answers'      => wp_json_encode((object) $answers),
            'meta'         => wp_json_encode((object) $record['meta']),
            'ip'           => $record['ip'],
            'user_agent'   => $record['user_agent'],
            'submitted_at' => $record['submitted_at'],
        ]);
        if ($ok === false) {
            throw new \RuntimeException(__('Your response could not be saved. Please try again.', 'sepo-forms'));
        }
        unset(self::$cache[$formId]);
        /**
         * Fires after a response is saved.
         *
         * @param array<string,mixed> $record
         */
        do_action('sepo_forms_submitted', $record, $formId);
        return $record;
    }

    public static function delete(int $formId, string $id): bool
    {
        global $wpdb;
        $row = self::get($formId, $id);
        if ($row === null) {
            return false;
        }
        Files::deleteFor($row);
        $wpdb->delete(self::table(), ['form_id' => $formId, 'id' => $id]); // phpcs:ignore WordPress.DB
        unset(self::$cache[$formId]);
        do_action('sepo_forms_submission_deleted', $id, $formId);
        return true;
    }

    public static function deleteForm(int $formId): int
    {
        global $wpdb;
        foreach (self::forForm($formId) as $row) {
            Files::deleteFor($row);
        }
        $n = (int) $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() . ' WHERE form_id = %d', $formId)); // phpcs:ignore WordPress.DB
        unset(self::$cache[$formId]);
        return $n;
    }

    // ------------------------------------------------------------ reads

    /** Every response to one form, newest first. @return list<array<string,mixed>> */
    public static function forForm(int $formId): array
    {
        if (isset(self::$cache[$formId])) {
            return self::$cache[$formId];
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE form_id = %d ORDER BY submitted_at DESC, row_id DESC', $formId), ARRAY_A); // phpcs:ignore WordPress.DB
        return self::$cache[$formId] = array_map([self::class, 'hydrate'], (array) $rows);
    }

    /** @return array<string,mixed>|null */
    public static function get(int $formId, string $id): ?array
    {
        foreach (self::forForm($formId) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    public static function count(int $formId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE form_id = %d', $formId)); // phpcs:ignore WordPress.DB
    }

    public static function hasUserSubmitted(int $formId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . self::table() . ' WHERE form_id = %d AND user_id = %d LIMIT 1', $formId, $userId)); // phpcs:ignore WordPress.DB
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function hydrate(array $row): array
    {
        $answers = json_decode((string) ($row['answers'] ?? ''), true);
        $meta = json_decode((string) ($row['meta'] ?? ''), true);
        return [
            'id'           => (string) $row['id'],
            'form_id'      => (int) $row['form_id'],
            'user_id'      => (int) $row['user_id'],
            'email'        => (string) $row['email'],
            'answers'      => is_array($answers) ? $answers : [],
            'meta'         => is_array($meta) ? $meta : [],
            'ip'           => (string) $row['ip'],
            'user_agent'   => (string) $row['user_agent'],
            'submitted_at' => (int) $row['submitted_at'],
        ];
    }

    // ------------------------------------------------------------ figures

    /**
     * @param list<array<string,mixed>> $subs
     * @return array{total_submissions:int,unique_users:int,guest_submissions:int,last_submission:int}
     */
    public static function stats(array $subs): array
    {
        $users = [];
        $guests = 0;
        $last = 0;
        foreach ($subs as $s) {
            if ($s['user_id'] > 0) {
                $users[$s['user_id']] = true;
            } else {
                $guests++;
            }
            $last = max($last, (int) $s['submitted_at']);
        }
        return ['total_submissions' => count($subs), 'unique_users' => count($users), 'guest_submissions' => $guests, 'last_submission' => $last];
    }

    /** Who answered: the user's display name, else the email given, else "Guest". */
    public static function respondent(array $s): string
    {
        if ($s['user_id'] > 0) {
            $u = get_userdata((int) $s['user_id']);
            if ($u) {
                return $u->display_name . ($s['email'] !== '' && $s['email'] !== $u->user_email ? ' (' . $s['email'] . ')' : '');
            }
        }
        return $s['email'] !== '' ? $s['email'] : __('Guest', 'sepo-forms');
    }

    /**
     * Rows for the submissions table: one per response, one column per question.
     *
     * @param list<array<string,mixed>> $subs
     * @param list<array<string,mixed>> $questions
     * @return list<array<string,string>>
     */
    public static function tableRows(array $subs, array $questions, int $formId): array
    {
        $rows = [];
        foreach ($subs as $s) {
            $row = [
                'id'           => $s['id'],
                'submitted_at' => wp_date('Y-m-d H:i', (int) $s['submitted_at']),
                '_ts'          => (string) $s['submitted_at'],
                'respondent'   => self::respondent($s),
            ];
            foreach ($questions as $q) {
                $row['q_' . $q['id']] = FormDefinition::answerToText($q, $s['answers'][$q['id']] ?? ($s['answers'][(string) $q['id']] ?? ''));
            }
            $files = Files::listFor($s, $formId);
            if ($files !== []) {
                $row['_files'] = wp_json_encode($files) ?: '[]';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    // ------------------------------------------------------------ CSV

    public static function exportRequest(): void
    {
        $formId = isset($_GET['form']) ? absint($_GET['form']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        if (!PostType::canManage($formId) || !check_admin_referer('sepo_forms_export_' . $formId)) {
            wp_die(esc_html__('You cannot export these responses.', 'sepo-forms'), '', ['response' => 403]);
        }
        $form = FormDefinition::load($formId);
        $subs = self::forForm($formId);
        $name = sanitize_file_name(get_post_field('post_name', $formId) ?: 'form') . '-responses-' . gmdate('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $head = [__('Response ID', 'sepo-forms'), __('Submitted', 'sepo-forms'), __('Respondent', 'sepo-forms'), __('User ID', 'sepo-forms'), __('Email', 'sepo-forms')];
        foreach ($form['questions'] as $q) {
            $head[] = $q['question'];
        }
        $head[] = __('Files', 'sepo-forms');
        fputcsv($out, $head);
        foreach ($subs as $s) {
            $line = [$s['id'], wp_date('Y-m-d H:i:s', $s['submitted_at']), self::respondent($s), $s['user_id'] ?: '', $s['email']];
            foreach ($form['questions'] as $q) {
                $line[] = FormDefinition::answerToText($q, $s['answers'][$q['id']] ?? ($s['answers'][(string) $q['id']] ?? ''));
            }
            $line[] = implode(' | ', array_map(static fn($f) => $f['label'] . ': ' . $f['name'], Files::listFor($s, $formId)));
            // A leading = + - @ would run as a formula in spreadsheet apps.
            fputcsv($out, array_map(static fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $line));
        }
        fclose($out);
        exit;
    }

    public static function exportUrl(int $formId): string
    {
        return add_query_arg(['action' => 'sepo_forms_export', 'form' => $formId, '_wpnonce' => wp_create_nonce('sepo_forms_export_' . $formId)], admin_url('admin-post.php'));
    }
}
