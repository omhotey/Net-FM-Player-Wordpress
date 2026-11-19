<?php
// Tests the real 0.3.1 updater against a candidate in an isolated plugin directory.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
$root = '/tmp/nfp-upgrade-candidate-' . getmypid();
define('WP_PLUGIN_DIR', $root . '/plugins');
define('WP_PLUGIN_URL', 'http://localhost.invalid/plugins');
set_exception_handler(static function (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
if (!mkdir(WP_PLUGIN_DIR, 0755, true)) { throw new RuntimeException('Cannot create isolated directory'); }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
function nfp_upgrade_assert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function nfp_upgrade_snapshot() { return hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107)])); }
$before = nfp_upgrade_snapshot();
nfp_upgrade_assert(hash_file('sha256', '/tmp/nfp-base-20261119.zip') === '3218f8066301b1004ea205c00526854c45e4b138d7ac5dce4a03f617e5a60650', 'Base ZIP differs from published release');
ob_start();
$installer = new Plugin_Upgrader(new WP_Upgrader_Skin());
$installed = $installer->install('/tmp/nfp-base-20261119.zip', ['clear_update_cache' => false]);
ob_end_clean();
nfp_upgrade_assert($installed === true, 'Base release install failed');
$file = 'net-fm-player/net-fm-player.php';
require WP_PLUGIN_DIR . '/' . $file;
$renamed = ['tag_name' => 'v0.3.2', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.2.zip', 'browser_download_url' => 'https://github.com/omhotey/Net-FM-Player-Wordpress/releases/download/v0.3.2/net-fm-player-0.3.2.zip', 'state' => 'uploaded']]];
$renamed_filter = static function () use ($renamed) { return $renamed; };
add_filter('pre_site_transient_net_fm_player_github_release', $renamed_filter);
nfp_upgrade_assert(apply_filters('update_plugins_github.com', false, ['Version' => '0.3.1'], $file, []) === false, 'Expected 0.3.1 to reject renamed repository asset');
remove_filter('pre_site_transient_net_fm_player_github_release', $renamed_filter);
$package = 'https://github.com/omhotey/net-fm-player/releases/download/v0.3.2/net-fm-player-0.3.2.zip';
$release = ['tag_name' => 'v0.3.2', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.2.zip', 'browser_download_url' => $package, 'state' => 'uploaded']]];
$release_filter = static function () use ($release) { return $release; };
add_filter('pre_site_transient_net_fm_player_github_release', $release_filter);
$update = apply_filters('update_plugins_github.com', false, ['Version' => '0.3.1'], $file, []);
remove_filter('pre_site_transient_net_fm_player_github_release', $release_filter);
nfp_upgrade_assert(is_array($update) && $update['version'] === '0.3.2', 'Published 0.3.1 updater did not discover candidate');
$update['new_version'] = $update['version'];
$update['plugin'] = $file;
$updates = (object) ['response' => [$file => (object) $update]];
$update_filter = static function () use ($updates) { return $updates; };
$active_filter = static function () { return []; };
$no_backup = static function ($options) { unset($options['hook_extra']['temp_backup']); return $options; };
$http = static function ($preempt, $args, $url) use ($package) {
    if ($url !== $package) { return $preempt; }
    if (empty($args['stream']) || empty($args['filename']) || !copy('/tmp/nfp-candidate-20261119.zip', $args['filename'])) {
        return new WP_Error('nfp_fixture', 'Cannot copy candidate archive');
    }
    return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => $args['filename']];
};
add_filter('pre_site_transient_update_plugins', $update_filter);
add_filter('pre_option_active_plugins', $active_filter);
add_filter('upgrader_package_options', $no_backup);
add_filter('pre_http_request', $http, 10, 3);
ob_start();
$upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $upgrader->upgrade($file, ['clear_update_cache' => false]);
$log = ob_get_clean();
remove_filter('pre_http_request', $http, 10);
remove_filter('upgrader_package_options', $no_backup);
remove_filter('pre_option_active_plugins', $active_filter);
remove_filter('pre_site_transient_update_plugins', $update_filter);
nfp_upgrade_assert($result === true, 'Upgrade failed: ' . (is_wp_error($result) ? $result->get_error_message() : $log));
nfp_upgrade_assert(get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false)['Version'] === '0.3.2', 'Candidate version mismatch');
nfp_upgrade_assert(nfp_upgrade_snapshot() === $before, 'Sandbox settings or pages changed');
echo "Published 0.3.1 updater discovered simulated release and installed 0.3.2 candidate; station settings and pages unchanged.\n";
