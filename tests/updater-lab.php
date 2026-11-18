<?php
// Runs against the designated WordPress sandbox; does not change site settings.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
function nfp_update_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
}
$file = 'net-fm-player/net-fm-player.php';
$asset = 'https://github.com/omhotey/net-fm-player/releases/download/v0.3.2/net-fm-player-0.3.2.zip';
$fixture = ['tag_name' => 'v0.3.2', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'net-fm-player-0.3.2.zip', 'browser_download_url' => $asset, 'state' => 'uploaded']]];
$fixture_filter = static function () use (&$fixture) { return $fixture; };
add_filter('pre_site_transient_net_fm_player_github_release', $fixture_filter);
$result = apply_filters('update_plugins_github.com', false, ['Version' => '0.3.1'], $file, []);
nfp_update_assert(is_array($result) && $result['version'] === '0.3.2' && $result['package'] === $asset, 'Stable release did not produce update metadata');
$details = apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'net-fm-player']);
nfp_update_assert(is_object($details) && $details->version === '0.3.2' && $details->download_link === $asset && !empty($details->sections['description']), 'Plugin details unavailable');
nfp_update_assert(apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'other']) === false, 'Updater changed another plugin details');
nfp_update_assert(apply_filters('update_plugins_github.com', false, [], 'other/other.php', []) === false, 'Updater affected another plugin');
$fixture['prerelease'] = true;
nfp_update_assert(apply_filters('update_plugins_github.com', false, [], $file, []) === false, 'Prerelease was offered as stable');
$fixture['prerelease'] = false;
$fixture['assets'][0]['browser_download_url'] = 'https://attacker.invalid/net-fm-player-0.3.2.zip';
nfp_update_assert(apply_filters('update_plugins_github.com', false, [], $file, []) === false, 'Untrusted package URL accepted');
$fixture['assets'][0]['browser_download_url'] = $asset;
$fixture['tag_name'] = 'v0.3.2-rc1';
nfp_update_assert(apply_filters('update_plugins_github.com', false, [], $file, []) === false, 'Invalid tag accepted');
$fixture['tag_name'] = 'v0.3.2';
$fixture['draft'] = true;
nfp_update_assert(apply_filters('update_plugins_github.com', false, [], $file, []) === false, 'Draft release offered');
remove_filter('pre_site_transient_net_fm_player_github_release', $fixture_filter);
echo "GitHub updater: stable release accepted; unrelated plugin, prerelease, draft and untrusted package rejected.\n";
