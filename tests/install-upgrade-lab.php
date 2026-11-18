<?php
// Isolated plugin directory, existing WordPress database: do not activate either copy.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
define('WP_PLUGIN_DIR', '/tmp/nfp-installer-' . getmypid() . '/plugins');
define('WP_PLUGIN_URL', 'http://localhost.invalid/plugins');
if (!mkdir(WP_PLUGIN_DIR, 0755, true)) { throw new RuntimeException('Cannot create isolated plugin directory'); }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
function nfp_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function nfp_snapshot() {
    return hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107)]));
}
function nfp_install($zip, $overwrite) {
    ob_start();
    $upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
    $result = $upgrader->install($zip, ['overwrite_package' => $overwrite, 'clear_update_cache' => false]);
    $log = ob_get_clean();
    if ($result !== true) { throw new RuntimeException('Install failed: ' . (is_wp_error($result) ? $result->get_error_message() : $log)); }
}
function nfp_version() {
    return get_plugin_data(WP_PLUGIN_DIR . '/net-fm-player/net-fm-player.php', false, false)['Version'];
}
$before = nfp_snapshot();
nfp_install('/tmp/net-fm-player-0.3.0-test.zip', false);
nfp_check(nfp_version() === '0.3.0', 'Clean install version mismatch');
nfp_check(is_file(WP_PLUGIN_DIR . '/net-fm-player/assets/net-fm-player-admin.css'), 'Admin stylesheet missing from package');
nfp_install('/tmp/net-fm-player-0.2.0-prior.zip', true);
nfp_check(nfp_version() === '0.2.0', 'Prior version install failed');
nfp_install('/tmp/net-fm-player-0.3.0-test.zip', true);
nfp_check(nfp_version() === '0.3.0', 'Upgrade version mismatch');
nfp_install('/tmp/net-fm-player-0.2.0-prior.zip', true);
$fake_update = (object) ['response' => [
    'net-fm-player/net-fm-player.php' => (object) ['package' => '/tmp/net-fm-player-0.3.0-test.zip'],
]];
$update_filter = static function () use ($fake_update) { return $fake_update; };
$active_filter = static function () { return []; };
$no_backup_filter = static function ($options) { unset($options['hook_extra']['temp_backup']); return $options; };
add_filter('pre_site_transient_update_plugins', $update_filter);
// The sandbox's real plugin has the same slug; never deactivate it during this isolated test.
add_filter('pre_option_active_plugins', $active_filter);
// This test copy is disposable; shared instructions prohibit automatic backups.
add_filter('upgrader_package_options', $no_backup_filter);
ob_start();
$upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $upgrader->upgrade('net-fm-player/net-fm-player.php', ['clear_update_cache' => false]);
$log = ob_get_clean();
remove_filter('upgrader_package_options', $no_backup_filter);
remove_filter('pre_option_active_plugins', $active_filter);
remove_filter('pre_site_transient_update_plugins', $update_filter);
if ($result !== true) { throw new RuntimeException('Upgrade failed: ' . (is_wp_error($result) ? $result->get_error_message() : $log)); }
nfp_check(nfp_version() === '0.3.0', 'Core upgrade path version mismatch');
nfp_check(nfp_snapshot() === $before, 'Settings or test pages changed');
require WP_PLUGIN_DIR . '/net-fm-player/net-fm-player.php';
$plugin = Net_Fm_Player_Plugin::instance();
$plugin->register_assets();
$plugin->register_block();
nfp_check(WP_Block_Type_Registry::get_instance()->is_registered('net-fm-player/player'), 'Installed block is not registered');
$rendered = $plugin->render_shortcode(['stations' => 'all']);
nfp_check(strpos($rendered, 'data-nfp-switcher') !== false, 'Upgraded player failed to use existing station settings');
nfp_check(strpos($rendered, 'steelballsradio') !== false, 'Upgraded player did not render SteelBalls');
echo "Clean installation, uploaded replacement and local-package core upgrade 0.2.0 -> 0.3.0 passed; settings and test pages unchanged.\n";
