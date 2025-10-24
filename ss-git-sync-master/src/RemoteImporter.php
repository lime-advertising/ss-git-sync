<?php

namespace SSGSM;

use RuntimeException;
use SSGSM\Support;
use SSGSM\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class RemoteImporter {
    private array $settings;

    public function __construct(?array $settings = null) {
        $this->settings = $settings ?? Plugin::getSettings();
    }

    /**
     * @param array $targets Labels of secondary sites selected for import.
     * @return array{queued:int, success:array<string,string>, failed:array<string,string>, errors:array<string>}
     */
    public function trigger(array $targets): array {
        $targets = array_values(array_filter($targets, static fn($value) => is_string($value) && $value !== ''));
        if (empty($targets)) {
            throw new RuntimeException('No secondary sites selected.');
        }

        $index = [];
        foreach ($this->settings['secondaries'] ?? [] as $secondary) {
            if (!is_array($secondary) || empty($secondary['label'])) {
                continue;
            }
            $index[$secondary['label']] = $secondary;
        }

        $success = [];
        $failed = [];
        $errors = [];

        foreach ($targets as $label) {
            if (!isset($index[$label])) {
                $errors[] = sprintf(__('Unknown secondary site: %s', 'ssgs'), $label);
                continue;
            }

            $secondary = $index[$label];
            if (empty($secondary['url'])) {
                $errors[] = sprintf(__('Missing URL for %s.', 'ssgs'), $label);
                continue;
            }

            $secretPlain = Support\decrypt_secret($secondary['secret'] ?? '');
            if ($secretPlain === '') {
                $errors[] = sprintf(__('No shared secret stored for %s.', 'ssgs'), $label);
                continue;
            }

            $result = $this->dispatch($secondary, $secretPlain);
            if (($result['status'] ?? '') === 'success') {
                $success[$label] = $result['message'] ?? '';
            } else {
                $failed[$label] = $result['message'] ?? __('Remote import failed.', 'ssgs');
            }
        }

        return [
            'queued'  => count($success) + count($failed),
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ];
    }

    /**
     * @param array  $secondary Secondary site definition.
     * @param string $secret    Decrypted shared secret for the site.
     * @return array{status:string,message:string}
     */
    protected function dispatch(array $secondary, string $secret): array {
        $endpoint = $this->buildEndpoint($secondary['url'] ?? '');
        if ($endpoint === null) {
            $message = sprintf(__('Invalid URL for %s.', 'ssgs'), $secondary['label'] ?? __('Unnamed site', 'ssgs'));
            Logger::log('distributor', $message, 1);
            return ['status' => 'error', 'message' => $message];
        }

        $payload = [
            'source'       => home_url(),
            'dispatchedAt' => current_time('mysql'),
        ];

        $args = [
            'timeout'   => 15,
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'X-SSGS-Secret' => $secret,
            ],
            'body'      => wp_json_encode($payload),
            'sslverify' => apply_filters('ssgsm_distributor_sslverify', true, $secondary),
        ];

        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) {
            $message = sprintf(
                __('Error contacting %s: %s', 'ssgs'),
                $secondary['label'],
                $response->get_error_message()
            );
            Logger::log('distributor', $message, 1);
            return ['status' => 'error', 'message' => $message];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = null;
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if ($code >= 200 && $code < 300) {
            $status = is_array($data) ? ($data['status'] ?? 'success') : 'success';
            $message = is_array($data) ? ($data['message'] ?? '') : '';
            Logger::log(
                'distributor',
                sprintf(
                    'Remote import request sent to %s (%s). Status: %s',
                    $secondary['label'],
                    $endpoint,
                    $status
                )
            );

            if ($status === 'success') {
                return [
                    'status'  => 'success',
                    'message' => $message !== '' ? $message : __('Import completed.', 'ssgs'),
                ];
            }

            return [
                'status'  => 'error',
                'message' => $message !== '' ? $message : __('Remote site reported an error.', 'ssgs'),
            ];
        }

        $responseMessage = is_array($data) ? ($data['message'] ?? '') : $body;
        $message = sprintf(
            __('Unexpected response from %1$s (HTTP %2$d). %3$s', 'ssgs'),
            $secondary['label'],
            $code,
            $responseMessage
        );
        Logger::log('distributor', $message, 1);

        return ['status' => 'error', 'message' => $responseMessage];
    }

    private function buildEndpoint(string $baseUrl): ?string {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return null;
        }

        $parsed = wp_parse_url($baseUrl);
        if (empty($parsed['scheme'])) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        return trailingslashit($baseUrl) . 'wp-json/ssgs/v1/import';
    }
}
