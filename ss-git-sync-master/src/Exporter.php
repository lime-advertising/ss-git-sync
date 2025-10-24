<?php

namespace SSGSM;

use RuntimeException;
use SSGSM\Support;
use SSGSM\Support\Git;
use SSGSM\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Exporter {
    private Git $git;
    private array $settings;

    public function __construct() {
        $this->settings = Plugin::getSettings();
        $this->git = new Git($this->settings['exports']);
    }

    public function exportAndPushAll(): void {
        $remote = $this->resolveRemote();

        $this->git->ensureRepo($remote, $this->settings['branch']);

        foreach ($this->settings['projects'] as $slug => $filename) {
            $this->exportProject($slug, $filename);
        }

        $message = sprintf('SSGSM export on %s @ %s', home_url(), current_time('mysql'));
        $this->git->addCommitPush($message, $this->settings['branch']);
    }

    private function resolveRemote(): string {
        $remote = trim((string) ($this->settings['repo'] ?? ''));
        if ($remote === '') {
            throw new RuntimeException('Repository URL not configured.');
        }

        $auth = $this->settings['auth'] ?? [];
        if (($auth['mode'] ?? 'ssh') === 'https-token') {
            $token = Support\decrypt_secret($auth['token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('HTTPS token mode selected but no token is stored.');
            }
            $username = sanitize_text_field($auth['username'] ?? '');
            $remote = $this->buildHttpsRemote($remote, $username, $token);
        }

        return $remote;
    }

    private function buildHttpsRemote(string $base, string $username, string $token): string {
        $parts = wp_parse_url($base);
        if (!$parts || empty($parts['host'])) {
            // Attempt to convert SSH-style URL (git@github.com:org/repo.git)
            if (preg_match('~git@([^:]+):(.+)~', $base, $m)) {
                $parts = [
                    'scheme' => 'https',
                    'host'   => $m[1],
                    'path'   => '/' . ltrim($m[2], '/'),
                ];
            } else {
                throw new RuntimeException('Invalid repository URL.');
            }
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'];
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = $parts['path'] ?? '';
        $query  = isset($parts['query']) ? '?' . $parts['query'] : '';

        if ($username === '') {
            $auth = rawurlencode('x-oauth-basic') . ':' . rawurlencode($token);
        } else {
            $auth = rawurlencode($username) . ':' . rawurlencode($token);
        }

        return sprintf('%s://%s@%s%s%s%s', $scheme, $auth, $host, $port, $path, $query);
    }

    private function exportProject(string $slug, string $filename): void {
        $projectId = $this->resolveProjectId($slug);
        if (!$projectId) {
            Logger::log('export', 'Project not found for slug ' . $slug, 1);
            return;
        }

        if (!class_exists('Nextend\\SmartSlider3\\BackupSlider\\ExportSlider') || !class_exists('Nextend\\SmartSlider3\\Application\\ApplicationSmartSlider3')) {
            throw new RuntimeException('Smart Slider 3 export classes unavailable.');
        }

        $destination = trailingslashit($this->settings['exports']) . $filename;
        wp_mkdir_p(dirname($destination));

        try {
            $application = \Nextend\SmartSlider3\Application\ApplicationSmartSlider3::getInstance();
            $adminContext = $application->getApplicationTypeAdmin();
            if (!$adminContext) {
                throw new RuntimeException('Unable to obtain Smart Slider admin context.');
            }

            $exporter = new \Nextend\SmartSlider3\BackupSlider\ExportSlider($adminContext, $projectId);
            $tempFile = $exporter->create(true);
            if (!$tempFile || !file_exists($tempFile)) {
                throw new RuntimeException('Smart Slider export failed for ' . $slug);
            }

            if (!@copy($tempFile, $destination)) {
                @unlink($tempFile);
                throw new RuntimeException('Failed to copy export file for ' . $slug);
            }

            @unlink($tempFile);
            Logger::log('export', sprintf('Exported %s to %s', $slug, $destination));
        } catch (\Throwable $e) {
            Logger::log('export', 'Export failed for ' . $slug . ': ' . $e->getMessage(), 1);
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function resolveProjectId(string $slug): ?int {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

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
            Logger::log('export', 'Failed to resolve project id for ' . $slug . ': ' . $e->getMessage(), 1);
        }

        return null;
    }
}
