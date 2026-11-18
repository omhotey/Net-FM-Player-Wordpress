<?php
// Isolated plugin directory with the existing sandbox database; no live copy is replaced.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
define('WP_PLUGIN_DIR', '/tmp/nfp-updater-' . getmypid() . '/plugins');
define('WP_PLUGIN_URL', 'http://localhost.invalid/plugins');
if (!mkdir(WP_PLUGIN_DIR, 0755, true)) { throw new RuntimeException('Cannot create isolated plugin directory'); }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
function nfp_update_test($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function nfp_update_snapshot() { return hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107)])); }
$before = nfp_update_snapshot();
ob_start();
$installer = new Plugin_Upgrader(new WP_Upgrader_Skin());
$installed = $installer->install('/tmp/net-fm-player-0.3.1.zip', ['clear_update_cache' => false]);
$log = ob_get_clean();
nfp_update_test($installed === true, 'Candidate install failed');
$plugin_file = 'net-fm-player/net-fm-player.php';
require WP_PLUGIN_DIR . '/' . $plugin_file;
$package = 'https://github.com/omhotey/net-fm-player/releases/download/v0.3.2/net-fm-player-0.3.2.zip';
$release = ['tag_name' => 'v0.3.2', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.2.zip', 'browser_download_url' => $package, 'state' => 'uploaded']]];
$release_filter = static function () use ($release) { return $release; };
add_filter('pre_site_transient_net_fm_player_github_release', $release_filter);
$update = apply_filters('update_plugins_github.com', false, ['Version' => '0.3.1'], $plugin_file, []);
remove_filter('pre_site_transient_net_fm_player_github_release', $release_filter);
nfp_update_test(is_array($update) && $update['version'] === '0.3.2' && $update['package'] === $package, 'Updater did not discover test version');
$update['new_version'] = $update['version'];
$update['plugin'] = $plugin_file;
$updates = (object) ['response' => [$plugin_file => (object) $update]];
$update_filter = static function () use ($updates) { return $updates; };
$active_filter = static function () { return []; };
$no_backup_filter = static function ($options) { unset($options['hook_extra']['temp_backup']); return $options; };
$http_filter = static function ($preempt, $args, $url) use ($package) {
    if ($url !== $package) { return $preempt; }
    if (empty($args['stream']) || empty($args['filename']) || !copy('/tmp/net-fm-player-0.3.2-fixture.zip', $args['filename'])) {
        return new WP_Error('nfp_test_stream', 'Cannot copy test package');
    }
    return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => $args['filename']];
};
add_filter('pre_site_transient_update_plugins', $update_filter);
add_filter('pre_option_active_plugins', $active_filter);
add_filter('upgrader_package_options', $no_backup_filter);
add_filter('pre_http_request', $http_filter, 10, 3);
ob_start();
$upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $upgrader->upgrade($plugin_file, ['clear_update_cache' => false]);
$log = ob_get_clean();
remove_filter('pre_http_request', $http_filter, 10);
remove_filter('upgrader_package_options', $no_backup_filter);
remove_filter('pre_option_active_plugins', $active_filter);
remove_filter('pre_site_transient_update_plugins', $update_filter);
nfp_update_test($result === true, 'WordPress upgrade failed: ' . (is_wp_error($result) ? $result->get_error_message() : $log));
nfp_update_test(get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false)['Version'] === '0.3.2', 'Updated package version mismatch');
nfp_update_test(nfp_update_snapshot() === $before, 'Station settings or test pages changed');
echo "WordPress discovered simulated GitHub 0.3.2 and upgraded 0.3.1 via the core upgrader; settings and pages unchanged.\n";
