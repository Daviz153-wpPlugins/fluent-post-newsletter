<?php
/**
 * Plugin Name: Fluent Post Newsletter
 * Plugin URI:  https://github.com/Daviz153-wpPlugins/fluent-post-newsletter
 * Description: 워드프레스 포스트를 FluentCRM 이메일 캠페인으로 복제·발송하는 애드온 플러그인
 * Version:     0.1.0
 * Author:      Daviz153
 * License:     GPL-2.0-or-later
 * Text Domain: fluent-post-newsletter
 * Requires PHP: 8.2
 * Requires Plugins: fluent-crm
 */

defined('ABSPATH') || exit;

define('FPN_VERSION', '0.1.0');
define('FPN_FILE',    __FILE__);
define('FPN_DIR',     plugin_dir_path(__FILE__));
define('FPN_URL',     plugin_dir_url(__FILE__));

// GitHub 자동 업데이트
if (file_exists(FPN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php')) {
    require_once FPN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
    $fpnUpdater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/Daviz153-wpPlugins/fluent-post-newsletter/',
        __FILE__,
        'fluent-post-newsletter'
    );
    $fpnUpdater->getVcsApi()->enableReleaseAssets();
}

add_action('plugins_loaded', function () {
    if (!defined('FLUENTCRM')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
               . esc_html__('Fluent Post Newsletter를 사용하려면 FluentCRM이 활성화되어 있어야 합니다.', 'fluent-post-newsletter')
               . '</p></div>';
        });
        return;
    }

    require_once FPN_DIR . 'includes/class-content-sanitizer.php';
    require_once FPN_DIR . 'includes/class-email-template.php';
    require_once FPN_DIR . 'includes/class-campaign-manager.php';
    require_once FPN_DIR . 'includes/class-meta-box.php';
    require_once FPN_DIR . 'includes/class-plugin.php';

    FluentPostNewsletter\Plugin::getInstance();
});
