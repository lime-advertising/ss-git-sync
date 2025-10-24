<?php

namespace SSGSM;

use SSGSM\Support;
use SSGSM\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'addMenu']);
        add_action('admin_post_ssgsm_save_settings', [__CLASS__, 'handleSave']);
        add_action('admin_post_ssgsm_export_now', [__CLASS__, 'exportNow']);
        add_action('admin_post_ssgsm_push_token', [__CLASS__, 'pushToken']);
    }

    public static function addMenu(): void {
        add_options_page(
            __('SS Git Sync (Master)', 'ssgs'),
            __('SS Git Sync (Master)', 'ssgs'),
            'manage_options',
            'ssgsm',
            [__CLASS__, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = Plugin::getSettings();
        $message  = get_transient('ssgsm_notice');
        if ($message) {
            printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($message['type']), esc_html($message['text']));
            delete_transient('ssgsm_notice');
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SS Git Sync (Master)', 'ssgs'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ssgsm_save_settings'); ?>
                <input type="hidden" name="action" value="ssgsm_save_settings">
                <table class="form-table" role="presentation">
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
                            <td><input type="text" class="regular-text" name="settings[exports]" value="<?php echo esc_attr($settings['exports']); ?>">
                                <p class="description"><?php esc_html_e('Absolute path where exported .ss3 files are written.', 'ssgs'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Authentication', 'ssgs'); ?></th>
                            <td>
                         <fieldset>
                            <label><input type="radio" name="settings[auth][mode]" value="ssh" <?php checked($settings['auth']['mode'], 'ssh'); ?>> <?php esc_html_e('SSH deploy key (recommended)', 'ssgs'); ?></label><br>
                            <label><input type="radio" name="settings[auth][mode]" value="https-token" <?php checked($settings['auth']['mode'], 'https-token'); ?>> <?php esc_html_e('HTTPS + Personal Access Token', 'ssgs'); ?></label>
                        </fieldset>
                        <div id="ssgsm-auth-token" <?php if ($settings['auth']['mode'] !== 'https-token') echo 'style="display:none"'; ?>>
                                    <p class="description"><?php esc_html_e('Paste a GitHub Personal Access Token (with repo access). Leave the token field blank to keep the stored value.', 'ssgs'); ?></p>
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
                                        <p class="description"><?php esc_html_e('A token is currently stored. Tick the box below to remove it.', 'ssgs'); ?></p>
                                        <label><input type="checkbox" name="settings[auth][clear]" value="1"> <?php esc_html_e('Clear stored token on save', 'ssgs'); ?></label>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Use HTTPS tokens if you cannot manage SSH keys—tokens are encrypted and never displayed once saved.', 'ssgs'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Project Map', 'ssgs'); ?></th>
                            <td>
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
                                    $projects = $settings['projects'];
                                    if (empty($projects)) {
                                        $projects = ['' => ''];
                                    }
                                    foreach ($projects as $slug => $file) :
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
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Secondary Sites', 'ssgs'); ?></th>
                            <td>
                                <p class="description"><?php esc_html_e('List each downstream site that should receive tokens. Secrets are stored encrypted; leave the field blank to keep the existing value.', 'ssgs'); ?></p>
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
                                    $secondaries = $settings['secondaries'];
                                    if (empty($secondaries)) {
                                        $secondaries = [
                                            [
                                                'label'  => '',
                                                'url'    => '',
                                                'secret' => '',
                                            ],
                                        ];
                                    }
                                    foreach ($secondaries as $secondary) :
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
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save Settings', 'ssgs')); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1rem;">
                <?php wp_nonce_field('ssgsm_export_now'); ?>
                <input type="hidden" name="action" value="ssgsm_export_now">
                <?php submit_button(__('Export & Push Now', 'ssgs'), 'secondary', ''); ?>
            </form>
            <?php
            $last = Support\get_last_export();
            if ($last):
                printf('<p><em>%s %s (%s)</em></p>', esc_html__('Last export:', 'ssgs'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last)), human_time_diff($last));
            endif;
            $secondariesForPush = $settings['secondaries'];
            if (!empty($secondariesForPush)) :
                ?>
                <hr>
                <h2><?php esc_html_e('Distribute HTTPS Token', 'ssgs'); ?></h2>
                <p><?php esc_html_e('Paste a new Personal Access Token and select the sites that should receive it. Tokens are relayed immediately and are not stored on the master site.', 'ssgs'); ?></p>
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
                                    <?php foreach ($secondariesForPush as $secondary) :
                                        $label = $secondary['label'] ?: $secondary['url'];
                                        ?>
                                        <label style="display:block;margin-bottom:0.5rem;">
                                            <input type="checkbox" name="targets[]" value="<?php echo esc_attr($secondary['label']); ?>" <?php checked(true); ?>>
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
                <?php
            else :
                ?>
                <hr>
                <p><em><?php esc_html_e('Add at least one secondary site to enable token distribution.', 'ssgs'); ?></em></p>
                <?php
            endif;
            ?>
        </div>
        <script>
            (function() {
                const btn = document.getElementById('ssgsm-add-project');
                if (btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const tbody = document.getElementById('ssgsm-project-rows');
                        const tpl = document.getElementById('ssgsm-project-template');
                        if (tbody && tpl) {
                            tbody.insertAdjacentHTML('beforeend', tpl.innerHTML);
                        }
                    });
                }

                const secondaryBtn = document.getElementById('ssgsm-add-secondary');
                if (secondaryBtn) {
                    secondaryBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const tbody = document.getElementById('ssgsm-secondary-rows');
                        const tpl = document.getElementById('ssgsm-secondary-template');
                        if (tbody && tpl) {
                            tbody.insertAdjacentHTML('beforeend', tpl.innerHTML);
                        }
                    });
                }

                const authRadios = document.querySelectorAll('input[name="settings[auth][mode]"]');
                const tokenWrap = document.getElementById('ssgsm-auth-token');
                if (authRadios.length && tokenWrap) {
                    const toggle = () => {
                        const selected = document.querySelector('input[name="settings[auth][mode]"]:checked');
                        tokenWrap.style.display = selected && selected.value === 'https-token' ? '' : 'none';
                    };
                    authRadios.forEach(radio => radio.addEventListener('change', toggle));
                    toggle();
                }
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
        $mode = isset($auth['mode']) && $auth['mode'] === 'https-token' ? 'https-token' : 'ssh';
        $merged['auth']['mode'] = $mode;
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
        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('options-general.php?page=ssgsm')));
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

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('options-general.php?page=ssgsm')));
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
            wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('options-general.php?page=ssgsm')));
            exit;
        }

        try {
            $distributor = new Distributor(Plugin::getSettings());
            $report = $distributor->pushToken($token, $targets);
            if (!empty($report['errors'])) {
                $messages = array_unique(array_map('sanitize_text_field', $report['errors']));
                set_transient('ssgsm_notice', ['type' => 'error', 'text' => implode(' ', $messages)], 5);
            } else {
                $count = (int) ($report['queued'] ?? count($targets));
                set_transient('ssgsm_notice', ['type' => 'success', 'text' => sprintf(_n('Token distribution queued for %d site.', 'Token distribution queued for %d sites.', $count, 'ssgs'), $count)], 5);
            }
        } catch (RuntimeException $e) {
            Logger::log('distributor', 'Token push failed: ' . $e->getMessage(), 1);
            set_transient('ssgsm_notice', ['type' => 'error', 'text' => __('Token distribution failed. Check logs for details.', 'ssgs')], 5);
        }

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('options-general.php?page=ssgsm')));
        exit;
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
