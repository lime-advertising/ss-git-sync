<?php

namespace SSGSS;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use SSGSS\Support;
use SSGSS\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Rest {
    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register']);
    }

    public static function register(): void {
        register_rest_route(
            'ssgs/v1',
            '/token',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'updateToken'],
                'permission_callback' => [__CLASS__, 'authorize'],
                'args'                => [
                    'token' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'username' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'ssgs/v1',
            '/import',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'runImport'],
                'permission_callback' => [__CLASS__, 'authorize'],
            ]
        );

        register_rest_route(
            'ssgs/v1',
            '/clear-cache',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'clearCache'],
                'permission_callback' => [__CLASS__, 'authorize'],
            ]
        );
    }

    public static function authorize(WP_REST_Request $request) {
        $settings = Plugin::getSettings();
        $secret = Support\decrypt_secret($settings['remote']['secret'] ?? '');

        if ($secret === '') {
            return new WP_Error('ssgs_rest_unconfigured', __('Remote secret not configured.', 'ssgs'), ['status' => 403]);
        }

        $provided = (string) $request->get_header('x-ssgs-secret');
        if ($provided === '') {
            return new WP_Error('ssgs_rest_missing_header', __('Missing authentication header.', 'ssgs'), ['status' => 401]);
        }

        if (!hash_equals($secret, $provided)) {
            return new WP_Error('ssgs_rest_forbidden', __('Invalid authentication secret.', 'ssgs'), ['status' => 403]);
        }

        return true;
    }

    public static function updateToken(WP_REST_Request $request) {
        $token = trim((string) $request->get_param('token'));
        if ($token === '') {
            return new WP_Error('ssgs_rest_bad_request', __('Token payload required.', 'ssgs'), ['status' => 400]);
        }

        $username = sanitize_text_field((string) $request->get_param('username'));

        $settings = Plugin::getSettings();
        if (!Support\validate_personal_access_token($token, $settings['repo'] ?? '')) {
            return new WP_Error('ssgs_rest_invalid_token', __('Token validation failed with GitHub. Token was not stored.', 'ssgs'), ['status' => 401]);
        }
        $auth = $settings['auth'] ?? [];
        $auth['token'] = Support\encrypt_secret($token);
        if ($username !== '') {
            $auth['username'] = $username;
        }
        $settings['auth'] = $auth;
        Plugin::saveSettings($settings);

        $importStatus = 'skipped';
        try {
            (new Importer())->pullAndImportAll();
            $importStatus = 'completed';
        } catch (\Throwable $e) {
            $importStatus = 'failed';
            Logger::log('rest', 'Importer run after token update failed: ' . $e->getMessage(), 1);
        }

        Logger::log('rest', sprintf('Token updated via REST. Import status: %s.', $importStatus));

        $message = __('Token stored.', 'ssgs');
        $status  = 'success';
        $http    = 200;
        if ($importStatus === 'failed') {
            $message = __('Token stored, but the importer failed. Check logs on this site.', 'ssgs');
            $status  = 'error';
            $http    = 500;
        } elseif ($importStatus === 'completed') {
            $message = __('Token stored and importer completed.', 'ssgs');
        }

        return new WP_REST_Response(
            [
                'status'  => $status,
                'message' => $message,
                'import'  => $importStatus,
            ],
            $http
        );
    }

    public static function runImport(WP_REST_Request $request) {
        try {
            (new Importer())->pullAndImportAll();
            Logger::log('rest', 'Remote import triggered via REST.');

            return new WP_REST_Response(
                [
                    'status'  => 'success',
                    'message' => __('Import completed.', 'ssgs'),
                ],
                200
            );
        } catch (\Throwable $e) {
            Logger::log('rest', 'Remote import failed: ' . $e->getMessage(), 1);

            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => sprintf(__('Remote import failed: %s', 'ssgs'), $e->getMessage()),
                ],
                500
            );
        }
    }

    public static function clearCache(WP_REST_Request $request) {
        if (!class_exists('Nextend\\SmartSlider3\\PublicApi\\Project')) {
            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => __('Smart Slider is not available on this site.', 'ssgs'),
                ],
                500
            );
        }

        $settings = Plugin::getSettings();
        $projects = $settings['projects'] ?? [];
        if (empty($projects)) {
            return new WP_REST_Response(
                [
                    'status'  => 'success',
                    'message' => __('No projects configured. Nothing to clear.', 'ssgs'),
                    'cleared' => [],
                ],
                200
            );
        }

        $projectIds = $settings['project_ids'] ?? [];
        $updatedIds = false;
        $cleared = [];
        $failed = [];

        foreach ($projects as $slug => $_file) {
            $slugKey = sanitize_title($slug);
            if ($slugKey === '') {
                continue;
            }

            $projectId = isset($projectIds[$slug]) ? (int) $projectIds[$slug] : 0;
            if (!$projectId) {
                $projectId = self::resolveSliderId($slugKey);
                if ($projectId) {
                    $projectIds[$slug] = $projectId;
                    $updatedIds = true;
                }
            }

            if (!$projectId) {
                $failed[$slug] = __('Unable to locate slider on the secondary site.', 'ssgs');
                continue;
            }

            try {
                \Nextend\SmartSlider3\PublicApi\Project::clearCache($projectId);
                $cleared[] = $slug;
            } catch (\Throwable $e) {
                $failed[$slug] = $e->getMessage();
                Logger::log('rest', 'Cache clear failed for ' . $slug . ': ' . $e->getMessage(), 1);
            }
        }

        if ($updatedIds) {
            $settings['project_ids'] = $projectIds;
            Plugin::saveSettings($settings);
        }

        if (!empty($failed)) {
            $message = sprintf(
                __('Cache clear failed for: %s.', 'ssgs'),
                implode(', ', array_map('sanitize_text_field', array_keys($failed)))
            );
            if (!empty($cleared)) {
                $message .= ' ' . sprintf(
                    __('Cleared for: %s.', 'ssgs'),
                    implode(', ', array_map('sanitize_text_field', $cleared))
                );
            }

            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => $message,
                    'cleared' => $cleared,
                    'failed'  => $failed,
                ],
                500
            );
        }

        Logger::log('rest', 'Cache cleared remotely for projects: ' . implode(', ', $cleared));

        return new WP_REST_Response(
            [
                'status'  => 'success',
                'message' => __('Cache cleared on the secondary site.', 'ssgs'),
                'cleared' => $cleared,
            ],
            200
        );
    }

    private static function resolveSliderId(string $slug): ?int {
        if (!class_exists('Nextend\\SmartSlider3\\Application\\ApplicationSmartSlider3') || !class_exists('Nextend\\SmartSlider3\\Application\\Model\\ModelSliders')) {
            return null;
        }

        try {
            $application = \Nextend\SmartSlider3\Application\ApplicationSmartSlider3::getInstance();
            $adminContext = $application->getApplicationTypeAdmin();
            if (!$adminContext) {
                return null;
            }

            $model = new \Nextend\SmartSlider3\Application\Model\ModelSliders($adminContext);
            $row = $model->getByAlias($slug);
            if (is_array($row) && isset($row['id'])) {
                return (int) $row['id'];
            }
        } catch (\Throwable $e) {
            Logger::log('rest', 'Failed to resolve slider id for ' . $slug . ': ' . $e->getMessage(), 1);
        }

        return null;
    }
}
