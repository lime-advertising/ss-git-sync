<?php

namespace SSGSS;

use SSGSS\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Cron {
    private const HOOK = 'ssgss_cron_sync';

    public static function init(): void {
        add_action(self::HOOK, [__CLASS__, 'run']);
        register_activation_hook(SSGSS_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(SSGSS_FILE, [__CLASS__, 'deactivate']);
        add_filter('cron_schedules', [__CLASS__, 'registerInterval']);
    }

    public static function registerInterval(array $schedules): array {
        if (!isset($schedules['quarter-hourly'])) {
            $schedules['quarter-hourly'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __('Every 15 Minutes', 'ssgs'),
            ];
        }

        return $schedules;
    }

    public static function activate(): void {
        self::refreshSchedule();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public static function refreshSchedule(): void {
        wp_clear_scheduled_hook(self::HOOK);
        $settings = Plugin::getSettings();
        $recurrence = $settings['cron'] ?? 'hourly';
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, $recurrence, self::HOOK);
        }
    }

    public static function run(): void {
        try {
            (new Importer())->pullAndImportAll();
        } catch (\Throwable $e) {
            Logger::log('cron', 'Cron import failed: ' . $e->getMessage(), 1);
        }
    }
}
