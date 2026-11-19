<?php
// Real WordPress update-check flow with mocked remote services, rolled back.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
$package = 'https://github.com/omhotey/Net-FM-Player-Wordpress/releases/download/v0.3.2/net-fm-player-0.3.2.zip';
$release = ['tag_name' => 'v0.3.2', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.2.zip', 'browser_download_url' => $package, 'state' => 'uploaded']]];
$captured = null;
add_filter('pre_site_transient_net_fm_player_github_release', static function () use ($release) { return $release; });
add_filter('pre_site_transient_update_plugins', static function () { return (object) ['last_checked' => 0, 'checked' => []]; });
add_filter('pre_set_site_transient_update_plugins', static function ($value) use (&$captured) { $captured = $value; return $value; });
add_filter('pre_http_request', static function ($preempt, $args, $url) {
    if (strpos($url, 'https://api.wordpress.org/plugins/update-check/') !== 0) { return $preempt; }
    return ['headers' => [], 'body' => '{"plugins":{},"translations":[],"no_update":{}}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
}, 10, 3);
global $wpdb;
if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Cannot start test transaction'); }
try {
    wp_update_plugins();
    $entry = $captured->response['net-fm-player/net-fm-player.php'] ?? null;
    if (!$entry || ($entry->new_version ?? '') !== '0.3.2' || ($entry->package ?? '') !== $package) {
        throw new RuntimeException('Core update check did not advertise the simulated release');
    }
    echo "WordPress core update check advertised simulated GitHub 0.3.2 with the correct package.\n";
} finally {
    $wpdb->query('ROLLBACK');
}
