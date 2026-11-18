<?php
// Read-only integration checks against the explicitly designated sandbox.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
function nfp_lab_assert($value, $message) {
    if (!$value) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
}
$registry = WP_Block_Type_Registry::get_instance();
nfp_lab_assert($registry->is_registered('net-fm-player/player'), 'Gutenberg block is not registered');
$content = render_block([
    'blockName' => 'net-fm-player/player',
    'attrs' => ['station' => 'steelballs', 'align' => 'wide'],
    'innerBlocks' => [],
    'innerHTML' => '',
    'innerContent' => [],
]);
nfp_lab_assert(strpos($content, 'data-nfp') !== false, 'Block did not render player');
nfp_lab_assert(strpos($content, 'alignwide') !== false, 'Block alignment missing');
nfp_lab_assert(strpos($content, 'steelballsradio') !== false, 'Block station not rendered');
$response = rest_do_request(new WP_REST_Request('GET', '/net-fm-player/v1/stations'));
nfp_lab_assert($response->get_status() === 200, 'Catalog endpoint failed');
$data = $response->get_data();
$ids = array_column($data['stations'], 'id');
nfp_lab_assert(in_array('upwave', $ids, true) && in_array('steelballs', $ids, true), 'Enabled stations missing');
nfp_lab_assert(!in_array('failure-test', $ids, true), 'Disabled station exposed');
$write_response = rest_do_request(new WP_REST_Request('POST', '/net-fm-player/v1/stations'));
nfp_lab_assert($write_response->get_status() >= 400, 'Public catalog accepts a write request');
wp_set_current_user(0);
$settings_before = get_option('net_fm_player_options');
$unauthorized_settings = new WP_REST_Request('POST', '/wp/v2/settings');
$unauthorized_settings->set_body_params(['net_fm_player_options' => ['station_label' => 'unauthorized-test']]);
$unauthorized_response = rest_do_request($unauthorized_settings);
nfp_lab_assert(in_array($unauthorized_response->get_status(), [401, 403], true), 'Anonymous settings write was not denied');
nfp_lab_assert(get_option('net_fm_player_options') === $settings_before, 'Anonymous settings request changed station configuration');
$editor_role = get_role('editor');
nfp_lab_assert($editor_role !== null, 'Editor role missing');
$synthetic_editor = new WP_User();
$synthetic_editor->ID = 999999;
$synthetic_editor->roles = ['editor'];
$synthetic_editor->allcaps = $editor_role->capabilities;
$GLOBALS['current_user'] = $synthetic_editor;
ob_start();
Net_Fm_Player_Plugin::instance()->render_settings_page();
$editor_page = ob_get_clean();
nfp_lab_assert($editor_page === '', 'Settings page rendered for an editor without manage_options');
$editor_response = rest_do_request(new WP_REST_Request('POST', '/wp/v2/settings'));
nfp_lab_assert(in_array($editor_response->get_status(), [401, 403], true), 'Editor was allowed to write site settings');
wp_set_current_user(0);
ob_start();
Net_Fm_Player_Plugin::instance()->render_settings_page();
$unauthorized_page = ob_get_clean();
nfp_lab_assert($unauthorized_page === '', 'Settings page rendered for a visitor');
$unsafe_options = [
    'station_label' => '<script>alert(1)</script>',
    'stream_primary_url' => 'javascript:alert(1)',
    'fallback_art' => 'javascript:alert(1)',
];
$unsafe_filter = static function () use ($unsafe_options) { return $unsafe_options; };
add_filter('pre_option_net_fm_player_options', $unsafe_filter);
$unsafe_html = do_shortcode('[net_fm_player]');
remove_filter('pre_option_net_fm_player_options', $unsafe_filter);
nfp_lab_assert(stripos($unsafe_html, '<script') === false, 'Script tag in rendered player');
nfp_lab_assert(stripos($unsafe_html, 'javascript:') === false, 'Unsafe URL in rendered player');
$malicious_options = [
    'stations' => [
        'bad' => ['station_label' => '<img src=x onerror=alert(1)>', 'stream_primary_url' => 'javascript:alert(1)', 'frontend_enabled' => true],
        'good' => ['station_label' => 'Normal', 'stream_primary_url' => 'https://example.test/live', 'frontend_enabled' => true],
    ],
];
$malicious_filter = static function () use ($malicious_options) { return $malicious_options; };
add_filter('pre_option_net_fm_player_options', $malicious_filter);
$malicious_html = do_shortcode('[net_fm_player stations="all"]');
remove_filter('pre_option_net_fm_player_options', $malicious_filter);
nfp_lab_assert(stripos($malicious_html, '<img src=x onerror=') === false, 'Unescaped station label in selector');
nfp_lab_assert(stripos($malicious_html, 'javascript:') === false, 'Unsafe station stream in selector');
$legacy_options = ['station_label' => 'Legacy station', 'stream_primary_url' => 'https://legacy.example.test/live'];
$legacy_filter = static function () use ($legacy_options) { return $legacy_options; };
add_filter('pre_option_net_fm_player_options', $legacy_filter);
$legacy_html = do_shortcode('[net_fm_player]');
remove_filter('pre_option_net_fm_player_options', $legacy_filter);
nfp_lab_assert(strpos($legacy_html, 'legacy.example.test') !== false, 'Legacy global shortcode configuration failed');
echo "WordPress block rendering and public catalog checks passed\n";
