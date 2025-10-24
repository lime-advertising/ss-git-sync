<?php

namespace SSGSM;

use RuntimeException;
use SSGSM\Support;
use SSGSM\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Distributor {
    private array $settings;

    public function __construct(?array $settings = null) {
        $this->settings = $settings ?? Plugin::getSettings();
    }

    /**
     * @param string $token   Personal access token to distribute.
     * @param array  $targets Labels of secondary sites selected for update.
     * @return array{queued:int, sent:array<string>, pending:array<string>, errors:array<string>}
     */
    public function pushToken(string $token, array $targets): array {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Token missing.');
        }

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

        $sent = [];
        $pending = [];
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

            $status = $this->dispatch($secondary, $token, $secretPlain);
            if ($status === 'sent') {
                $sent[] = $label;
            } else {
                $pending[] = $label;
            }
        }

        return [
            'queued'  => count($sent) + count($pending),
            'sent'    => $sent,
            'pending' => $pending,
            'errors'  => $errors,
        ];
    }

    /**
     * Placeholder for HTTP dispatch logic.
     *
     * @param array  $secondary Secondary site definition.
     * @param string $token     Token to transmit.
     * @param string $secret    Decrypted shared secret for the site.
     * @return string           'sent' when the request succeeded, otherwise 'pending'.
     */
    protected function dispatch(array $secondary, string $token, string $secret): string {
        Logger::log(
            'distributor',
            sprintf(
                'Token distribution stub for %s (%s). Secret length %d. Token length %d.',
                $secondary['label'],
                $secondary['url'],
                strlen($secret),
                strlen($token)
            )
        );

        return 'pending';
    }
}
