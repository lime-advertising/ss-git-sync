<?php

namespace SSGSS\Support;

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

    $settings['auth'] = normalize_auth($settings['auth'] ?? []);
    $settings['remote'] = normalize_remote($settings['remote'] ?? []);

    if (empty($settings['exports'])) {
        $settings['exports'] = trailingslashit($defaults['exports'] ?? '');
    } else {
        $settings['exports'] = trailingslashit(wp_normalize_path($settings['exports']));
    }

    return $settings;
}

function save_settings(string $option, array $settings): void {
    $settings['projects'] = normalize_projects($settings['projects'] ?? []);
    $settings['project_ids'] = sanitize_project_ids($settings['project_ids'] ?? []);
    $settings['auth'] = normalize_auth($settings['auth'] ?? []);
    $settings['remote'] = normalize_remote($settings['remote'] ?? []);

    if (isset($settings['exports'])) {
        $settings['exports'] = trailingslashit(wp_normalize_path($settings['exports']));
    }

    update_option($option, $settings, false);
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

function update_last_import(array $projects): void {
    $existing = get_option('ssgss_last_imports', []);
    if (!is_array($existing)) {
        $existing = [];
    }

    $now = time();
    foreach ($projects as $slug => $filename) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            continue;
        }
        $existing[$slug] = $now;
    }

    update_option('ssgss_last_imports', $existing, false);
}

function get_last_imports(): array {
    $stored = get_option('ssgss_last_imports', []);
    if (!is_array($stored)) {
        return [];
    }

    return array_map('intval', $stored);
}

function update_last_import_timestamps(array $projects): void {
    $existing = get_option('ssgss_last_imports', []);
    if (!is_array($existing)) {
        $existing = [];
    }

    foreach ($projects as $slug => $timestamp) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '' || !is_numeric($timestamp)) {
            continue;
        }
        $existing[$slug] = (int) $timestamp;
    }

    update_option('ssgss_last_imports', $existing, false);
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

function normalize_remote(array $remote): array {
    if (!is_array($remote)) {
        $remote = [];
    }

    $secret = is_string($remote['secret'] ?? '') ? $remote['secret'] : '';

    return [
        'secret' => $secret,
    ];
}

function validate_personal_access_token(string $token, string $repo): bool {
    $token = trim($token);
    if ($token === '') {
        return false;
    }

    $host = '';
    if ($repo !== '') {
        $parts = wp_parse_url($repo);
        $host = strtolower($parts['host'] ?? '');
    }

    // Only validate against GitHub; skip for other hosts.
    if ($host !== '' && strpos($host, 'github.com') === false) {
        return true;
    }

    $response = wp_remote_get(
        'https://api.github.com/user',
        [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept'        => 'application/vnd.github+json',
                'User-Agent'    => 'ss-git-sync',
            ],
            'timeout' => 10,
        ]
    );

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);

    return $code >= 200 && $code < 300;
}
