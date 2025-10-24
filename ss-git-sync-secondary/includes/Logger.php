<?php

namespace SSGSS\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    public static function log(string $channel, string $message, int $code = 0): void {
        $file = WP_CONTENT_DIR . '/ss-git-sync.log';
        $line = sprintf('[%s] [%s] (%d) %s%s', gmdate('c'), $channel, $code, $message, PHP_EOL);
        file_put_contents($file, $line, FILE_APPEND);
    }
}
