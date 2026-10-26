<?php

declare(strict_types=1);

namespace SepoForms\Google;

defined('ABSPATH') || exit;

/**
 * Builds a Google Form from one of this site's forms.
 *
 * The Forms API takes two calls: forms.create (a title only), then
 * forms.batchUpdate with every item in order. Sections become page breaks,
 * placed before the first question of each section except the first;
 * a section's instruction becomes the page break's description. Question
 * types the API cannot create are reported back as skipped. If the second
 * call fails, the half-made form is deleted from Drive.
 *
 * Failures come back as a structured result, never an exception.
 */
final class Client
{
    private const API = 'https://forms.googleapis.com/v1';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const TIMEOUT = 15;

    public const SCOPE_FORMS = 'https://www.googleapis.com/auth/forms.body';
    public const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive.file';

    public function __construct(private string $clientId, private string $clientSecret, private string $refreshToken)
    {
    }

    /**
     * @param array{title:string,description:string,questions:list<array<string,mixed>>} $definition
     * @return array{ok:bool,form_id:string,responder_uri:string,edit_uri:string,skipped:list<string>,error:string}
     */
    public function build(array $definition): array
    {
        $result = ['ok' => false, 'form_id' => '', 'responder_uri' => '', 'edit_uri' => '', 'skipped' => [], 'error' => ''];
        $token = $this->accessToken();
        if ($token === '') {
            $result['error'] = __('Google refused the stored credentials. Reconnect the integration under Forms → Settings.', 'sepo-forms');
            return $result;
        }
        $title = trim($definition['title']) !== '' ? trim($definition['title']) : __('Untitled form', 'sepo-forms');

        $create = $this->call('POST', self::API . '/forms', $token, ['info' => ['title' => $title, 'documentTitle' => $title]]);
        if (!$create['ok']) {
            $result['error'] = $create['error'];
            return $result;
        }
        $formId = (string) ($create['body']['formId'] ?? '');
        if ($formId === '') {
            $result['error'] = __('Google returned no form ID for the new form.', 'sepo-forms');
            return $result;
        }
        $result['form_id'] = $formId;
        $result['responder_uri'] = (string) ($create['body']['responderUri'] ?? '');

        $requests = [];
        if (trim($definition['description']) !== '') {
            $requests[] = ['updateFormInfo' => ['info' => ['description' => trim($definition['description'])], 'updateMask' => 'description']];
        }
        $index = 0;
        $last = null;
        foreach ($definition['questions'] as $q) {
            $group = (int) ($q['group'] ?? 0);
            if ($last !== null && $group !== $last) {
                $sectionTitle = trim((string) ($q['_groupTitle'] ?? ''));
                /* translators: %d: section number */
                $page = ['title' => $sectionTitle !== '' ? $sectionTitle : sprintf(__('Section %d', 'sepo-forms'), $group + 1), 'pageBreakItem' => new \stdClass()];
                if (trim((string) ($q['_groupHelp'] ?? '')) !== '') {
                    $page['description'] = trim((string) $q['_groupHelp']);
                }
                $requests[] = ['createItem' => ['item' => $page, 'location' => ['index' => $index++]]];
            }
            $last = $group;
            $item = $this->translate($q);
            if ($item === null) {
                /* translators: %s: question */
                $result['skipped'][] = sprintf(__('%s (type not supported by the API)', 'sepo-forms'), (string) ($q['question'] ?? ''));
                continue;
            }
            $requests[] = ['createItem' => ['item' => $item, 'location' => ['index' => $index++]]];
        }
        if ($requests !== []) {
            $update = $this->call('POST', self::API . '/forms/' . rawurlencode($formId) . ':batchUpdate', $token, ['requests' => $requests]);
            if (!$update['ok']) {
                $this->deleteFile($formId, $token);
                $result['error'] = $update['error'];
                $result['form_id'] = '';
                return $result;
            }
            if (!empty($update['body']['form']['responderUri'])) {
                $result['responder_uri'] = (string) $update['body']['form']['responderUri'];
            }
        }
        $result['edit_uri'] = 'https://docs.google.com/forms/d/' . rawurlencode($formId) . '/edit';
        $result['ok'] = true;
        return $result;
    }

    /** @return array{ok:bool,status:int,body:array<string,mixed>,error:string} */
    private function call(string $method, string $url, string $token, ?array $payload): array
    {
        $args = [
            'method'  => $method,
            'timeout' => self::TIMEOUT,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
            'redirection' => 0,
        ];
        if ($payload !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            /* translators: %s: error */
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => sprintf(__('Could not reach Google. %s', 'sepo-forms'), $res->get_error_message())];
        }
        $status = (int) wp_remote_retrieve_response_code($res);
        $body = json_decode((string) wp_remote_retrieve_body($res), true);
        $body = is_array($body) ? $body : [];
        if ($status < 200 || $status >= 300) {
            $msg = (string) ($body['error']['message'] ?? '');
            /* translators: %d: HTTP status */
            return ['ok' => false, 'status' => $status, 'body' => $body, 'error' => $msg !== '' ? $msg : sprintf(__('Google returned HTTP %d.', 'sepo-forms'), $status)];
        }
        return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => ''];
    }

    private function accessToken(): string
    {
        $res = wp_remote_post(self::TOKEN_URL, [
            'timeout' => self::TIMEOUT,
            'body'    => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type'    => 'refresh_token',
            ],
        ]);
        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            return '';
        }
        $body = json_decode((string) wp_remote_retrieve_body($res), true);
        return is_array($body) ? (string) ($body['access_token'] ?? '') : '';
    }

    private function deleteFile(string $formId, string $token): void
    {
        wp_remote_request('https://www.googleapis.com/drive/v3/files/' . rawurlencode($formId), [
            'method'  => 'DELETE',
            'timeout' => self::TIMEOUT,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
    }

    /**
     * @param array<string,mixed> $q
     * @return array<string,mixed>|null
     */
    private function translate(array $q): ?array
    {
        $title = trim((string) ($q['question'] ?? ''));
        if ($title === '') {
            return null;
        }
        $item = ['title' => $title, 'questionItem' => ['question' => ['required' => !empty($q['required'])]]];
        if (trim((string) ($q['help'] ?? '')) !== '') {
            $item['description'] = trim((string) $q['help']);
        }
        switch ($q['type'] ?? 'text') {
            case 'text':
            case 'email':
            case 'phone':
            case 'url':
            case 'numeric':
                $item['questionItem']['question']['textQuestion'] = ['paragraph' => false];
                return $item;
            case 'longtext':
                $item['questionItem']['question']['textQuestion'] = ['paragraph' => true];
                return $item;
            case 'multiplechoice':
            case 'multipleselect':
                $options = [];
                foreach ((array) ($q['options'] ?? []) as $o) {
                    if (trim((string) $o) !== '') {
                        $options[] = ['value' => trim((string) $o)];
                    }
                }
                if ($options === []) {
                    return null;
                }
                $item['questionItem']['question']['choiceQuestion'] = ['type' => $q['type'] === 'multiplechoice' ? 'RADIO' : 'CHECKBOX', 'options' => $options];
                return $item;
            case 'date':
                $item['questionItem']['question']['dateQuestion'] = ['includeTime' => false, 'includeYear' => true];
                return $item;
        }
        return null;
    }
}
