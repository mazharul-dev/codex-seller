<?php
/**
 * Plugin Name: CodeX Seller
 * Plugin URI:  https://codexsell.com
 * Description: Sync purchased CodeX Seller products and update them directly from your WordPress admin.
 * Version:     1.1.0
 * Author:      CodeX Seller
 * Author URI:  https://codexsell.com
 * Text Domain: codex-seller
 */

if (! defined('ABSPATH')) {
    exit;
}

final class CodeX_Seller_Plugin
{
    public const VERSION = '1.1.0';
    public const FIXED_API_BASE_URL = 'https://codexsell.com/';
    public const OPTION_KEY = 'codex_seller_api_base_url';
    public const OPTION_EMAIL = 'codex_seller_user_email';
    public const OPTION_PASSWORD = 'codex_seller_user_password';
    public const OPTION_AUTO_UPDATES = 'codex_seller_auto_updates';
    public const OPTION_BACKUP = 'codex_seller_backup_before_update';
    public const OPTION_HEALTH = 'codex_seller_health_check';
    public const OPTION_EMAIL_REPORTS = 'codex_seller_email_reports';
    public const OPTION_REPORT_EMAIL = 'codex_seller_report_email';
    public const OPTION_UPDATE_PLUGINS = 'codex_seller_update_plugins';
    public const OPTION_UPDATE_THEMES = 'codex_seller_update_themes';
    public const OPTION_FREQUENCY = 'codex_seller_update_frequency';
    public const OPTION_LAST_RUN = 'codex_seller_last_run';
    public const OPTION_LAST_REPORT = 'codex_seller_last_report';
    public const OPTION_LAST_SUMMARY = 'codex_seller_last_summary';
    public const NONCE_ACTION = 'codex_seller_admin_action';
    public const CRON_HOOK = 'codex_seller_auto_update_event';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_codex_seller_login', [$this, 'handle_login']);
        add_action('admin_post_codex_seller_save_settings', [$this, 'handle_save_settings']);
        add_action('wp_ajax_codex_seller_fetch_products', [$this, 'ajax_fetch_products']);
        add_action('wp_ajax_codex_seller_update_product', [$this, 'ajax_update_product']);
        add_action('wp_ajax_codex_seller_run_now', [$this, 'ajax_run_now']);
        add_action('wp_ajax_codex_seller_health_check', [$this, 'ajax_health_check']);
        add_action('wp_ajax_codex_seller_rollback_latest', [$this, 'ajax_rollback_latest']);
        add_action(self::CRON_HOOK, [$this, 'run_scheduled_updates']);
    }

    public static function activate(): void
    {
        self::ensure_default_options();

        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function ensure_default_options(): void
    {
        $defaults = [
            self::OPTION_KEY => self::FIXED_API_BASE_URL,
            self::OPTION_AUTO_UPDATES => '1',
            self::OPTION_BACKUP => '1',
            self::OPTION_HEALTH => '1',
            self::OPTION_EMAIL_REPORTS => '0',
            self::OPTION_REPORT_EMAIL => get_option('admin_email', ''),
            self::OPTION_UPDATE_PLUGINS => '1',
            self::OPTION_UPDATE_THEMES => '1',
            self::OPTION_FREQUENCY => 'daily',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key, null) === null) {
                update_option($key, $value);
            }
        }
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'CodeX Seller',
            'CodeX Seller',
            'manage_options',
            'codex-seller',
            [$this, 'render_admin_page'],
            'dashicons-update-alt',
            56
        );
    }

    public function register_settings(): void
    {
        register_setting('codex_seller_settings', self::OPTION_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => self::FIXED_API_BASE_URL,
        ]);

        register_setting('codex_seller_settings', self::OPTION_EMAIL, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => '',
        ]);

        register_setting('codex_seller_settings', self::OPTION_PASSWORD, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting('codex_seller_settings', self::OPTION_AUTO_UPDATES, [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_boolean_option'],
            'default' => true,
        ]);

        register_setting('codex_seller_settings', self::OPTION_BACKUP, [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_boolean_option'],
            'default' => true,
        ]);

        register_setting('codex_seller_settings', self::OPTION_HEALTH, [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_boolean_option'],
            'default' => true,
        ]);

        register_setting('codex_seller_settings', self::OPTION_EMAIL_REPORTS, [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_boolean_option'],
            'default' => false,
        ]);

        register_setting('codex_seller_settings', self::OPTION_REPORT_EMAIL, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email', ''),
        ]);

        register_setting('codex_seller_settings', self::OPTION_UPDATE_PLUGINS, [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_boolean_option'],
            'default' => true,
        ]);

        register_setting('codex_seller_settings', self::OPTION_UPDATE_THEMES, [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_boolean_option'],
            'default' => true,
        ]);

        register_setting('codex_seller_settings', self::OPTION_FREQUENCY, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_frequency'],
            'default' => 'daily',
        ]);
    }

    public function sanitize_boolean_option($value): string
    {
        return empty($value) ? '0' : '1';
    }

    public function sanitize_frequency($value): string
    {
        $value = sanitize_key($value);
        $allowed = ['hourly', 'twicedaily', 'daily'];

        return in_array($value, $allowed, true) ? $value : 'daily';
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_codex-seller') {
            return;
        }

        wp_enqueue_style('codex-seller-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
        wp_enqueue_script('codex-seller-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], self::VERSION, true);
        wp_localize_script('codex-seller-admin', 'CodexSeller', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'strings' => [
                'fetchProducts' => __('Fetch Products', 'codex-seller'),
                'loading' => __('Loading...', 'codex-seller'),
                'runNow' => __('Run Now', 'codex-seller'),
                'running' => __('Running...', 'codex-seller'),
                'update' => __('Update', 'codex-seller'),
                'install' => __('Install', 'codex-seller'),
                'updating' => __('Updating...', 'codex-seller'),
                'rollback' => __('Rollback Now', 'codex-seller'),
                'rollbackConfirm' => __('Restore the latest CodeX Seller backup now?', 'codex-seller'),
                'rollbacking' => __('Restoring...', 'codex-seller'),
            ],
        ]);
    }

    public function render_admin_page(): void
    {
        self::ensure_default_options();

        $activeView = $this->get_admin_view();
        $settings = $this->get_settings();
        $health = $this->run_health_check();
        $latestBackup = $this->get_latest_backup();
        $backups = $this->get_backups(10);
        $lastSummary = get_option(self::OPTION_LAST_SUMMARY, []);
        $lastRun = (int) get_option(self::OPTION_LAST_RUN, 0);
        $lastReport = (int) get_option(self::OPTION_LAST_REPORT, 0);
        $nextRun = wp_next_scheduled(self::CRON_HOOK);
        $loggedIn = ! empty($settings['api_url']) && ! empty($settings['email']) && ! empty($settings['password']);
        $statusLabel = $loggedIn && $health['passed'] ? __('Everything is up to date', 'codex-seller') : __('Needs attention', 'codex-seller');
        $statusClass = $loggedIn && $health['passed'] ? 'is-good' : 'is-warning';
        $updatedCount = isset($lastSummary['updated']) ? (int) $lastSummary['updated'] : 0;
        $checkedCount = isset($lastSummary['checked']) ? (int) $lastSummary['checked'] : 0;
        $navItems = [
            'dashboard' => ['label' => __('Dashboard', 'codex-seller'), 'icon' => 'dashicons-dashboard'],
            'updates' => ['label' => __('Updates', 'codex-seller'), 'icon' => 'dashicons-shield', 'badge' => (string) $checkedCount],
            'backups' => ['label' => __('Backups', 'codex-seller'), 'icon' => 'dashicons-backup'],
            'reports' => ['label' => __('Reports', 'codex-seller'), 'icon' => 'dashicons-email-alt'],
            'settings' => ['label' => __('Settings', 'codex-seller'), 'icon' => 'dashicons-admin-generic'],
        ];
        ?>
        <div class="wrap codex-seller-wrap">
            <?php if (isset($_GET['codex_seller_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('CodeX Seller settings saved.', 'codex-seller'); ?></p></div>
            <?php endif; ?>

            <div class="codex-seller-app">
                <aside class="codex-seller-sidebar" aria-label="<?php esc_attr_e('CodeX Seller navigation', 'codex-seller'); ?>">
                    <div class="codex-seller-brand">
                        <span class="codex-seller-logo"><span class="dashicons dashicons-update-alt"></span></span>
                        <strong>CodeX Seller</strong>
                    </div>
                    <?php foreach ($navItems as $view => $item): ?>
                        <a class="codex-seller-nav <?php echo esc_attr($activeView === $view ? 'is-active' : ''); ?>" href="<?php echo esc_url($this->get_view_url($view)); ?>">
                            <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                            <span><?php echo esc_html($item['label']); ?></span>
                            <?php if (isset($item['badge'])): ?>
                                <span class="codex-seller-badge"><?php echo esc_html($item['badge']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </aside>

                <main class="codex-seller-main">
                    <?php if ($activeView === 'dashboard'): ?>
                        <section id="codex-seller-dashboard" class="codex-seller-hero">
                            <div>
                                <p class="codex-seller-eyebrow"><?php esc_html_e('Smart. Safe. Automatic.', 'codex-seller'); ?></p>
                                <h1><?php esc_html_e('Auto Updater Dashboard', 'codex-seller'); ?></h1>
                                <p><?php esc_html_e('Keep purchased CodeX Seller plugins and themes updated with backups, checks, rollback, and email reports.', 'codex-seller'); ?></p>
                            </div>
                            <div class="codex-seller-status-strip">
                                <span class="codex-seller-pill <?php echo esc_attr($statusClass); ?>"><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html($statusLabel); ?></span>
                                <span class="codex-seller-next-run"><span class="dashicons dashicons-calendar-alt"></span><strong><?php esc_html_e('Next Run', 'codex-seller'); ?></strong><?php echo esc_html($this->format_schedule_time($nextRun, $settings['auto_updates'])); ?></span>
                            </div>
                        </section>

                        <section class="codex-seller-feature-grid" aria-label="<?php esc_attr_e('CodeX Seller feature summary', 'codex-seller'); ?>">
                            <div class="codex-seller-feature">
                                <span class="codex-seller-feature-icon is-blue"><span class="dashicons dashicons-update-alt"></span></span>
                                <strong><?php esc_html_e('Auto Updates', 'codex-seller'); ?></strong>
                                <span><?php echo esc_html($settings['auto_updates'] ? __('Set and forget', 'codex-seller') : __('Paused', 'codex-seller')); ?></span>
                            </div>
                            <div class="codex-seller-feature">
                                <span class="codex-seller-feature-icon is-green"><span class="dashicons dashicons-shield-alt"></span></span>
                                <strong><?php esc_html_e('Backup & Rollback', 'codex-seller'); ?></strong>
                                <span><?php echo esc_html($settings['backup'] ? __('One-click restore', 'codex-seller') : __('Backup disabled', 'codex-seller')); ?></span>
                            </div>
                            <div class="codex-seller-feature">
                                <span class="codex-seller-feature-icon is-purple"><span class="dashicons dashicons-email"></span></span>
                                <strong><?php esc_html_e('Email Reports', 'codex-seller'); ?></strong>
                                <span><?php echo esc_html($settings['email_reports'] ? __('Stay informed', 'codex-seller') : __('Off', 'codex-seller')); ?></span>
                            </div>
                        </section>

                        <section class="codex-seller-flow" aria-label="<?php esc_attr_e('Safe update flow', 'codex-seller'); ?>">
                            <div><span class="dashicons dashicons-database"></span><strong><?php esc_html_e('Backup', 'codex-seller'); ?></strong><small><?php esc_html_e('Before update', 'codex-seller'); ?></small></div>
                            <div><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                            <div><span class="dashicons dashicons-heart"></span><strong><?php esc_html_e('Health Check', 'codex-seller'); ?></strong><small><?php esc_html_e('Scan and verify', 'codex-seller'); ?></small></div>
                            <div><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                            <div><span class="dashicons dashicons-update"></span><strong><?php esc_html_e('Update', 'codex-seller'); ?></strong><small><?php esc_html_e('Automatically', 'codex-seller'); ?></small></div>
                            <div><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                            <div><span class="dashicons dashicons-email-alt"></span><strong><?php esc_html_e('Email Report', 'codex-seller'); ?></strong><small><?php esc_html_e('Summary sent', 'codex-seller'); ?></small></div>
                            <div><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                            <div><span class="dashicons dashicons-undo"></span><strong><?php esc_html_e('Rollback', 'codex-seller'); ?></strong><small><?php esc_html_e('If anything fails', 'codex-seller'); ?></small></div>
                        </section>

                        <section class="codex-seller-card codex-seller-report-card">
                            <div class="codex-seller-report-grid">
                                <div><span><?php esc_html_e('Products Checked', 'codex-seller'); ?></span><strong><?php echo esc_html((string) $checkedCount); ?></strong></div>
                                <div><span><?php esc_html_e('Updated', 'codex-seller'); ?></span><strong><?php echo esc_html((string) $updatedCount); ?></strong></div>
                                <div><span><?php esc_html_e('Last Run', 'codex-seller'); ?></span><strong><?php echo esc_html($lastRun ? $this->format_timestamp($lastRun) : __('Never', 'codex-seller')); ?></strong></div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($activeView === 'updates'): ?>
                        <div class="codex-seller-grid">
                            <section id="codex-seller-updates" class="codex-seller-card codex-seller-updates-card">
                                <div class="codex-seller-card-header">
                                    <div>
                                        <h2><?php esc_html_e('Updates', 'codex-seller'); ?></h2>
                                        <p><?php esc_html_e('Fetch your purchased products, review versions, and install updates safely.', 'codex-seller'); ?></p>
                                    </div>
                                    <div class="codex-seller-actions">
                                        <button id="codex-seller-fetch-products" class="button button-secondary" type="button"><?php esc_html_e('Fetch Products', 'codex-seller'); ?></button>
                                        <button id="codex-seller-run-now" class="button button-primary" type="button" <?php disabled(! $loggedIn); ?>><?php esc_html_e('Run Now', 'codex-seller'); ?></button>
                                    </div>
                                </div>

                                <div class="codex-seller-tabs" role="tablist" aria-label="<?php esc_attr_e('Product filters', 'codex-seller'); ?>">
                                    <button type="button" class="is-active" data-filter="all"><?php esc_html_e('All', 'codex-seller'); ?></button>
                                    <button type="button" data-filter="plugin"><?php esc_html_e('Plugins', 'codex-seller'); ?></button>
                                    <button type="button" data-filter="theme"><?php esc_html_e('Themes', 'codex-seller'); ?></button>
                                </div>

                                <?php if (! $loggedIn): ?>
                                    <div class="codex-seller-empty"><?php esc_html_e('Save your CodeX Seller email and password before fetching products.', 'codex-seller'); ?></div>
                                <?php endif; ?>
                                <div id="codex-seller-products" class="codex-seller-products"></div>
                                <div id="codex-seller-run-output" class="codex-seller-inline-output" aria-live="polite"></div>
                            </section>

                            <aside class="codex-seller-side">
                                <section class="codex-seller-card codex-seller-safety-card">
                                    <div class="codex-seller-card-header compact">
                                        <h2><?php esc_html_e('Safety Net', 'codex-seller'); ?></h2>
                                        <button id="codex-seller-health-check" class="button button-small" type="button"><?php esc_html_e('Check', 'codex-seller'); ?></button>
                                    </div>
                                    <ul id="codex-seller-health-list" class="codex-seller-check-list">
                                        <?php foreach ($health['checks'] as $check): ?>
                                            <li class="<?php echo esc_attr('is-' . $check['status']); ?>">
                                                <span class="dashicons <?php echo esc_attr($check['status'] === 'failed' ? 'dashicons-warning' : 'dashicons-yes-alt'); ?>"></span>
                                                <span><?php echo esc_html($check['label']); ?></span>
                                                <strong><?php echo esc_html($check['message']); ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            </aside>
                        </div>
                    <?php endif; ?>

                    <?php if ($activeView === 'backups'): ?>
                        <section id="codex-seller-backups" class="codex-seller-card codex-seller-rollback-card">
                            <div class="codex-seller-card-header">
                                <div>
                                    <h2><?php esc_html_e('Backups & Rollback', 'codex-seller'); ?></h2>
                                    <p><?php esc_html_e('Restore the latest backup or review recent rollback points.', 'codex-seller'); ?></p>
                                </div>
                            </div>
                            <?php if ($latestBackup): ?>
                                <p><?php esc_html_e('Last backup:', 'codex-seller'); ?> <strong><?php echo esc_html($this->format_timestamp((int) $latestBackup['created'])); ?></strong></p>
                                <p class="codex-seller-muted"><?php echo esc_html($latestBackup['name']); ?> <?php echo esc_html($latestBackup['version'] ? '(' . $latestBackup['version'] . ')' : ''); ?></p>
                                <button id="codex-seller-rollback-latest" class="button button-secondary" type="button"><?php esc_html_e('Rollback Now', 'codex-seller'); ?></button>
                            <?php else: ?>
                                <div class="codex-seller-empty"><?php esc_html_e('No backups have been created yet.', 'codex-seller'); ?></div>
                                <button class="button button-secondary" type="button" disabled><?php esc_html_e('Rollback Now', 'codex-seller'); ?></button>
                            <?php endif; ?>
                            <div id="codex-seller-rollback-output" class="codex-seller-inline-output" aria-live="polite"></div>

                            <div class="codex-seller-backup-list">
                                <h3><?php esc_html_e('Recent Backups', 'codex-seller'); ?></h3>
                                <?php if ($backups): ?>
                                    <?php foreach ($backups as $backup): ?>
                                        <div class="codex-seller-backup-row">
                                            <strong><?php echo esc_html($backup['name']); ?></strong>
                                            <span><?php echo esc_html(ucfirst($backup['type'] ?: __('Package', 'codex-seller'))); ?></span>
                                            <span><?php echo esc_html($backup['version'] ?: __('Version unknown', 'codex-seller')); ?></span>
                                            <span><?php echo esc_html($this->format_timestamp((int) $backup['created'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="codex-seller-empty"><?php esc_html_e('Backup history is empty.', 'codex-seller'); ?></div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($activeView === 'reports'): ?>
                        <section id="codex-seller-reports" class="codex-seller-card codex-seller-report-card">
                            <div class="codex-seller-card-header">
                                <div>
                                    <h2><?php esc_html_e('Reports', 'codex-seller'); ?></h2>
                                    <p><?php esc_html_e('Recent automation activity and notification status.', 'codex-seller'); ?></p>
                                </div>
                            </div>
                            <div class="codex-seller-report-grid">
                                <div><span><?php esc_html_e('Last Run', 'codex-seller'); ?></span><strong><?php echo esc_html($lastRun ? $this->format_timestamp($lastRun) : __('Never', 'codex-seller')); ?></strong></div>
                                <div><span><?php esc_html_e('Products Checked', 'codex-seller'); ?></span><strong><?php echo esc_html((string) $checkedCount); ?></strong></div>
                                <div><span><?php esc_html_e('Updated', 'codex-seller'); ?></span><strong><?php echo esc_html((string) $updatedCount); ?></strong></div>
                                <div><span><?php esc_html_e('Last Email', 'codex-seller'); ?></span><strong><?php echo esc_html($lastReport ? $this->format_timestamp($lastReport) : __('Not sent', 'codex-seller')); ?></strong></div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($activeView === 'settings'): ?>
                        <section id="codex-seller-settings" class="codex-seller-card codex-seller-settings-card">
                            <div class="codex-seller-card-header">
                                <div>
                                    <h2><?php esc_html_e('Settings', 'codex-seller'); ?></h2>
                                    <p><?php esc_html_e('Connect your CodeX Seller account and choose how automation runs.', 'codex-seller'); ?></p>
                                </div>
                            </div>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                                <input type="hidden" name="action" value="codex_seller_save_settings">
                                <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>" value="<?php echo esc_attr(self::FIXED_API_BASE_URL); ?>">

                                <div class="codex-seller-settings-grid">
                                    <label class="codex-seller-fixed-field">
                                        <span><?php esc_html_e('API Base URL', 'codex-seller'); ?></span>
                                        <a href="<?php echo esc_url(self::FIXED_API_BASE_URL); ?>" target="_blank" rel="noreferrer"><?php echo esc_html(self::FIXED_API_BASE_URL); ?></a>
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Email', 'codex-seller'); ?></span>
                                        <input type="email" name="<?php echo esc_attr(self::OPTION_EMAIL); ?>" value="<?php echo esc_attr($settings['email']); ?>" class="regular-text" required>
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Password', 'codex-seller'); ?></span>
                                        <input type="password" name="<?php echo esc_attr(self::OPTION_PASSWORD); ?>" value="<?php echo esc_attr($settings['password']); ?>" class="regular-text" required>
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Report Email', 'codex-seller'); ?></span>
                                        <input type="email" name="<?php echo esc_attr(self::OPTION_REPORT_EMAIL); ?>" value="<?php echo esc_attr($settings['report_email']); ?>" class="regular-text">
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Schedule', 'codex-seller'); ?></span>
                                        <select name="<?php echo esc_attr(self::OPTION_FREQUENCY); ?>">
                                            <option value="hourly" <?php selected($settings['frequency'], 'hourly'); ?>><?php esc_html_e('Hourly', 'codex-seller'); ?></option>
                                            <option value="twicedaily" <?php selected($settings['frequency'], 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'codex-seller'); ?></option>
                                            <option value="daily" <?php selected($settings['frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'codex-seller'); ?></option>
                                        </select>
                                    </label>
                                </div>

                                <div class="codex-seller-toggle-grid">
                                    <?php $this->render_toggle(self::OPTION_AUTO_UPDATES, __('Auto updates', 'codex-seller'), __('Run updates on the selected schedule.', 'codex-seller'), $settings['auto_updates']); ?>
                                    <?php $this->render_toggle(self::OPTION_BACKUP, __('Backup before update', 'codex-seller'), __('Create a rollback point before installing updates.', 'codex-seller'), $settings['backup']); ?>
                                    <?php $this->render_toggle(self::OPTION_HEALTH, __('Health check', 'codex-seller'), __('Verify credentials, storage, and filesystem access first.', 'codex-seller'), $settings['health']); ?>
                                    <?php $this->render_toggle(self::OPTION_EMAIL_REPORTS, __('Email reports', 'codex-seller'), __('Send update summaries to the report email.', 'codex-seller'), $settings['email_reports']); ?>
                                    <?php $this->render_toggle(self::OPTION_UPDATE_PLUGINS, __('Update plugins', 'codex-seller'), __('Include purchased plugins in automation.', 'codex-seller'), $settings['update_plugins']); ?>
                                    <?php $this->render_toggle(self::OPTION_UPDATE_THEMES, __('Update themes', 'codex-seller'), __('Include purchased themes in automation.', 'codex-seller'), $settings['update_themes']); ?>
                                </div>

                                <?php submit_button(__('Save Settings', 'codex-seller')); ?>
                            </form>
                        </section>
                    <?php endif; ?>
                </main>
            </div>
        </div>
        <?php
    }

    private function get_admin_view(): string
    {
        $view = sanitize_key(wp_unslash($_GET['view'] ?? 'dashboard'));
        $allowed = ['dashboard', 'updates', 'backups', 'reports', 'settings'];

        return in_array($view, $allowed, true) ? $view : 'dashboard';
    }

    private function get_view_url(string $view): string
    {
        return add_query_arg([
            'page' => 'codex-seller',
            'view' => $view,
        ], admin_url('admin.php'));
    }

    private function render_toggle(string $name, string $title, string $description, bool $checked): void
    {
        ?>
        <label class="codex-seller-toggle">
            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($checked); ?>>
            <span aria-hidden="true"></span>
            <strong><?php echo esc_html($title); ?></strong>
            <small><?php echo esc_html($description); ?></small>
        </label>
        <?php
    }

    public function handle_login(): void
    {
        $this->handle_save_settings();
    }

    public function handle_save_settings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized user', 'codex-seller'));
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Invalid nonce', 'codex-seller'));
        }

        update_option(self::OPTION_KEY, self::FIXED_API_BASE_URL);
        update_option(self::OPTION_EMAIL, sanitize_email(wp_unslash($_POST[self::OPTION_EMAIL] ?? '')));
        update_option(self::OPTION_PASSWORD, sanitize_text_field(wp_unslash($_POST[self::OPTION_PASSWORD] ?? '')));
        update_option(self::OPTION_REPORT_EMAIL, sanitize_email(wp_unslash($_POST[self::OPTION_REPORT_EMAIL] ?? get_option('admin_email', ''))));
        update_option(self::OPTION_FREQUENCY, $this->sanitize_frequency(wp_unslash($_POST[self::OPTION_FREQUENCY] ?? 'daily')));

        $booleanOptions = [
            self::OPTION_AUTO_UPDATES,
            self::OPTION_BACKUP,
            self::OPTION_HEALTH,
            self::OPTION_EMAIL_REPORTS,
            self::OPTION_UPDATE_PLUGINS,
            self::OPTION_UPDATE_THEMES,
        ];

        foreach ($booleanOptions as $option) {
            update_option($option, isset($_POST[$option]) ? '1' : '0');
        }

        $this->sync_cron_schedule();

        wp_redirect(add_query_arg([
            'codex_seller_saved' => '1',
            'view' => 'settings',
        ], admin_url('admin.php?page=codex-seller')));
        exit;
    }

    public function ajax_fetch_products(): void
    {
        $this->guard_ajax_request();

        $products = $this->fetch_products_with_install_data();

        if (is_wp_error($products)) {
            wp_send_json_error(['message' => $products->get_error_message()], 400);
        }

        wp_send_json_success(['products' => $products]);
    }

    public function ajax_update_product(): void
    {
        $this->guard_ajax_request();

        $downloadUrl = esc_url_raw(wp_unslash($_POST['download_url'] ?? ''));
        $productName = sanitize_text_field(wp_unslash($_POST['product_name'] ?? 'codex-seller-product'));
        $slug = sanitize_title(wp_unslash($_POST['slug'] ?? $productName));
        $currentVersion = sanitize_text_field(wp_unslash($_POST['current_version'] ?? ''));
        $packageType = sanitize_key(wp_unslash($_POST['package_type'] ?? ''));

        if (empty($downloadUrl)) {
            wp_send_json_error(['message' => __('Missing download URL.', 'codex-seller')], 400);
        }

        $health = $this->run_health_check();
        if ($this->get_bool_option(self::OPTION_HEALTH, true) && ! $health['passed']) {
            wp_send_json_error([
                'message' => __('Health check failed. Fix the Safety Net items before updating.', 'codex-seller'),
                'health' => $health,
            ], 400);
        }

        $result = $this->install_product_update([
            'name' => $productName,
            'slug' => $slug,
            'current_version' => $currentVersion,
            'download_url' => $downloadUrl,
            'package_type' => $packageType,
        ], 'manual');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        $summary = [
            'mode' => 'manual',
            'checked' => 1,
            'updated' => 1,
            'skipped' => 0,
            'failed' => 0,
            'items' => [
                [
                    'name' => $productName,
                    'status' => 'updated',
                    'message' => $result['message'],
                ],
            ],
        ];
        update_option(self::OPTION_LAST_RUN, time());
        update_option(self::OPTION_LAST_SUMMARY, $summary);
        $this->maybe_send_report($summary);

        wp_send_json_success($result);
    }

    public function ajax_run_now(): void
    {
        $this->guard_ajax_request();

        $summary = $this->run_update_cycle('manual');

        if (is_wp_error($summary)) {
            $data = ['message' => $summary->get_error_message()];
            $errorData = $summary->get_error_data();

            if (is_array($errorData)) {
                $data = array_merge($data, $errorData);
            }

            wp_send_json_error($data, 400);
        }

        wp_send_json_success(['summary' => $summary]);
    }

    public function ajax_health_check(): void
    {
        $this->guard_ajax_request();

        wp_send_json_success(['health' => $this->run_health_check()]);
    }

    public function ajax_rollback_latest(): void
    {
        $this->guard_ajax_request();

        $result = $this->restore_latest_backup();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        wp_send_json_success($result);
    }

    public function run_scheduled_updates(): void
    {
        if (! $this->get_bool_option(self::OPTION_AUTO_UPDATES, true)) {
            return;
        }

        $this->run_update_cycle('scheduled');
    }

    private function guard_ajax_request(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized user.', 'codex-seller')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    private function get_settings(): array
    {
        return [
            'api_url' => self::FIXED_API_BASE_URL,
            'email' => get_option(self::OPTION_EMAIL, ''),
            'password' => get_option(self::OPTION_PASSWORD, ''),
            'auto_updates' => $this->get_bool_option(self::OPTION_AUTO_UPDATES, true),
            'backup' => $this->get_bool_option(self::OPTION_BACKUP, true),
            'health' => $this->get_bool_option(self::OPTION_HEALTH, true),
            'email_reports' => $this->get_bool_option(self::OPTION_EMAIL_REPORTS, false),
            'report_email' => get_option(self::OPTION_REPORT_EMAIL, get_option('admin_email', '')),
            'update_plugins' => $this->get_bool_option(self::OPTION_UPDATE_PLUGINS, true),
            'update_themes' => $this->get_bool_option(self::OPTION_UPDATE_THEMES, true),
            'frequency' => $this->sanitize_frequency(get_option(self::OPTION_FREQUENCY, 'daily')),
        ];
    }

    private function get_bool_option(string $option, bool $default): bool
    {
        $value = get_option($option, $default ? '1' : '0');

        return $value === true || $value === '1' || $value === 1;
    }

    private function sync_cron_schedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);

        if (! $this->get_bool_option(self::OPTION_AUTO_UPDATES, true)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, $this->sanitize_frequency(get_option(self::OPTION_FREQUENCY, 'daily')), self::CRON_HOOK);
    }

    private function run_health_check(): array
    {
        $settings = $this->get_settings();
        $uploadDir = wp_upload_dir();
        $uploadsReady = empty($uploadDir['error']) && ! empty($uploadDir['basedir']) && is_dir($uploadDir['basedir']) && is_writable($uploadDir['basedir']);
        $fileModsAllowed = ! defined('DISALLOW_FILE_MODS') || ! DISALLOW_FILE_MODS;
        $zipReady = class_exists('ZipArchive');
        $hasCredentials = ! empty($settings['api_url']) && ! empty($settings['email']) && ! empty($settings['password']);
        $cronReady = ! $settings['auto_updates'] || (bool) wp_next_scheduled(self::CRON_HOOK);

        $checks = [
            [
                'key' => 'credentials',
                'label' => __('Account connection', 'codex-seller'),
                'status' => $hasCredentials ? 'passed' : 'failed',
                'message' => $hasCredentials ? __('Complete', 'codex-seller') : __('Required', 'codex-seller'),
            ],
            [
                'key' => 'backup',
                'label' => __('Create Backup', 'codex-seller'),
                'status' => ($uploadsReady && (! $settings['backup'] || $zipReady)) ? 'passed' : 'failed',
                'message' => ($uploadsReady && (! $settings['backup'] || $zipReady)) ? __('Complete', 'codex-seller') : __('Blocked', 'codex-seller'),
            ],
            [
                'key' => 'filesystem',
                'label' => __('Safe Update', 'codex-seller'),
                'status' => $fileModsAllowed ? 'passed' : 'failed',
                'message' => $fileModsAllowed ? __('Complete', 'codex-seller') : __('Disabled', 'codex-seller'),
            ],
            [
                'key' => 'cron',
                'label' => __('Automation Schedule', 'codex-seller'),
                'status' => $cronReady ? 'passed' : 'warning',
                'message' => $cronReady ? __('Ready', 'codex-seller') : __('Needs save', 'codex-seller'),
            ],
            [
                'key' => 'email',
                'label' => __('Email Report', 'codex-seller'),
                'status' => (! $settings['email_reports'] || is_email($settings['report_email'])) ? 'passed' : 'failed',
                'message' => (! $settings['email_reports'] || is_email($settings['report_email'])) ? __('Ready', 'codex-seller') : __('Invalid', 'codex-seller'),
            ],
        ];

        $passed = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'failed') {
                $passed = false;
                break;
            }
        }

        return [
            'passed' => $passed,
            'checks' => $checks,
        ];
    }

    private function run_update_cycle(string $mode)
    {
        $health = $this->run_health_check();
        if ($this->get_bool_option(self::OPTION_HEALTH, true) && ! $health['passed']) {
            return new WP_Error('codex_seller_health_failed', __('Health check failed. Fix the Safety Net items before running updates.', 'codex-seller'), ['health' => $health]);
        }

        $products = $this->fetch_products_with_install_data();
        if (is_wp_error($products)) {
            return $products;
        }

        $settings = $this->get_settings();
        $summary = [
            'mode' => $mode,
            'checked' => count($products),
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($products as $product) {
            $type = $product['package_type'] === 'theme' ? 'theme' : 'plugin';
            $name = $product['name'] ?? $product['slug'];

            if (($type === 'plugin' && ! $settings['update_plugins']) || ($type === 'theme' && ! $settings['update_themes'])) {
                $summary['skipped']++;
                $summary['items'][] = [
                    'name' => $name,
                    'status' => 'skipped',
                    'message' => __('Disabled by settings.', 'codex-seller'),
                ];
                continue;
            }

            if (empty($product['installed'])) {
                $summary['skipped']++;
                $summary['items'][] = [
                    'name' => $name,
                    'status' => 'skipped',
                    'message' => __('Not installed. Use the Install button first.', 'codex-seller'),
                ];
                continue;
            }

            if (empty($product['has_update'])) {
                $summary['skipped']++;
                $summary['items'][] = [
                    'name' => $name,
                    'status' => 'skipped',
                    'message' => __('Already up to date.', 'codex-seller'),
                ];
                continue;
            }

            if (empty($product['download_url'])) {
                $summary['skipped']++;
                $summary['items'][] = [
                    'name' => $name,
                    'status' => 'skipped',
                    'message' => __('No download file available.', 'codex-seller'),
                ];
                continue;
            }

            $result = $this->install_product_update($product, $mode);

            if (is_wp_error($result)) {
                $summary['failed']++;
                $summary['items'][] = [
                    'name' => $name,
                    'status' => 'failed',
                    'message' => $result->get_error_message(),
                ];
                continue;
            }

            $summary['updated']++;
            $summary['items'][] = [
                'name' => $name,
                'status' => 'updated',
                'message' => $result['message'],
            ];
        }

        update_option(self::OPTION_LAST_RUN, time());
        update_option(self::OPTION_LAST_SUMMARY, $summary);
        $this->maybe_send_report($summary);

        return $summary;
    }

    private function fetch_products_with_install_data()
    {
        $products = $this->fetch_remote_products();

        if (is_wp_error($products)) {
            return $products;
        }

        return array_map(function ($product) {
            return $this->hydrate_product($product);
        }, $products);
    }

    private function fetch_remote_products()
    {
        $settings = $this->get_settings();

        if (empty($settings['api_url']) || empty($settings['email']) || empty($settings['password'])) {
            return new WP_Error('codex_seller_missing_credentials', __('Please configure your API credentials.', 'codex-seller'));
        }

        $response = wp_remote_post(trailingslashit($settings['api_url']) . 'api/products/latest', [
            'body' => [
                'email' => $settings['email'],
                'password' => $settings['password'],
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $products = [];

        if (is_array($data)) {
            if (isset($data['products']) && is_array($data['products'])) {
                $products = $data['products'];
            } elseif (isset($data['data']['products']) && is_array($data['data']['products'])) {
                $products = $data['data']['products'];
            }
        }

        if ($code !== 200 || ! is_array($products)) {
            $message = is_array($data) ? ($data['error'] ?? $data['message'] ?? __('Unable to fetch products.', 'codex-seller')) : __('Unable to fetch products.', 'codex-seller');

            return new WP_Error('codex_seller_fetch_failed', $message);
        }

        return $products;
    }

    private function hydrate_product(array $product): array
    {
        $name = sanitize_text_field($product['name'] ?? $product['title'] ?? __('CodeX Seller Product', 'codex-seller'));
        $slug = sanitize_title($product['slug'] ?? $product['product_slug'] ?? $name);
        $currentVersion = sanitize_text_field($product['current_version'] ?? $product['version'] ?? $product['latest_version'] ?? '');
        $downloadUrl = esc_url_raw($product['download_url'] ?? $product['downloadUrl'] ?? '');
        $remoteType = sanitize_key($product['package_type'] ?? $product['type'] ?? '');
        $installed = $this->get_installed_product_data($slug, $name);
        $packageType = $installed['type'] ?: ($remoteType ?: 'plugin');

        if (! in_array($packageType, ['plugin', 'theme'], true)) {
            $packageType = 'plugin';
        }

        return array_merge($product, [
            'name' => $name,
            'slug' => $slug,
            'current_version' => $currentVersion,
            'download_url' => $downloadUrl,
            'installed_version' => $installed['version'],
            'has_update' => $installed['version'] && $currentVersion && version_compare($currentVersion, $installed['version'], '>'),
            'installed' => (bool) $installed['version'],
            'package_type' => $packageType,
        ]);
    }

    private function install_product_update(array $product, string $context)
    {
        $downloadUrl = esc_url_raw($product['download_url'] ?? '');
        $productName = sanitize_text_field($product['name'] ?? 'codex-seller-product');
        $slug = sanitize_title($product['slug'] ?? $productName);
        $installed = $this->get_installed_product_data($slug, $productName);
        $backup = null;

        if ($this->get_bool_option(self::OPTION_BACKUP, true) && ! empty($installed['path']) && file_exists($installed['path'])) {
            $backup = $this->create_backup($productName, $slug, $installed);

            if (is_wp_error($backup)) {
                return $backup;
            }
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tempFile = download_url($downloadUrl);

        if (is_wp_error($tempFile)) {
            return $tempFile;
        }

        $targetFile = $this->save_downloaded_package($tempFile, $productName, $downloadUrl);
        @unlink($tempFile);

        if (is_wp_error($targetFile)) {
            return $targetFile;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $installType = sanitize_key($product['package_type'] ?? '');
        if (! in_array($installType, ['plugin', 'theme'], true)) {
            $installType = $this->get_package_type($targetFile);
        }

        $skin = wp_doing_ajax() ? new WP_Ajax_Upgrader_Skin() : new Automatic_Upgrader_Skin();
        $result = null;

        if ($installType === 'theme') {
            $upgrader = new Theme_Upgrader($skin);
            $result = $this->run_upgrader_install($upgrader, $targetFile);
        } else {
            $upgrader = new Plugin_Upgrader($skin);
            $result = $this->run_upgrader_install($upgrader, $targetFile);

            if (is_wp_error($result)) {
                $themeUpgrader = new Theme_Upgrader($skin);
                $themeResult = $this->run_upgrader_install($themeUpgrader, $targetFile);

                if (! is_wp_error($themeResult) && $themeResult !== false) {
                    $installType = 'theme';
                    $result = $themeResult;
                }
            }
        }

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return new WP_Error('codex_seller_install_failed', __('Update installation failed.', 'codex-seller'));
        }

        return [
            'message' => __('Update installed successfully.', 'codex-seller'),
            'installed' => true,
            'package_type' => $installType,
            'file' => $targetFile,
            'backup' => $backup,
            'context' => $context,
        ];
    }

    private function run_upgrader_install($upgrader, string $package)
    {
        $reflection = new ReflectionMethod($upgrader, 'install');

        if ($reflection->getNumberOfParameters() >= 2) {
            return $upgrader->install($package, ['overwrite_package' => true]);
        }

        return $upgrader->install($package);
    }

    private function save_downloaded_package(string $tempFile, string $productName, string $downloadUrl)
    {
        $uploadDir = wp_upload_dir();
        $targetDir = trailingslashit($uploadDir['basedir']) . 'codex-seller/packages';
        wp_mkdir_p($targetDir);

        if (! is_dir($targetDir) || ! is_writable($targetDir)) {
            return new WP_Error('codex_seller_package_dir_failed', __('Unable to prepare the update package directory.', 'codex-seller'));
        }

        $safeName = sanitize_file_name($productName);
        $fileName = ($safeName ?: 'codex-seller-product') . '-' . wp_hash($downloadUrl) . '.zip';
        $targetFile = trailingslashit($targetDir) . $fileName;

        if (! copy($tempFile, $targetFile)) {
            return new WP_Error('codex_seller_package_save_failed', __('Unable to save the downloaded update file.', 'codex-seller'));
        }

        return $targetFile;
    }

    private function create_backup(string $productName, string $slug, array $installed)
    {
        if (! class_exists('ZipArchive')) {
            return new WP_Error('codex_seller_zip_missing', __('ZipArchive is required to create backups.', 'codex-seller'));
        }

        $sourcePath = $installed['path'] ?? '';
        if (empty($sourcePath) || ! file_exists($sourcePath)) {
            return new WP_Error('codex_seller_backup_source_missing', __('The installed product path could not be found for backup.', 'codex-seller'));
        }

        $backupDir = $this->get_backup_dir();
        wp_mkdir_p($backupDir);

        if (! is_dir($backupDir) || ! is_writable($backupDir)) {
            return new WP_Error('codex_seller_backup_dir_failed', __('Unable to write to the backup directory.', 'codex-seller'));
        }

        $created = time();
        $safeSlug = sanitize_file_name($slug ?: $productName);
        $backupFile = trailingslashit($backupDir) . ($safeSlug ?: 'codex-seller-product') . '-' . gmdate('Ymd-His', $created) . '.zip';
        $metadata = [
            'name' => $productName,
            'slug' => $slug,
            'version' => $installed['version'] ?? '',
            'type' => $installed['type'] ?? 'plugin',
            'path' => wp_normalize_path($sourcePath),
            'relative_path' => $installed['relative_path'] ?? '',
            'created' => $created,
        ];

        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('codex_seller_backup_create_failed', __('Unable to create backup archive.', 'codex-seller'));
        }

        $this->add_path_to_zip($zip, $sourcePath);
        $zip->addFromString('codex-seller-backup.json', wp_json_encode($metadata, JSON_PRETTY_PRINT));
        $zip->close();

        return array_merge($metadata, [
            'file' => $backupFile,
            'url' => trailingslashit($this->get_backup_url()) . basename($backupFile),
        ]);
    }

    private function add_path_to_zip(ZipArchive $zip, string $source): void
    {
        $source = wp_normalize_path($source);
        $basePath = dirname($source);

        if (is_file($source)) {
            $zip->addFile($source, basename($source));
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filePath = wp_normalize_path($file->getPathname());
            $localPath = ltrim(substr($filePath, strlen($basePath)), '/\\');
            $zip->addFile($filePath, $localPath);
        }
    }

    private function restore_latest_backup()
    {
        $backup = $this->get_latest_backup();

        if (! $backup) {
            return new WP_Error('codex_seller_no_backup', __('No backup is available for rollback.', 'codex-seller'));
        }

        return $this->restore_backup($backup['file']);
    }

    private function restore_backup(string $backupFile)
    {
        if (! class_exists('ZipArchive')) {
            return new WP_Error('codex_seller_zip_missing', __('ZipArchive is required to restore backups.', 'codex-seller'));
        }

        $metadata = $this->read_backup_metadata($backupFile);
        if (empty($metadata['path']) || empty($metadata['type'])) {
            return new WP_Error('codex_seller_backup_metadata_missing', __('This backup does not include restore metadata.', 'codex-seller'));
        }

        $targetPath = wp_normalize_path($metadata['path']);
        $allowedRoot = $metadata['type'] === 'theme' ? wp_normalize_path(get_theme_root()) : wp_normalize_path(WP_PLUGIN_DIR);

        if (! $this->is_safe_child_path($targetPath, $allowedRoot)) {
            return new WP_Error('codex_seller_unsafe_restore_path', __('The backup restore path is outside the allowed WordPress directory.', 'codex-seller'));
        }

        if (file_exists($targetPath) && ! $this->delete_path($targetPath, $allowedRoot)) {
            return new WP_Error('codex_seller_restore_delete_failed', __('Unable to remove the current product before rollback.', 'codex-seller'));
        }

        $zip = new ZipArchive();
        if ($zip->open($backupFile) !== true) {
            return new WP_Error('codex_seller_restore_open_failed', __('Unable to open the backup archive.', 'codex-seller'));
        }

        $result = $this->extract_backup_zip($zip, dirname($targetPath), $allowedRoot);
        $zip->close();

        if (! $result) {
            return new WP_Error('codex_seller_restore_failed', __('Unable to restore the backup archive.', 'codex-seller'));
        }

        return [
            'message' => __('Rollback completed successfully.', 'codex-seller'),
            'backup' => $metadata,
        ];
    }

    private function extract_backup_zip(ZipArchive $zip, string $destination, string $allowedRoot): bool
    {
        $destination = wp_normalize_path($destination);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            $entryName = str_replace('\\', '/', (string) $entryName);

            if ($entryName === 'codex-seller-backup.json' || $entryName === '') {
                continue;
            }

            if ($entryName[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $entryName)) {
                return false;
            }

            $target = wp_normalize_path(trailingslashit($destination) . $entryName);
            if (! $this->is_safe_child_path($target, $allowedRoot)) {
                return false;
            }

            if (substr($entryName, -1) === '/') {
                wp_mkdir_p($target);
                continue;
            }

            wp_mkdir_p(dirname($target));

            $source = $zip->getStream($entryName);
            if (! $source) {
                return false;
            }

            $targetHandle = fopen($target, 'wb');
            if (! $targetHandle) {
                fclose($source);
                return false;
            }

            stream_copy_to_stream($source, $targetHandle);
            fclose($source);
            fclose($targetHandle);
        }

        return true;
    }

    private function get_backup_dir(): string
    {
        $uploadDir = wp_upload_dir();

        return trailingslashit($uploadDir['basedir']) . 'codex-seller/backups';
    }

    private function get_backup_url(): string
    {
        $uploadDir = wp_upload_dir();

        return trailingslashit($uploadDir['baseurl']) . 'codex-seller/backups';
    }

    private function get_latest_backup(): array
    {
        $backups = $this->get_backups(1);

        return $backups[0] ?? [];
    }

    private function get_backups(int $limit = 10): array
    {
        $backupDir = $this->get_backup_dir();
        if (! is_dir($backupDir)) {
            return [];
        }

        $files = glob(trailingslashit($backupDir) . '*.zip');
        if (! $files) {
            return [];
        }

        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $backups = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $metadata = $this->read_backup_metadata($file);
            $backups[] = array_merge([
                'name' => basename($file),
                'slug' => '',
                'version' => '',
                'type' => '',
                'path' => '',
                'relative_path' => '',
                'created' => filemtime($file) ?: time(),
            ], $metadata, [
                'file' => $file,
                'size' => file_exists($file) ? filesize($file) : 0,
            ]);
        }

        return $backups;
    }

    private function read_backup_metadata(string $file): array
    {
        if (! class_exists('ZipArchive') || ! file_exists($file)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return [];
        }

        $index = $zip->locateName('codex-seller-backup.json');
        if ($index === false) {
            $zip->close();
            return [];
        }

        $contents = $zip->getFromIndex($index);
        $zip->close();

        $metadata = json_decode((string) $contents, true);

        return is_array($metadata) ? $metadata : [];
    }

    private function delete_path(string $path, string $allowedRoot): bool
    {
        $path = wp_normalize_path($path);
        if (! $this->is_safe_child_path($path, $allowedRoot)) {
            return false;
        }

        if (is_file($path) || is_link($path)) {
            return unlink($path);
        }

        if (! is_dir($path)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = wp_normalize_path($file->getPathname());
            if (! $this->is_safe_child_path($filePath, $allowedRoot)) {
                return false;
            }

            if ($file->isDir() && ! $file->isLink()) {
                if (! rmdir($filePath)) {
                    return false;
                }
            } elseif (! unlink($filePath)) {
                return false;
            }
        }

        return rmdir($path);
    }

    private function is_safe_child_path(string $path, string $root): bool
    {
        $path = untrailingslashit(wp_normalize_path($path));
        $root = untrailingslashit(wp_normalize_path($root));

        return $path !== $root && strpos($path . '/', trailingslashit($root)) === 0;
    }

    private function maybe_send_report(array $summary): void
    {
        $settings = $this->get_settings();

        if (! $settings['email_reports'] || ! is_email($settings['report_email'])) {
            return;
        }

        $lines = [
            __('CodeX Seller update report', 'codex-seller'),
            '',
            sprintf(__('Mode: %s', 'codex-seller'), $summary['mode'] ?? 'manual'),
            sprintf(__('Products checked: %d', 'codex-seller'), (int) ($summary['checked'] ?? 0)),
            sprintf(__('Updated: %d', 'codex-seller'), (int) ($summary['updated'] ?? 0)),
            sprintf(__('Skipped: %d', 'codex-seller'), (int) ($summary['skipped'] ?? 0)),
            sprintf(__('Failed: %d', 'codex-seller'), (int) ($summary['failed'] ?? 0)),
            '',
        ];

        foreach (($summary['items'] ?? []) as $item) {
            $lines[] = sprintf('%s - %s - %s', $item['name'] ?? '', $item['status'] ?? '', $item['message'] ?? '');
        }

        wp_mail($settings['report_email'], __('CodeX Seller Update Report', 'codex-seller'), implode("\n", $lines));
        update_option(self::OPTION_LAST_REPORT, time());
    }

    private function get_installed_product_data(string $slug, string $name = ''): array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $needleSlug = sanitize_title($slug);
        $nameSlug = sanitize_title($name);
        $plugins = get_plugins();

        foreach ($plugins as $path => $details) {
            $parts = explode('/', $path);
            $folder = $parts[0];
            $pluginName = $details['Name'] ?? '';
            $matches = [
                sanitize_title($folder),
                sanitize_title($path),
                sanitize_title($pluginName),
            ];

            if (in_array($needleSlug, $matches, true) || ($nameSlug && in_array($nameSlug, $matches, true)) || strpos(strtolower($path), strtolower($needleSlug . '.php')) !== false) {
                $sourcePath = count($parts) > 1 ? trailingslashit(WP_PLUGIN_DIR) . $folder : trailingslashit(WP_PLUGIN_DIR) . $path;

                return [
                    'version' => $details['Version'] ?? '',
                    'type' => 'plugin',
                    'path' => $sourcePath,
                    'relative_path' => $path,
                    'name' => $pluginName,
                ];
            }
        }

        $themes = wp_get_themes();
        foreach ($themes as $themeSlug => $theme) {
            $themeName = $theme->get('Name');
            if (sanitize_title($themeSlug) === $needleSlug || sanitize_title($themeName) === $needleSlug || ($nameSlug && sanitize_title($themeName) === $nameSlug)) {
                return [
                    'version' => $theme->get('Version') ?: '',
                    'type' => 'theme',
                    'path' => $theme->get_stylesheet_directory(),
                    'relative_path' => $themeSlug,
                    'name' => $themeName,
                ];
            }
        }

        return [
            'version' => '',
            'type' => 'plugin',
            'path' => '',
            'relative_path' => '',
            'name' => '',
        ];
    }

    private function get_package_type(string $zipFile): string
    {
        if (! class_exists('ZipArchive')) {
            return 'plugin';
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return 'plugin';
        }

        $themeCandidate = false;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (preg_match('/^[^\/]+\/style\.css$/i', $name)) {
                $themeCandidate = true;
                break;
            }
        }

        $zip->close();

        return $themeCandidate ? 'theme' : 'plugin';
    }

    private function format_schedule_time($timestamp, bool $enabled): string
    {
        if (! $enabled) {
            return __('Paused', 'codex-seller');
        }

        if (! $timestamp) {
            return __('Not scheduled', 'codex-seller');
        }

        return $this->format_timestamp((int) $timestamp);
    }

    private function format_timestamp(int $timestamp): string
    {
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}

register_activation_hook(__FILE__, ['CodeX_Seller_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['CodeX_Seller_Plugin', 'deactivate']);

new CodeX_Seller_Plugin();
