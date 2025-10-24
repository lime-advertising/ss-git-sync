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
                ],
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
        $token = (string) $request->get_param('token');
        if ($token === '') {
            return new WP_Error('ssgs_rest_bad_request', __('Token payload required.', 'ssgs'), ['status' => 400]);
        }

        Logger::log('rest', sprintf('Received token update request (stub). Token length %d.', strlen($token)));

        return new WP_REST_Response(
            [
                'status'  => 'pending',
                'message' => __('Token update handler not implemented yet.', 'ssgs'),
            ],
            202
        );
    }
}
