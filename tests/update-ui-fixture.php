<?php
// Only install temporarily as a mu-plugin in WordPress Lab, then remove.
if (!defined('ABSPATH')) { exit; }
add_filter('pre_site_transient_net_fm_player_github_release', static function () {
    return ['tag_name' => 'v0.3.2', 'draft' => false, 'prerelease' => false, 'assets' => [[
        'name' => 'net-fm-player-0.3.2.zip',
        'browser_download_url' => 'https://github.com/omhotey/net-fm-player/releases/download/v0.3.2/net-fm-player-0.3.2.zip',
        'state' => 'uploaded',
    ]]];
});
add_filter('site_transient_update_plugins', static function ($transient) {
    if (!is_admin() || !current_user_can('update_plugins') || !class_exists('Net_Fm_Player_Updater')) { return $transient; }
    if (!is_object($transient)) { $transient = new stdClass(); }
    $file = 'net-fm-player/net-fm-player.php';
    $update = Net_Fm_Player_Updater::check(false, ['Version' => '0.3.1'], $file, []);
    if (is_array($update)) {
        $update['plugin'] = $file;
        $update['new_version'] = $update['version'];
        $transient->response[$file] = (object) $update;
    }
    return $transient;
});
