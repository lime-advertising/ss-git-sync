<?php

namespace SSGSM;

use SSGSM\Support;
use SSGSM\Support\Logger;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'addMenu']);
        add_action('admin_post_ssgsm_save_settings', [__CLASS__, 'handleSave']);
        add_action('admin_post_ssgsm_export_now', [__CLASS__, 'exportNow']);
        add_action('admin_post_ssgsm_push_token', [__CLASS__, 'pushToken']);
        add_action('admin_post_ssgsm_remote_import', [__CLASS__, 'remoteImport']);
        add_action('admin_post_ssgsm_remote_clear_cache', [__CLASS__, 'remoteClearCache']);
    }

    public static function addMenu(): void {
        add_menu_page(
            __('SS Git Sync', 'ssgs'),
            __('SS Git Sync', 'ssgs'),
            'manage_options',
            'ssgs',
            [__CLASS__, 'render'],
            'dashicons-update',
            81
        );

        add_submenu_page(
            'ssgs',
            __('Logs', 'ssgs'),
            __('Logs', 'ssgs'),
            'manage_options',
            'ssgs-logs',
            [__CLASS__, 'renderLogs']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = Plugin::getSettings();
        $secondaryStatuses = Support\get_secondary_statuses();
        $message  = get_transient('ssgsm_notice');
        if ($message) {
            printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($message['type']), esc_html($message['text']));
            delete_transient('ssgsm_notice');
        }

        $last = Support\get_last_export();
        $secondaries = $settings['secondaries'];
        $secondariesForPush = $secondaries;
        $hasSecondaries = !empty($secondariesForPush);
        ?>
        <div class="wrap ssgsm-wrap">
            <h1><?php esc_html_e('SS Git Sync (Master)', 'ssgs'); ?></h1>
            <style>
                .ssgsm-wrap {
                    max-width: 1100px;
                }
                .ssgsm-panels {
                    display: grid;
                    gap: 1rem;
                    margin-top: 1.5rem;
                }
                .ssgsm-panel {
                    border: 1px solid #dcdcde;
                    border-radius: 8px;
                    background: #fff;
                    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
                    overflow: hidden;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                }
                .ssgsm-panel:not(.is-open) {
                    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
                }
                .ssgsm-panel__header {
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 1rem 1.2rem;
                    background: linear-gradient(180deg, #fdfdfd 0%, #f5f5f5 100%);
                    border: 0;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    color: #1d2327;
                }
                .ssgsm-panel__header:hover {
                    background: #f0f0f1;
                }
                .ssgsm-panel__header:focus-visible {
                    outline: 2px solid #2271b1;
                    outline-offset: -2px;
                }
                .ssgsm-panel__icon {
                    font-size: 18px;
                    transition: transform 0.2s ease;
                }
                .ssgsm-panel:not(.is-open) .ssgsm-panel__icon {
                    transform: rotate(-90deg);
                }
                .ssgsm-panel__content {
                    padding: 1.2rem 1.4rem 1.4rem;
                    background: #fff;
                }
                .ssgsm-panel__content table.form-table {
                    margin-top: 0;
                }
                .ssgsm-panel__content table.form-table th {
                    padding-top: 0;
                }
                .ssgsm-form-table th {
                    width: 220px;
                }
                .ssgsm-description {
                    margin: 0 0 1rem;
                    color: #50575e;
                    max-width: 60ch;
                }
                .ssgsm-panel .widefat {
                    border-radius: 6px;
                    overflow: hidden;
                }
                .ssgsm-panel .widefat input[type="text"],
                .ssgsm-panel .widefat input[type="url"],
                .ssgsm-panel .widefat input[type="password"] {
                    width: 100%;
                    box-sizing: border-box;
                }
                .ssgsm-panel .widefat th {
                    background: #f6f7f7;
                }
                .ssgsm-toggle-buttons {
                    display: flex;
                    gap: 0.75rem;
                    margin-bottom: 0.75rem;
                    flex-wrap: wrap;
                }
                .ssgsm-toggle-buttons .button-link {
                    padding: 0;
                }
                .ssgsm-panel__content .description {
                    color: #50575e;
                }
                .ssgsm-status-table td,
                .ssgsm-status-table th {
                    vertical-align: top;
                }
            </style>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ssgsm-form">
                <?php wp_nonce_field('ssgsm_save_settings'); ?>
                <input type="hidden" name="action" value="ssgsm_save_settings">
                <div class="ssgsm-panels">
                    <section class="ssgsm-panel is-open" data-ssgsm-collapsible>
                        <button type="button" class="ssgsm-panel__header" aria-expanded="true" aria-controls="ssgsm-panel-settings">
                            <span class="ssgsm-panel__title"><?php esc_html_e('Repository & Access', 'ssgs'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                        </button>
                        <div class="ssgsm-panel__content" id="ssgsm-panel-settings">
                            <table class="form-table ssgsm-form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Repository URL', 'ssgs'); ?></th>
                                        <td>
                                            <input type="text" class="regular-text" name="settings[repo]" value="<?php echo esc_attr($settings['repo']); ?>">
                                            <?php if (!empty($settings['repo']) && $href = esc_url(self::repoLink($settings['repo']))): ?>
                                                <p><a href="<?php echo $href; ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open repository', 'ssgs'); ?></a></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Branch', 'ssgs'); ?></th>
                                        <td><input type="text" class="regular-text" name="settings[branch]" value="<?php echo esc_attr($settings['branch']); ?>"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Exports Directory', 'ssgs'); ?></th>
                                        <td>
                                            <input type="text" class="regular-text" name="settings[exports]" value="<?php echo esc_attr($settings['exports']); ?>">
                                            <p class="description"><?php esc_html_e('Absolute path where exported .ss3 files are written.', 'ssgs'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Personal Access Token', 'ssgs'); ?></th>
                                        <td>
                                            <p class="description ssgsm-description"><?php esc_html_e('Paste a GitHub Personal Access Token with read/write access to the repository. Leave the token field blank on later saves to keep the stored value.', 'ssgs'); ?></p>
                                            <p>
                                                <label><?php esc_html_e('Username (optional)', 'ssgs'); ?><br>
                                                    <input type="text" name="settings[auth][username]" value="<?php echo esc_attr($settings['auth']['username']); ?>">
                                                </label>
                                            </p>
                                            <p>
                                                <label><?php esc_html_e('Personal Access Token', 'ssgs'); ?><br>
                                                    <input type="password" name="settings[auth][token]" value="" autocomplete="new-password">
                                                </label>
                                            </p>
                                            <?php if (!empty($settings['auth']['token'])) : ?>
                                                <p class="description"><?php esc_html_e('A token is stored already. Tick the box below to remove it.', 'ssgs'); ?></p>
                                                <label><input type="checkbox" name="settings[auth][clear]" value="1"> <?php esc_html_e('Clear stored token on save', 'ssgs'); ?></label>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="ssgsm-panel" data-ssgsm-collapsible>
                        <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-projects">
                            <span class="ssgsm-panel__title"><?php esc_html_e('Project Map', 'ssgs'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                        </button>
                        <div class="ssgsm-panel__content" id="ssgsm-panel-projects">
                            <p class="ssgsm-description"><?php esc_html_e('Map each Smart Slider project slug to the exported .ss3 file name stored in the repository.', 'ssgs'); ?></p>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Alias', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Filename (.ss3)', 'ssgs'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="ssgsm-project-rows">
                                <?php
                                $projectMap = $settings['projects'];
                                if (empty($projectMap)) {
                                    $projectMap = ['' => ''];
                                }
                                foreach ($projectMap as $slug => $file) :
                                    ?>
                                    <tr>
                                        <td><input type="text" name="projects[slug][]" value="<?php echo esc_attr($slug); ?>" placeholder="homepage_hero"></td>
                                        <td><input type="text" name="projects[file][]" value="<?php echo esc_attr($file); ?>" placeholder="homepage_hero.ss3"></td>
                                        <td><button type="button" class="button" onclick="this.closest('tr').remove();"><?php esc_html_e('Remove', 'ssgs'); ?></button></td>
                                    </tr>
                                    <?php
                                endforeach;
                                ?>
                                </tbody>
                            </table>
                            <p><button class="button" type="button" id="ssgsm-add-project"><?php esc_html_e('Add Project', 'ssgs'); ?></button></p>
                            <template id="ssgsm-project-template"><tr><td><input type="text" name="projects[slug][]" placeholder="homepage_hero"></td><td><input type="text" name="projects[file][]" placeholder="homepage_hero.ss3"></td><td><button type="button" class="button" onclick="this.closest('tr').remove();"><?php esc_html_e('Remove', 'ssgs'); ?></button></td></tr></template>
                        </div>
                    </section>
                    <section class="ssgsm-panel" data-ssgsm-collapsible>
                        <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-secondaries">
                            <span class="ssgsm-panel__title"><?php esc_html_e('Secondary Sites', 'ssgs'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                        </button>
                        <div class="ssgsm-panel__content" id="ssgsm-panel-secondaries">
                            <p class="ssgsm-description"><?php esc_html_e('List each downstream site that should receive tokens. Secrets are stored encrypted; leave the field blank to keep the existing value.', 'ssgs'); ?></p>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Label', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Site URL', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Shared Secret', 'ssgs'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="ssgsm-secondary-rows">
                                <?php
                                $secondaryList = $secondaries;
                                if (empty($secondaryList)) {
                                    $secondaryList = [
                                        [
                                            'label'  => '',
                                            'url'    => '',
                                            'secret' => '',
                                        ],
                                    ];
                                }
                                foreach ($secondaryList as $secondary) :
                                    ?>
                                    <tr>
                                        <td><input type="text" name="secondaries[label][]" value="<?php echo esc_attr($secondary['label'] ?? ''); ?>" placeholder="Site Name"></td>
                                        <td><input type="url" name="secondaries[url][]" value="<?php echo esc_attr($secondary['url'] ?? ''); ?>" placeholder="https://example.com"></td>
                                        <td>
                                            <input type="password" name="secondaries[secret][]" value="" placeholder="<?php esc_attr_e('Enter new secret', 'ssgs'); ?>" autocomplete="new-password">
                                            <?php if (!empty($secondary['secret'])) : ?>
                                                <p class="description"><?php esc_html_e('Secret stored. Leave blank to keep.', 'ssgs'); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td><button type="button" class="button" onclick="this.closest('tr').remove();"><?php esc_html_e('Remove', 'ssgs'); ?></button></td>
                                    </tr>
                                    <?php
                                endforeach;
                                ?>
                                </tbody>
                            </table>
                            <p><button class="button" type="button" id="ssgsm-add-secondary"><?php esc_html_e('Add Site', 'ssgs'); ?></button></p>
                            <template id="ssgsm-secondary-template"><tr><td><input type="text" name="secondaries[label][]" placeholder="Site Name"></td><td><input type="url" name="secondaries[url][]" placeholder="https://example.com"></td><td><input type="password" name="secondaries[secret][]" placeholder="<?php esc_attr_e('Enter new secret', 'ssgs'); ?>" autocomplete="new-password"></td><td><button type="button" class="button" onclick="this.closest('tr').remove();"><?php esc_html_e('Remove', 'ssgs'); ?></button></td></tr></template>
                        </div>
                    </section>
                </div>
                <?php submit_button(__('Save Settings', 'ssgs')); ?>
            </form>
            <div class="ssgsm-panels">
                <section class="ssgsm-panel" data-ssgsm-collapsible>
                    <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-export">
                        <span class="ssgsm-panel__title"><?php esc_html_e('Manual Export', 'ssgs'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                    </button>
                    <div class="ssgsm-panel__content" id="ssgsm-panel-export">
                        <p class="ssgsm-description"><?php esc_html_e('Run an immediate export and push without waiting for the scheduled task.', 'ssgs'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('ssgsm_export_now'); ?>
                            <input type="hidden" name="action" value="ssgsm_export_now">
                            <?php submit_button(__('Export & Push Now', 'ssgs'), 'secondary', ''); ?>
                        </form>
                        <?php if ($last) : ?>
                            <p class="description">
                                <?php
                                printf(
                                    '%s %s (%s)',
                                    esc_html__('Last export:', 'ssgs'),
                                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last)),
                                    esc_html(human_time_diff($last))
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="ssgsm-panel" data-ssgsm-collapsible>
                    <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-token">
                        <span class="ssgsm-panel__title"><?php esc_html_e('Distribute HTTPS Token', 'ssgs'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                    </button>
                    <div class="ssgsm-panel__content" id="ssgsm-panel-token">
                        <?php if ($hasSecondaries) : ?>
                            <p class="ssgsm-description"><?php esc_html_e('Paste a new Personal Access Token and select the sites that should receive it. Tokens are relayed immediately and are not stored on the master site.', 'ssgs'); ?></p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('ssgsm_push_token'); ?>
                                <input type="hidden" name="action" value="ssgsm_push_token">
                                <table class="form-table" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><label for="ssgsm-distribute-token"><?php esc_html_e('Personal Access Token', 'ssgs'); ?></label></th>
                                            <td><input type="password" id="ssgsm-distribute-token" name="token" class="regular-text" autocomplete="new-password" required></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Target Sites', 'ssgs'); ?></th>
                                            <td>
                                                <div class="ssgsm-toggle-buttons" data-ssgsm-toggle-group="token">
                                                    <button type="button" class="button-link" data-action="select"><?php esc_html_e('Select all', 'ssgs'); ?></button>
                                                    <button type="button" class="button-link" data-action="deselect"><?php esc_html_e('Deselect all', 'ssgs'); ?></button>
                                                </div>
                                                <?php foreach ($secondariesForPush as $secondary) :
                                                    $label = $secondary['label'] ?: $secondary['url'];
                                                    ?>
                                                    <label style="display:block;margin-bottom:0.5rem;">
                                                        <input type="checkbox" name="targets[]" value="<?php echo esc_attr($secondary['label']); ?>" data-ssgsm-group="token" <?php checked(true); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </label>
                                                    <?php
                                                endforeach; ?>
                                                <p class="description"><?php esc_html_e('Uncheck any site that should not receive the new token.', 'ssgs'); ?></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php submit_button(__('Push Token to Selected Sites', 'ssgs')); ?>
                            </form>
                        <?php else : ?>
                            <p class="ssgsm-description"><?php esc_html_e('Add at least one secondary site to enable token distribution.', 'ssgs'); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="ssgsm-panel" data-ssgsm-collapsible>
                    <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-import">
                        <span class="ssgsm-panel__title"><?php esc_html_e('Trigger Remote Import', 'ssgs'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                    </button>
                    <div class="ssgsm-panel__content" id="ssgsm-panel-import">
                        <?php if ($hasSecondaries) : ?>
                            <p class="ssgsm-description"><?php esc_html_e('Select sites that should immediately pull Git and import sliders. The call runs with each site’s shared secret.', 'ssgs'); ?></p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('ssgsm_remote_import'); ?>
                                <input type="hidden" name="action" value="ssgsm_remote_import">
                                <table class="form-table" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Target Sites', 'ssgs'); ?></th>
                                            <td>
                                                <div class="ssgsm-toggle-buttons" data-ssgsm-toggle-group="import">
                                                    <button type="button" class="button-link" data-action="select"><?php esc_html_e('Select all', 'ssgs'); ?></button>
                                                    <button type="button" class="button-link" data-action="deselect"><?php esc_html_e('Deselect all', 'ssgs'); ?></button>
                                                </div>
                                                <?php foreach ($secondariesForPush as $secondary) :
                                                    $label = $secondary['label'] ?: $secondary['url'];
                                                    ?>
                                                    <label style="display:block;margin-bottom:0.5rem;">
                                                        <input type="checkbox" name="targets[]" value="<?php echo esc_attr($secondary['label']); ?>" data-ssgsm-group="import" <?php checked(true); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </label>
                                                    <?php
                                                endforeach; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php submit_button(__('Run Import on Selected Sites', 'ssgs')); ?>
                            </form>
                        <?php else : ?>
                            <p class="ssgsm-description"><?php esc_html_e('Add at least one secondary site to trigger remote imports.', 'ssgs'); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="ssgsm-panel" data-ssgsm-collapsible>
                    <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-cache">
                        <span class="ssgsm-panel__title"><?php esc_html_e('Clear Remote Cache', 'ssgs'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                    </button>
                    <div class="ssgsm-panel__content" id="ssgsm-panel-cache">
                        <?php if ($hasSecondaries) : ?>
                            <p class="ssgsm-description"><?php esc_html_e('Flush cached Smart Slider assets on selected secondary sites without re-importing content.', 'ssgs'); ?></p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('ssgsm_remote_clear_cache'); ?>
                                <input type="hidden" name="action" value="ssgsm_remote_clear_cache">
                                <table class="form-table" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Target Sites', 'ssgs'); ?></th>
                                            <td>
                                                <div class="ssgsm-toggle-buttons" data-ssgsm-toggle-group="cache">
                                                    <button type="button" class="button-link" data-action="select"><?php esc_html_e('Select all', 'ssgs'); ?></button>
                                                    <button type="button" class="button-link" data-action="deselect"><?php esc_html_e('Deselect all', 'ssgs'); ?></button>
                                                </div>
                                                <?php foreach ($secondariesForPush as $secondary) :
                                                    $label = $secondary['label'] ?: $secondary['url'];
                                                    ?>
                                                    <label style="display:block;margin-bottom:0.5rem;">
                                                        <input type="checkbox" name="targets[]" value="<?php echo esc_attr($secondary['label']); ?>" data-ssgsm-group="cache" <?php checked(true); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </label>
                                                    <?php
                                                endforeach; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php submit_button(__('Clear Cache on Selected Sites', 'ssgs')); ?>
                            </form>
                        <?php else : ?>
                            <p class="ssgsm-description"><?php esc_html_e('Add at least one secondary site to clear caches remotely.', 'ssgs'); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
                <?php if ($hasSecondaries) : ?>
                    <section class="ssgsm-panel" data-ssgsm-collapsible>
                        <button type="button" class="ssgsm-panel__header" aria-expanded="false" aria-controls="ssgsm-panel-status">
                            <span class="ssgsm-panel__title"><?php esc_html_e('Secondary Site Status', 'ssgs'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2 ssgsm-panel__icon"></span>
                        </button>
                        <div class="ssgsm-panel__content" id="ssgsm-panel-status">
                            <?php
                            $actionLabels = [
                                'token'  => __('Token Distribution', 'ssgs'),
                                'import' => __('Remote Import', 'ssgs'),
                                'cache'  => __('Cache Clear', 'ssgs'),
                            ];
                            $statusLabels = [
                                'success' => __('Success', 'ssgs'),
                                'error'   => __('Error', 'ssgs'),
                            ];
                            ?>
                            <table class="widefat fixed striped ssgsm-status-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Site', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Last Action', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Result', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Updated', 'ssgs'); ?></th>
                                        <th><?php esc_html_e('Message', 'ssgs'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($secondariesForPush as $secondary) :
                                        $labelKey = $secondary['label'] ?? '';
                                        $labelDisplay = $secondary['label'] ?: $secondary['url'];
                                        $statusData = $labelKey !== '' ? ($secondaryStatuses[$labelKey] ?? null) : null;
                                        $action = $statusData['action'] ?? '';
                                        $actionDisplay = $actionLabels[$action] ?? ($statusData ? ucfirst($action) : __('Never triggered', 'ssgs'));
                                        $result = $statusData['status'] ?? '';
                                        $resultDisplay = $result !== '' ? ($statusLabels[$result] ?? ucfirst($result)) : __('—', 'ssgs');
                                        $messageText = $statusData['message'] ?? '';
                                        $timestamp = isset($statusData['timestamp']) ? (int) $statusData['timestamp'] : 0;
                                        if ($timestamp > 0) {
                                            $timeDisplay = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
                                            $timeDiff = human_time_diff($timestamp, current_time('timestamp'));
                                            $timeHtml = esc_html($timeDisplay) . '<br><span class="description">' . esc_html(sprintf(__('%s ago', 'ssgs'), $timeDiff)) . '</span>';
                                        } else {
                                            $timeHtml = esc_html__('—', 'ssgs');
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($labelDisplay); ?></td>
                                            <td><?php echo esc_html($actionDisplay); ?></td>
                                            <td><?php echo esc_html($resultDisplay); ?></td>
                                            <td><?php echo wp_kses_post($timeHtml); ?></td>
                                            <td><?php echo $messageText !== '' ? nl2br(esc_html($messageText)) : esc_html__('—', 'ssgs'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
        <script>
            (function() {
                const projectAddButton = document.getElementById('ssgsm-add-project');
                if (projectAddButton) {
                    projectAddButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        const rows = document.getElementById('ssgsm-project-rows');
                        const template = document.getElementById('ssgsm-project-template');
                        if (rows && template) {
                            rows.insertAdjacentHTML('beforeend', template.innerHTML);
                        }
                    });
                }

                const secondaryAddButton = document.getElementById('ssgsm-add-secondary');
                if (secondaryAddButton) {
                    secondaryAddButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        const rows = document.getElementById('ssgsm-secondary-rows');
                        const template = document.getElementById('ssgsm-secondary-template');
                        if (rows && template) {
                            rows.insertAdjacentHTML('beforeend', template.innerHTML);
                        }
                    });
                }

                const toggleContainers = document.querySelectorAll('[data-ssgsm-toggle-group]');
                toggleContainers.forEach(function(container) {
                    container.addEventListener('click', function(event) {
                        const trigger = event.target.closest('button[data-action]');
                        if (!trigger) {
                            return;
                        }
                        event.preventDefault();
                        const action = trigger.getAttribute('data-action');
                        const group = container.getAttribute('data-ssgsm-toggle-group');
                        if (!action || !group) {
                            return;
                        }
                        const shouldCheck = action === 'select';
                        document.querySelectorAll('input[type="checkbox"][data-ssgsm-group="' + group + '"]').forEach(function(checkbox) {
                            checkbox.checked = shouldCheck;
                        });
                    });
                });

                const collapsiblePanels = document.querySelectorAll('[data-ssgsm-collapsible]');
                collapsiblePanels.forEach(function(panel) {
                    const header = panel.querySelector('.ssgsm-panel__header');
                    const content = panel.querySelector('.ssgsm-panel__content');
                    if (!header || !content) {
                        return;
                    }
                    if (panel.classList.contains('is-open')) {
                        header.setAttribute('aria-expanded', 'true');
                        content.hidden = false;
                    } else {
                        header.setAttribute('aria-expanded', 'false');
                        content.hidden = true;
                    }
                    header.addEventListener('click', function() {
                        const isOpen = panel.classList.toggle('is-open');
                        header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        content.hidden = !isOpen;
                    });
                });

            })();
        </script>
        <?php
    }
    public static function handleSave(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ssgs'));
        }
        check_admin_referer('ssgsm_save_settings');

        $raw = wp_unslash($_POST['settings'] ?? []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $projects = normalize_input_array($_POST['projects'] ?? []);
        $raw['projects'] = $projects;

        $settings = Plugin::getSettings();
        $merged   = array_merge($settings, $raw);
        $merged['projects'] = Support\normalize_projects($projects);
        $secondaryInput = $_POST['secondaries'] ?? [];
        if (!is_array($secondaryInput)) {
            $secondaryInput = [];
        }
        $merged['secondaries'] = Support\merge_secondary_input($secondaryInput, $settings['secondaries'] ?? []);

        $auth = $raw['auth'] ?? [];
        $merged['auth']['username'] = sanitize_text_field($auth['username'] ?? '');

        $newToken = isset($auth['token']) ? trim((string) $auth['token']) : '';
        $clearToken = !empty($auth['clear']);
        if ($clearToken) {
            $merged['auth']['token'] = '';
        } elseif ($newToken !== '') {
            $merged['auth']['token'] = Support\encrypt_secret($newToken);
        } else {
            $merged['auth']['token'] = $settings['auth']['token'] ?? '';
        }

        Plugin::saveSettings($merged);

        set_transient('ssgsm_notice', ['type' => 'success', 'text' => __('Settings saved.', 'ssgs')], 5);
        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
        exit;
    }

    private static function repoLink(string $repo): ?string {
        if ($repo === '') {
            return null;
        }

        if (preg_match('~git@([^:]+):(.+)~', $repo, $m)) {
            return sprintf('https://%s/%s', $m[1], ltrim($m[2], '/'));
        }

        if (preg_match('~https://~', $repo)) {
            return $repo;
        }

        return null;
    }

    public static function exportNow(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ssgs'));
        }
        check_admin_referer('ssgsm_export_now');

        try {
            (new Exporter())->exportAndPushAll();
            Support\update_last_export(time());
            set_transient('ssgsm_notice', ['type' => 'success', 'text' => __('Export completed.', 'ssgs')], 5);
        } catch (RuntimeException $e) {
            Logger::log('export', 'Manual export failed: ' . $e->getMessage(), 1);
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Export failed. Check logs.', 'ssgs')], 5);
        }

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
        exit;
    }

    public static function pushToken(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ssgs'));
        }
        check_admin_referer('ssgsm_push_token');

        $token = isset($_POST['token']) ? trim((string) $_POST['token']) : '';
        $targets = array_map('sanitize_text_field', (array) ($_POST['targets'] ?? []));
        $targets = array_values(array_filter($targets, static fn($value) => $value !== ''));

        if ($token === '' || empty($targets)) {
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Provide a token and select at least one site.', 'ssgs')], 5);
            wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
            exit;
        }

        try {
            $distributor = new Distributor(Plugin::getSettings());
            $report = $distributor->pushToken($token, $targets);
            $successMap = $report['success'] ?? [];
            $success = array_keys($successMap);
            $failed = $report['failed'] ?? [];
            $errors = $report['errors'] ?? [];

            $now = time();
            foreach ($successMap as $label => $messageText) {
                Support\record_secondary_status($label, [
                    'timestamp' => $now,
                    'action'    => 'token',
                    'status'    => 'success',
                    'message'   => $messageText !== '' ? $messageText : __('Token delivered.', 'ssgs'),
                ]);
            }
            foreach ($failed as $label => $messageText) {
                Support\record_secondary_status($label, [
                    'timestamp' => $now,
                    'action'    => 'token',
                    'status'    => 'error',
                    'message'   => $messageText,
                ]);
            }
            foreach ($errors as $errorMessage) {
                $labelMatch = null;
                if (preg_match('/Unknown secondary site:\s(.+)$/', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                } elseif (preg_match('/Missing URL for (.+?)\./', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                } elseif (preg_match('/No shared secret stored for (.+?)\./', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                }
                if ($labelMatch !== null) {
                    Support\record_secondary_status($labelMatch, [
                        'timestamp' => $now,
                        'action'    => 'token',
                        'status'    => 'error',
                        'message'   => $errorMessage,
                    ]);
                }
            }

            if (empty($errors) && empty($failed)) {
                $count = count($success);
                $text = sprintf(
                    _n('Token delivered to %d site.', 'Token delivered to %d sites.', $count, 'ssgs'),
                    $count
                );
                set_transient('ssgsm_notice', ['type' => 'success', 'text' => $text], 5);
            } else {
                $parts = [];
                if (!empty($failed)) {
                    $parts[] = sprintf(
                        __('Failed: %s.', 'ssgs'),
                        implode(', ', array_map('sanitize_text_field', array_keys($failed)))
                    );
                }
                if (!empty($errors)) {
                    $parts[] = implode(' ', array_unique(array_map('sanitize_text_field', $errors)));
                }
                if (!empty($success)) {
                    $parts[] = sprintf(
                        __('Delivered to: %s.', 'ssgs'),
                        implode(', ', array_map('sanitize_text_field', $success))
                    );
                }

                set_transient('ssgsm_notice', ['type' => 'error', 'text' => implode(' ', $parts)], 5);
            }
        } catch (RuntimeException $e) {
            Logger::log('distributor', 'Token push failed: ' . $e->getMessage(), 1);
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Token distribution failed. Check logs for details.', 'ssgs')], 5);
        }

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
        exit;
    }

    public static function remoteImport(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ssgs'));
        }
        check_admin_referer('ssgsm_remote_import');

        $targets = array_map('sanitize_text_field', (array) ($_POST['targets'] ?? []));
        $targets = array_values(array_filter($targets, static fn($value) => $value !== ''));

        if (empty($targets)) {
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Select at least one site to trigger the import.', 'ssgs')], 5);
            wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
            exit;
        }

        try {
            $runner = new RemoteImporter(Plugin::getSettings());
            $report = $runner->trigger($targets);
            $success = array_keys($report['success'] ?? []);
            $failed = $report['failed'] ?? [];
            $errors = $report['errors'] ?? [];

            $now = time();
            foreach ($report['success'] ?? [] as $label => $messageText) {
                Support\record_secondary_status($label, [
                    'timestamp' => $now,
                    'action'    => 'import',
                    'status'    => 'success',
                    'message'   => $messageText !== '' ? $messageText : __('Import completed.', 'ssgs'),
                ]);
            }
            foreach ($failed as $label => $messageText) {
                Support\record_secondary_status($label, [
                    'timestamp' => $now,
                    'action'    => 'import',
                    'status'    => 'error',
                    'message'   => $messageText,
                ]);
            }
            foreach ($errors as $errorMessage) {
                $labelMatch = null;
                if (preg_match('/Unknown secondary site:\s(.+)$/', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                } elseif (preg_match('/Missing URL for (.+?)\./', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                } elseif (preg_match('/No shared secret stored for (.+?)\./', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                }
                if ($labelMatch !== null) {
                    Support\record_secondary_status($labelMatch, [
                        'timestamp' => $now,
                        'action'    => 'import',
                        'status'    => 'error',
                        'message'   => $errorMessage,
                    ]);
                }
            }

            if (empty($errors) && empty($failed)) {
                $count = count($success);
                $text = sprintf(
                    _n('Import triggered on %d site.', 'Import triggered on %d sites.', $count, 'ssgs'),
                    $count
                );
                set_transient('ssgsm_notice', ['type' => 'success', 'text' => $text], 5);
            } else {
                $parts = [];
                if (!empty($failed)) {
                    $parts[] = sprintf(
                        __('Failed: %s.', 'ssgs'),
                        implode(', ', array_map('sanitize_text_field', array_keys($failed)))
                    );
                }
                if (!empty($errors)) {
                    $parts[] = implode(' ', array_unique(array_map('sanitize_text_field', $errors)));
                }
                if (!empty($success)) {
                    $parts[] = sprintf(
                        __('Triggered on: %s.', 'ssgs'),
                        implode(', ', array_map('sanitize_text_field', $success))
                    );
                }
                set_transient('ssgsm_notice', ['type' => 'error', 'text' => implode(' ', $parts)], 5);
            }
        } catch (RuntimeException $e) {
            Logger::log('distributor', 'Remote import trigger failed: ' . $e->getMessage(), 1);
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Remote import trigger failed. Check logs for details.', 'ssgs')], 5);
        }

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
        exit;
    }

    public static function remoteClearCache(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ssgs'));
        }
        check_admin_referer('ssgsm_remote_clear_cache');

        $targets = array_map('sanitize_text_field', (array) ($_POST['targets'] ?? []));
        $targets = array_values(array_filter($targets, static fn($value) => $value !== ''));

        if (empty($targets)) {
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Select at least one site to clear the cache.', 'ssgs')], 5);
            wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
            exit;
        }

        try {
            $clearer = new RemoteCacheClearer(Plugin::getSettings());
            $report = $clearer->trigger($targets);
            $success = array_keys($report['success'] ?? []);
            $failed = $report['failed'] ?? [];
            $errors = $report['errors'] ?? [];

            $now = time();
            foreach ($report['success'] ?? [] as $label => $messageText) {
                Support\record_secondary_status($label, [
                    'timestamp' => $now,
                    'action'    => 'cache',
                    'status'    => 'success',
                    'message'   => $messageText !== '' ? $messageText : __('Cache cleared.', 'ssgs'),
                ]);
            }
            foreach ($failed as $label => $messageText) {
                Support\record_secondary_status($label, [
                    'timestamp' => $now,
                    'action'    => 'cache',
                    'status'    => 'error',
                    'message'   => $messageText,
                ]);
            }
            foreach ($errors as $errorMessage) {
                $labelMatch = null;
                if (preg_match('/Unknown secondary site:\s(.+)$/', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                } elseif (preg_match('/Missing URL for (.+?)\./', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                } elseif (preg_match('/No shared secret stored for (.+?)\./', $errorMessage, $m)) {
                    $labelMatch = $m[1];
                }
                if ($labelMatch !== null) {
                    Support\record_secondary_status($labelMatch, [
                        'timestamp' => $now,
                        'action'    => 'cache',
                        'status'    => 'error',
                        'message'   => $errorMessage,
                    ]);
                }
            }

            if (empty($errors) && empty($failed)) {
                $count = count($success);
                $text = sprintf(
                    _n('Cache cleared on %d site.', 'Cache cleared on %d sites.', $count, 'ssgs'),
                    $count
                );
                set_transient('ssgsm_notice', ['type' => 'success', 'text' => $text], 5);
            } else {
                $parts = [];
                if (!empty($failed)) {
                    $parts[] = sprintf(
                        __('Failed: %s.', 'ssgs'),
                        implode(', ', array_map('sanitize_text_field', array_keys($failed)))
                    );
                }
                if (!empty($errors)) {
                    $parts[] = implode(' ', array_unique(array_map('sanitize_text_field', $errors)));
                }
                if (!empty($success)) {
                    $parts[] = sprintf(
                        __('Cleared for: %s.', 'ssgs'),
                        implode(', ', array_map('sanitize_text_field', $success))
                    );
                }
                set_transient('ssgsm_notice', ['type' => 'error', 'text' => implode(' ', $parts)], 5);
            }
        } catch (RuntimeException $e) {
            Logger::log('distributor', 'Remote cache clear failed: ' . $e->getMessage(), 1);
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Cache clear trigger failed. Check logs for details.', 'ssgs')], 5);
        }

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('admin.php?page=ssgs')));
        exit;
    }

    public static function renderLogs(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $logPath = WP_CONTENT_DIR . '/ss-git-sync.log';

        $perPageOptions = apply_filters('ssgsm_logs_per_page_options', [25, 50, 100, 200]);
        $perPageOptions = array_map('absint', (array) $perPageOptions);
        $perPageOptions = array_values(array_filter($perPageOptions, static fn($value) => $value > 0));
        if (empty($perPageOptions)) {
            $perPageOptions = [50];
        }

        $defaultPerPage = $perPageOptions[0];
        $perPage = isset($_GET['per_page']) ? absint($_GET['per_page']) : $defaultPerPage;
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = $defaultPerPage;
        }

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $channelFilter = isset($_GET['channel']) ? self::sanitizeChannel((string) $_GET['channel']) : '';
        $channelForQuery = $channelFilter !== '' ? $channelFilter : null;

        $offset = ($paged - 1) * $perPage;

        $entriesData = self::readLogEntries($logPath, $perPage, $offset, $channelForQuery);
        $entries = $entriesData['entries'];
        $hasMore = $entriesData['has_more'];
        // $entriesData['total'] reserved for future aggregate stats

        $channels = self::getLogChannels($logPath);
        if ($channelForQuery && !in_array($channelForQuery, $channels, true)) {
            $channels[] = $channelForQuery;
        }
        sort($channels, SORT_NATURAL | SORT_FLAG_CASE);

        $baseArgs = ['page' => 'ssgs-logs'];
        if ($channelForQuery) {
            $baseArgs['channel'] = $channelForQuery;
        }
        if ($perPage !== $defaultPerPage) {
            $baseArgs['per_page'] = $perPage;
        }

        $prevUrl = $paged > 1 ? add_query_arg(array_merge($baseArgs, ['paged' => $paged - 1])) : '';
        $nextUrl = $hasMore ? add_query_arg(array_merge($baseArgs, ['paged' => $paged + 1])) : '';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SS Git Sync Logs', 'ssgs'); ?></h1>
            <form method="get" style="margin-bottom:1rem;">
                <input type="hidden" name="page" value="ssgs-logs">
                <label for="ssgsm-log-channel"><?php esc_html_e('Channel', 'ssgs'); ?>:</label>
                <select name="channel" id="ssgsm-log-channel">
                    <option value=""><?php esc_html_e('All channels', 'ssgs'); ?></option>
                    <?php foreach ($channels as $channelOption): ?>
                        <option value="<?php echo esc_attr($channelOption); ?>" <?php selected($channelForQuery, $channelOption); ?>>
                            <?php echo esc_html($channelOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="ssgsm-log-per-page" style="margin-left:1rem;"><?php esc_html_e('Entries per page', 'ssgs'); ?>:</label>
                <select name="per_page" id="ssgsm-log-per-page">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($perPage, $option); ?>>
                            <?php echo esc_html($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Filter', 'ssgs'); ?></button>
            </form>
            <?php if (!file_exists($logPath) || !is_readable($logPath)): ?>
                <p><?php esc_html_e('Log file not found or not readable.', 'ssgs'); ?></p>
            <?php elseif (empty($entries)): ?>
                <p><?php esc_html_e('No log entries found for the selected filters.', 'ssgs'); ?></p>
            <?php else: ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'ssgs'); ?></th>
                            <th><?php esc_html_e('Channel', 'ssgs'); ?></th>
                            <th><?php esc_html_e('Code', 'ssgs'); ?></th>
                            <th><?php esc_html_e('Message', 'ssgs'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                            $timeIso = $entry['timestamp'];
                            $timeDisplay = $timeIso;
                            $humanDiff = '';
                            if ($timeIso !== '') {
                                $timestamp = strtotime($timeIso);
                                if ($timestamp !== false) {
                                    $local = get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), get_option('date_format') . ' ' . get_option('time_format'));
                                    if ($local) {
                                        $timeDisplay = $local;
                                    }
                                    $humanDiff = human_time_diff($timestamp, current_time('timestamp', true)) . ' ' . __('ago', 'ssgs');
                                }
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php if ($timeIso !== ''): ?>
                                        <time datetime="<?php echo esc_attr($timeIso); ?>">
                                            <?php echo esc_html($timeDisplay); ?>
                                        </time>
                                        <?php if ($humanDiff): ?>
                                            <br><span class="description"><?php echo esc_html($humanDiff); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php esc_html_e('Unknown', 'ssgs'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($entry['channel'] ?: __('General', 'ssgs')); ?></td>
                                <td><?php echo $entry['code'] !== null ? esc_html((string) $entry['code']) : '—'; ?></td>
                                <td><?php echo wp_kses_post(nl2br(esc_html($entry['message']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="tablenav" style="margin-top:1rem;">
                    <div class="tablenav-pages">
                        <?php if ($paged > 1): ?>
                            <a class="button" href="<?php echo esc_url($prevUrl); ?>">&laquo; <?php esc_html_e('Newer entries', 'ssgs'); ?></a>
                        <?php endif; ?>
                        <?php if ($hasMore): ?>
                            <a class="button" href="<?php echo esc_url($nextUrl); ?>"><?php esc_html_e('Older entries', 'ssgs'); ?> &raquo;</a>
                        <?php endif; ?>
                        <span class="tablenav-paging-text" style="margin-left:1rem;">
                            <?php
                            printf(
                                esc_html__('%1$d entries showing. Page %2$d.', 'ssgs'),
                                count($entries),
                                $paged
                            );
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function readLogEntries(string $path, int $limit, int $offset, ?string $channel): array {
        if (!file_exists($path) || !is_readable($path)) {
            return [
                'entries'  => [],
                'has_more' => false,
            ];
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $entries = [];
        $hasMore = false;
        $pendingLines = [];
        $matchedCount = 0;

        for ($line = $lastLine; $line >= 0; $line--) {
            $file->seek($line);
            $raw = trim($file->current());
            if ($raw === '') {
                continue;
            }

            $parsed = self::parseLogLine($raw);
            if (!$parsed['matched']) {
                array_unshift($pendingLines, $parsed['message']);
                continue;
            }

            if (!empty($pendingLines)) {
                $parsed['message'] .= "\n" . implode("\n", $pendingLines);
                $pendingLines = [];
            }

            $matchesChannel = $channel === null || $channel === '' || $parsed['channel'] === $channel;
            if ($matchesChannel && $matchedCount >= $offset && count($entries) < $limit) {
                $entries[] = $parsed;
            }

            if ($matchesChannel) {
                $matchedCount++;
            }

            if ($matchedCount > $offset + $limit) {
                $hasMore = true;
                break;
            }
        }

        return [
            'entries'  => $entries,
            'has_more' => $hasMore,
        ];
    }

    private static function parseLogLine(string $line): array {
        $pattern = '/^\[(?P<timestamp>.+?)\]\s+\[(?P<channel>.+?)\]\s+\((?P<code>-?\d+)\)\s(?P<message>.*)$/';
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches['timestamp'],
                'channel'   => $matches['channel'],
                'code'      => (int) $matches['code'],
                'message'   => $matches['message'],
                'matched'   => true,
            ];
        }

        return [
            'timestamp' => '',
            'channel'   => '',
            'code'      => null,
            'message'   => $line,
            'matched'   => false,
        ];
    }

    private static function getLogChannels(string $path): array {
        if (!file_exists($path) || !is_readable($path)) {
            return [];
        }

        $channels = [];
        $file = new \SplFileObject($path, 'r');
        foreach ($file as $line) {
            if (!is_string($line)) {
                continue;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parsed = self::parseLogLine($line);
            if ($parsed['matched'] && $parsed['channel'] !== '') {
                $channels[$parsed['channel']] = true;
            }
        }

        return array_keys($channels);
    }

    private static function sanitizeChannel(string $channel): string {
        $channel = sanitize_text_field($channel);
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $channel);
    }
}

function normalize_input_array($input): array {
    if (!is_array($input)) {
        return [];
    }

    $slugs = $input['slug'] ?? [];
    $files = $input['file'] ?? [];
    $count = max(count($slugs), count($files));
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $slug = sanitize_title($slugs[$i] ?? '');
        $file = sanitize_file_name($files[$i] ?? '');
        if ($slug === '' || $file === '') {
            continue;
        }
        $result[$slug] = $file;
    }

    return $result;
}
