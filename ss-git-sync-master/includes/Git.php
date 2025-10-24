<?php

namespace SSGSM\Support;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

class Git {
    private string $repoPath;
    private ?string $branch = null;

    /** @var array<string,bool> */
    private array $allowed = [
        'status'   => true,
        'add'      => true,
        'commit'   => true,
        'pull'     => true,
        'push'     => true,
        'reset'    => true,
        'checkout' => true,
        'init'     => true,
        'remote'   => true,
        'fetch'    => true,
        'branch'   => true,
        'rev-parse'=> true,
        'clean'    => true,
    ];

    public function __construct(string $repoPath) {
        $this->repoPath = rtrim($repoPath, '/');
        if (!is_dir($this->repoPath)) {
            wp_mkdir_p($this->repoPath);
        }
    }

    private function run(array $cmd, ?int &$code = null): string {
        if (empty($cmd) || $cmd[0] !== 'git') {
            throw new RuntimeException('Invalid git command');
        }

        $sub = $cmd[1] ?? '';
        if ($sub === '' || !isset($this->allowed[$sub])) {
            throw new RuntimeException('Git command not allowed: ' . implode(' ', $cmd));
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = getenv();
        $env['GIT_TERMINAL_PROMPT'] = '0';

        $process = proc_open($cmd, $descriptors, $pipes, $this->repoPath, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start git process');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        $combined = trim($stdout . (strlen($stderr) ? PHP_EOL . $stderr : ''));
        Logger::log('git', implode(' ', $cmd) . PHP_EOL . $combined, $code);

        if ($code !== 0) {
            throw new RuntimeException('Git failed: ' . $combined);
        }

        return $combined;
    }

    public function ensureRepo(string $remote, string $branch): void {
        $this->branch = $branch;

        if (!is_dir($this->repoPath . '/.git')) {
            $this->run(['git', 'init']);
            if ($remote !== '') {
                $this->run(['git', 'remote', 'add', 'origin', $remote]);
            }
            $this->removePlaceholder();
            if ($remote !== '' && $branch !== '') {
                try {
                    $this->run(['git', 'fetch', 'origin', $branch]);
                    $this->run(['git', 'checkout', '-b', $branch, 'origin/' . $branch]);
                } catch (RuntimeException $e) {
                    $this->run(['git', 'checkout', '-b', $branch]);
                }
            }
        } else {
            if ($remote !== '') {
                $this->run(['git', 'remote', 'set-url', 'origin', $remote]);
            }
            if ($branch !== '') {
                $fetchOk = true;
                try {
                    $this->removePlaceholder();
                    $this->run(['git', 'fetch', 'origin', $branch]);
                } catch (RuntimeException $e) {
                    $fetchOk = false;
                }

                try {
                    $this->run(['git', 'checkout', $branch]);
                } catch (RuntimeException $e) {
                    $this->run(['git', 'checkout', '-b', $branch]);
                }

                if ($fetchOk && $remote !== '') {
                    try {
                        $this->run(['git', 'reset', '--hard', 'origin/' . $branch]);
                    } catch (RuntimeException $e) {
                        // Remote branch may not exist yet.
                    }
                }
            }
        }
    }

    private function removePlaceholder(): void {
        $placeholder = $this->repoPath . '/.gitkeep';
        if (file_exists($placeholder) && !is_link($placeholder)) {
            @unlink($placeholder);
        }
    }

    public function addCommitPush(string $message, ?string $branch = null): void {
        $branch = $branch ?? $this->branch ?? 'HEAD';

        $this->run(['git', 'add', '.']);
        $status = $this->run(['git', 'status', '--porcelain']);
        if (trim($status) === '') {
            return;
        }
        $this->run(['git', 'commit', '-m', $message]);
        $this->run(['git', 'push', 'origin', $branch]);
    }

    public function pull(?string $branch = null): void {
        $branch = $branch ?? $this->branch ?? 'HEAD';
        $this->run(['git', 'pull', '--ff-only', 'origin', $branch]);
    }

    public function currentCommit(): string {
        $code = 0;
        try {
            $output = $this->run(['git', 'rev-parse', 'HEAD'], $code);
            if ($code === 0) {
                return trim($output);
            }
        } catch (RuntimeException $e) {
            // ignore
        }
        return '';
    }
}
