<?php

namespace SSGSM\Support;

if (!defined('ABSPATH')) {
    exit;
}

function normalize_projects(array $projects): array {
    $normalized = [];
    foreach ($projects as $key => $value) {
        if (is_array($value) && isset($value['slug'], $value['file'])) {
            $slug = sanitize_title((string) $value['slug']);
            $file = sanitize_file_name((string) $value['file']);
        } else {
            $slug = sanitize_title((string) $key);
            $file = sanitize_file_name((string) $value);
        }

        if ($slug !== '' && $file !== '') {
            $normalized[$slug] = $file;
        }
    }

    return $normalized;
}

function load_settings(string $option, array $defaults): array {
    $stored = get_option($option, []);
    if (!is_array($stored)) {
        $stored = [];
    }

    $settings = array_merge($defaults, $stored);
    $settings['projects'] = normalize_projects($settings['projects'] ?? []);

    $settings['project_ids'] = sanitize_project_ids($settings['project_ids'] ?? []);
    $settings['secondaries'] = normalize_secondaries($settings['secondaries'] ?? []);

    $settings['auth'] = normalize_auth($settings['auth'] ?? []);

    if (empty($settings['exports'])) {
        $settings['exports'] = trailingslashit($defaults['exports'] ?? '');
    } else {
        $settings['exports'] = trailingslashit(wp_normalize_path($settings['exports']));
    }

    return $settings;
}

function update_last_export(int $timestamp): void {
    update_option('ssgsm_last_export', $timestamp, false);
}

function get_last_export(): ?int {
    $ts = get_option('ssgsm_last_export');
    return is_numeric($ts) ? (int) $ts : null;
}

function save_settings(string $option, array $settings): void {
    $settings['projects'] = normalize_projects($settings['projects'] ?? []);
    $settings['project_ids'] = sanitize_project_ids($settings['project_ids'] ?? []);
    $settings['secondaries'] = normalize_secondaries($settings['secondaries'] ?? []);
    $settings['auth'] = normalize_auth($settings['auth'] ?? []);

    if (isset($settings['exports'])) {
        $settings['exports'] = trailingslashit(wp_normalize_path($settings['exports']));
    }

    update_option($option, $settings, false);

    if (isset($settings['secondaries']) && is_array($settings['secondaries'])) {
        $labels = array_map(
            static fn($secondary) => sanitize_text_field((string) ($secondary['label'] ?? '')),
            $settings['secondaries']
        );
        prune_secondary_statuses($labels);
    }
}

function sanitize_project_ids(array $ids): array {
    $sanitized = [];
    foreach ($ids as $slug => $id) {
        $slug = sanitize_title((string) $slug);
        $id   = (int) $id;
        if ($slug !== '' && $id > 0) {
            $sanitized[$slug] = $id;
        }
    }

    return $sanitized;
}

function normalize_auth(array $auth): array {
    $token = is_string($auth['token'] ?? '') ? $auth['token'] : '';
    $username = isset($auth['username']) ? sanitize_text_field((string) $auth['username']) : '';

    return [
        'token'    => $token,
        'username' => $username,
    ];
}

function encrypt_secret(string $value): string {
    if ($value === '') {
        return '';
    }

    $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'));
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($cipher === false) {
        return '';
    }

    return base64_encode($iv . $cipher);
}

function decrypt_secret(?string $value): string {
    if (empty($value)) {
        return '';
    }

    $decoded = base64_decode($value, true);
    if ($decoded === false || strlen($decoded) <= 16) {
        return '';
    }

    $iv = substr($decoded, 0, 16);
    $cipher = substr($decoded, 16);
    $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'));
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return $plain === false ? '' : $plain;
}

function normalize_secondaries(array $secondaries): array {
    $normalized = [];
    foreach ($secondaries as $secondary) {
        if (!is_array($secondary)) {
            continue;
        }

        $label = sanitize_text_field((string) ($secondary['label'] ?? ''));
        $url = esc_url_raw((string) ($secondary['url'] ?? ''));
        $secret = is_string($secondary['secret'] ?? '') ? $secondary['secret'] : '';

        if ($label === '' || $url === '') {
            continue;
        }

        $normalized[$label] = [
            'label'  => $label,
            'url'    => $url,
            'secret' => $secret,
        ];
    }

    return array_values($normalized);
}

function merge_secondary_input(array $input, array $existing): array {
    $labels = $input['label'] ?? [];
    $urls = $input['url'] ?? [];
    $secrets = $input['secret'] ?? [];

    $indexedExisting = [];
    foreach (normalize_secondaries($existing) as $secondary) {
        $indexedExisting[$secondary['label']] = $secondary;
    }

    $count = max(count($labels), count($urls));
    $merged = [];

    for ($i = 0; $i < $count; $i++) {
        $label = sanitize_text_field($labels[$i] ?? '');
        $url = esc_url_raw($urls[$i] ?? '');
        $incomingSecret = isset($secrets[$i]) ? trim((string) $secrets[$i]) : '';

        if ($label === '' || $url === '') {
            continue;
        }

        $stored = $indexedExisting[$label]['secret'] ?? '';
        if ($incomingSecret !== '') {
            $stored = encrypt_secret($incomingSecret);
        }

        $merged[$label] = [
            'label'  => $label,
            'url'    => $url,
            'secret' => $stored,
        ];
    }

    return array_values($merged);
}

function get_secondary_statuses(): array {
    $stored = get_option('ssgsm_secondary_status', []);
    if (!is_array($stored)) {
        return [];
    }

    $normalized = [];
    foreach ($stored as $label => $data) {
        $label = sanitize_text_field((string) $label);
        if ($label === '') {
            continue;
        }

        $timestamp = isset($data['timestamp']) && is_numeric($data['timestamp']) ? (int) $data['timestamp'] : 0;
        $action = sanitize_key($data['action'] ?? '');
        $status = sanitize_key($data['status'] ?? '');
        $message = sanitize_textarea_field($data['message'] ?? '');

        $normalized[$label] = [
            'timestamp' => $timestamp,
            'action'    => $action,
            'status'    => $status,
            'message'   => $message,
        ];
    }

    return $normalized;
}

function record_secondary_status(string $label, array $status): void {
    $label = sanitize_text_field($label);
    if ($label === '') {
        return;
    }

    $stored = get_option('ssgsm_secondary_status', []);
    if (!is_array($stored)) {
        $stored = [];
    }

    $timestamp = isset($status['timestamp']) && is_numeric($status['timestamp'])
        ? (int) $status['timestamp']
        : (int) current_time('timestamp');
    $action = sanitize_key($status['action'] ?? '');
    $result = sanitize_key($status['status'] ?? '');
    $message = sanitize_textarea_field($status['message'] ?? '');

    $stored[$label] = [
        'timestamp' => $timestamp,
        'action'    => $action,
        'status'    => $result,
        'message'   => $message,
    ];

    update_option('ssgsm_secondary_status', $stored, false);
}

function prune_secondary_statuses(array $labels): void {
    $stored = get_option('ssgsm_secondary_status', []);
    if (!is_array($stored) || empty($stored)) {
        return;
    }

    $valid = array_unique(array_filter(array_map('sanitize_text_field', $labels)));

    $pruned = [];
    foreach ($stored as $label => $data) {
        $sanitizedLabel = sanitize_text_field((string) $label);
        if ($sanitizedLabel === '') {
            continue;
        }
        if (in_array($sanitizedLabel, $valid, true)) {
            $pruned[$sanitizedLabel] = $data;
        }
    }

    if ($pruned !== $stored) {
        update_option('ssgsm_secondary_status', $pruned, false);
    }
}
