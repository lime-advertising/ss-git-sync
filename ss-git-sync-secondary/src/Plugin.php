<?php

namespace SSGSS;

use SSGSS\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    public const OPTION = 'ssgss_settings';

    public static function init(): void {
        Admin::init();
        Cron::init();
    }

    public static function defaults(): array {
        return [
            'repo'        => '',
            'branch'      => 'main',
            'exports'     => SSGSS_PATH . 'exports/',
            'projects'    => [],
            'project_ids' => [],
            'auth'        => [
                'mode'     => 'ssh',
                'token'    => '',
                'username' => '',
            ],
            'cron'        => 'hourly',
        ];
    }

    public static function getSettings(): array {
        return Support\load_settings(self::OPTION, self::defaults());
    }

    public static function saveSettings(array $settings): void {
        Support\save_settings(self::OPTION, array_merge(self::defaults(), $settings));
    }
}
