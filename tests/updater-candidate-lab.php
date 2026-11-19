<?php
// Tests candidate update checks in an isolated plugin directory and DB transaction.
define('WP_ADMIN', true);
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
$root = '/tmp/nfp-updater-candidate-' . getmypid();
define('WP_PLUGIN_DIR', $root . '/plugins');
define('WP_PLUGIN_URL', 'http://localhost.invalid/plugins');
set_exception_handler(static function (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
if (!mkdir(WP_PLUGIN_DIR, 0755, true)) { throw new RuntimeException('Cannot create isolated directory'); }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
function nfp_check_candidate($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
nfp_check_candidate(!wp_using_ext_object_cache(), 'External object cache prevents test rollback');
global $wpdb;
$table = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $wpdb->options));
nfp_check_candidate($table && strtoupper($table->Engine) === 'INNODB', 'Options table must support transactions');
ob_start();
$installer = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $installer->install('/tmp/nfp-candidate-20261119.zip', ['clear_update_cache' => false]);
ob_end_clean();
nfp_check_candidate($result === true, 'Candidate installation failed');
require WP_PLUGIN_DIR . '/net-fm-player/net-fm-player.php';
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
nfp_check_candidate(!empty($admins), 'Sandbox administrator not found');
wp_set_current_user($admins[0]);
$file = 'net-fm-player/net-fm-player.php';
$old = ['tag_name' => 'v0.3.1', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.1.zip', 'browser_download_url' => 'https://github.com/omhotey/net-fm-player/releases/download/v0.3.1/net-fm-player-0.3.1.zip', 'state' => 'uploaded']]];
$new = ['tag_name' => 'v0.3.3', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.3.zip', 'browser_download_url' => 'https://github.com/omhotey/net-fm-player/releases/download/v0.3.3/net-fm-player-0.3.3.zip', 'state' => 'uploaded']]];
$calls = 0;
$fail = false;
add_filter('pre_site_transient_net_fm_player_github_release', static function () use ($old) { return $old; });
add_filter('pre_http_request', static function ($preempt, $args, $url) use (&$calls, &$fail, $new) {
    if ($url !== 'https://api.github.com/repos/omhotey/net-fm-player/releases/latest') { return $preempt; }
    $calls++;
    if ($fail) { return new WP_Error('nfp_network_failure', 'Test network failure'); }
    return ['headers' => [], 'body' => wp_json_encode($new), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
}, 10, 3);
if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Cannot start test transaction'); }
try {
    $GLOBALS['pagenow'] = 'plugins.php';
    $result = Net_Fm_Player_Updater::check(false, [], $file, []);
    nfp_check_candidate($result['version'] === '0.3.1' && $calls === 0, 'Normal check failed to use cache');
    $GLOBALS['pagenow'] = 'update-core.php';
    $_GET['force-check'] = '1';
    $result = Net_Fm_Player_Updater::check(false, [], $file, []);
    nfp_check_candidate($result['version'] === '0.3.3' && $calls === 1, 'Manual check failed to bypass stale cache');
    $fail = true;
    nfp_check_candidate(Net_Fm_Player_Updater::check(false, [], $file, []) === false, 'Network failure returned an update');
    echo "Normal check used cached release; manual check fetched fresh release; network failure failed safely.\n";
} finally {
    unset($_GET['force-check']);
    $wpdb->query('ROLLBACK');
}
