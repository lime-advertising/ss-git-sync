<?php

namespace SSGSS;

use SSGSS\Support;
use SSGSS\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'addMenu']);
        add_action('admin_post_ssgss_save_settings', [__CLASS__, 'handleSave']);
        add_action('admin_post_ssgss_import_now', [__CLASS__, 'importNow']);
    }

    public static function addMenu(): void {
        add_options_page(
            __('SS Git Sync (Secondary)', 'ssgs'),
            __('SS Git Sync (Secondary)', 'ssgs'),
            'manage_options',
            'ssgss',
            [__CLASS__, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = Plugin::getSettings();
        $message  = get_transient('ssgss_notice');
        if ($message) {
            printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($message['type']), esc_html($message['text']));
            delete_transient('ssgss_notice');
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SS Git Sync (Secondary)', 'ssgs'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ssgss_save_settings'); ?>
                <input type="hidden" name="action" value="ssgss_save_settings">
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
                            <td><input type="text" class="regular-text" name="settings[exports]" value="<?php echo esc_attr($settings['exports']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Authentication', 'ssgs'); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="radio" name="settings[auth][mode]" value="ssh" <?php checked($settings['auth']['mode'], 'ssh'); ?>> <?php esc_html_e('SSH deploy key (recommended)', 'ssgs'); ?></label><br>
                                    <label><input type="radio" name="settings[auth][mode]" value="https-token" <?php checked($settings['auth']['mode'], 'https-token'); ?>> <?php esc_html_e('HTTPS + Personal Access Token', 'ssgs'); ?></label>
                                </fieldset>
                                <div id="ssgss-auth-token" <?php if ($settings['auth']['mode'] !== 'https-token') echo 'style="display:none"'; ?>>
                                    <p class="description"><?php esc_html_e('Paste a GitHub Personal Access Token that can read the repository. Leave the token field blank to keep the stored value.', 'ssgs'); ?></p>
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
                                <p class="description"><?php esc_html_e('Tokens are encrypted in the database. Choose HTTPS mode if you cannot manage SSH keys on this server.', 'ssgs'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Cron Frequency', 'ssgs'); ?></th>
                            <td>
                                <select name="settings[cron]">
                                    <?php
                                    $options = [
                                        'quarter-hourly' => __('Every 15 Minutes', 'ssgs'),
                                        'hourly'         => __('Hourly', 'ssgs'),
                                        'twicedaily'     => __('Every 12 Hours', 'ssgs'),
                                        'daily'          => __('Daily', 'ssgs'),
                                    ];
                                    foreach ($options as $value => $label) {
                                        printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($value), selected($settings['cron'], $value, false), esc_html($label));
                                    }
                                    ?>
                                </select>
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
                                    <tbody id="ssgss-project-rows">
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
                                <p><button class="button" type="button" id="ssgss-add-project"><?php esc_html_e('Add Project', 'ssgs'); ?></button></p>
                                <template id="ssgss-project-template"><tr><td><input type="text" name="projects[slug][]" placeholder="homepage_hero"></td><td><input type="text" name="projects[file][]" placeholder="homepage_hero.ss3"></td><td><button type="button" class="button" onclick="this.closest('tr').remove();"><?php esc_html_e('Remove', 'ssgs'); ?></button></td></tr></template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save Settings', 'ssgs')); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1rem;">
                <?php wp_nonce_field('ssgss_import_now'); ?>
                <input type="hidden" name="action" value="ssgss_import_now">
                <?php submit_button(__('Pull & Import Now', 'ssgs'), 'secondary', ''); ?>
            </form>
            <?php
            $lastImports = Support\get_last_imports();
            if (!empty($lastImports)) {
                echo '<h2>' . esc_html__('Last Import Timestamps', 'ssgs') . '</h2><ul>';
                foreach ($settings['projects'] as $slug => $filename) {
                    $slugSanitized = sanitize_title($slug);
                    echo '<li><strong>' . esc_html($slug) . '</strong>: ';
                    if (isset($lastImports[$slugSanitized])) {
                        $ts = $lastImports[$slugSanitized];
                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts));
                        echo ' (' . esc_html(human_time_diff($ts)) . ' ' . esc_html__('ago', 'ssgs') . ')';
                    } else {
                        esc_html_e('Never imported', 'ssgs');
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            ?>
        </div>
        <script>
            (function() {
                const btn = document.getElementById('ssgss-add-project');
                if (btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const tbody = document.getElementById('ssgss-project-rows');
                        const tpl = document.getElementById('ssgss-project-template');
                        if (tbody && tpl) {
                            tbody.insertAdjacentHTML('beforeend', tpl.innerHTML);
                        }
                    });
                }

                const authRadios = document.querySelectorAll('input[name="settings[auth][mode]"]');
                const tokenWrap = document.getElementById('ssgss-auth-token');
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
        check_admin_referer('ssgss_save_settings');

        $raw = wp_unslash($_POST['settings'] ?? []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $projects = normalize_input_array($_POST['projects'] ?? []);
        $raw['projects'] = $projects;

        $settings = Plugin::getSettings();
        $merged   = array_merge($settings, $raw);
        $merged['projects'] = Support\normalize_projects($projects);

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
        Cron::refreshSchedule();

        set_transient('ssgss_notice', ['type' => 'success', 'text' => __('Settings saved.', 'ssgs')], 5);
        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('options-general.php?page=ssgss')));
        exit;
    }

    public static function importNow(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ssgs'));
        }
        check_admin_referer('ssgss_import_now');

        try {
            (new Importer())->pullAndImportAll();
            set_transient('ssgss_notice', ['type' => 'success', 'text' => __('Pull & import completed.', 'ssgs')], 5);
        } catch (RuntimeException $e) {
            Logger::log('import', 'Manual import failed: ' . $e->getMessage(), 1);
            set_transient('ssgss_notice', ['type' => 'error', 'text' => __('Import failed. Check logs.', 'ssgs')], 5);
        }

        wp_safe_redirect(add_query_arg([], wp_get_referer() ?: admin_url('options-general.php?page=ssgss')));
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
