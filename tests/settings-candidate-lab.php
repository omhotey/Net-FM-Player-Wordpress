<?php
// The sandbox option is changed only inside a database transaction and rolled back.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
set_exception_handler(static function (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
$root = '/tmp/nfp-settings-candidate-' . getmypid();
define('WP_PLUGIN_DIR', $root . '/plugins');
define('WP_PLUGIN_URL', 'http://localhost.invalid/plugins');
if (!mkdir(WP_PLUGIN_DIR, 0755, true)) { throw new RuntimeException('Cannot create isolated plugin directory'); }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
function nfp_settings_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
if (wp_using_ext_object_cache()) { throw new RuntimeException('External object cache would invalidate rollback isolation'); }
global $wpdb;
$table = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $wpdb->options));
nfp_settings_assert($table && strtoupper($table->Engine) === 'INNODB', 'Options table must support transactions');
ob_start();
$installer = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $installer->install('/tmp/nfp-candidate-20261119.zip', ['clear_update_cache' => false]);
$log = ob_get_clean();
nfp_settings_assert($result === true, 'Candidate install failed: ' . $log);
require WP_PLUGIN_DIR . '/net-fm-player/net-fm-player.php';
$plugin = Net_Fm_Player_Plugin::instance();
$sample = ['use_global' => 'no', 'stream_primary' => 'https://one.test/320', 'stream_primary_label' => '320 kbps', 'stream_secondary' => 'https://one.test/128', 'stream_secondary_label' => '128 kbps', 'stream_hls_url' => 'https://one.test/live.m3u8', 'default_stream' => 'hls'];
$html = $plugin->render_shortcode($sample);
nfp_settings_assert(strpos($html, 'data-nfp-stream="hls"') === false, 'Auto button is still present');
nfp_settings_assert(strpos($html, 'data-nfp-stream="primary"') !== false && strpos($html, 'data-nfp-stream="secondary"') !== false, 'Direct quality buttons are missing');
nfp_settings_assert(strpos($html, '&quot;defaultStream&quot;:&quot;secondary&quot;') !== false, '128 kbps stream was not selected by default');
$plugin->register_settings();
$before = get_option(Net_Fm_Player_Plugin::OPTION_NAME);
$before_hash = hash('sha256', serialize($before));
$calls = 0;
add_filter('sanitize_option_' . Net_Fm_Player_Plugin::OPTION_NAME, static function ($value) use (&$calls) { $calls++; return $value; }, 1);
if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Cannot start transaction'); }
try {
    delete_option(Net_Fm_Player_Plugin::OPTION_NAME);
    $_POST['option_page'] = 'net_fm_player_settings';
    $_POST[Net_Fm_Player_Plugin::OPTION_NAME] = ['submission_complete' => '1'];
    $input = ['submission_complete' => '1', 'station_label' => 'Test only', 'stations' => [
        'a' => ['station_label' => 'Station A', 'stream_primary_url' => 'https://example.test/a', 'frontend_enabled' => '1'],
        'b' => ['station_label' => 'Station B', 'stream_primary_url' => 'https://example.test/b', 'frontend_enabled' => '0'],
    ]];
    nfp_settings_assert(update_option(Net_Fm_Player_Plugin::OPTION_NAME, $input), 'First save failed');
    $saved = get_option(Net_Fm_Player_Plugin::OPTION_NAME);
    nfp_settings_assert($calls >= 2, 'WordPress did not exercise repeated sanitization');
    nfp_settings_assert(($saved['stations']['a']['station_label'] ?? '') === 'Station A', 'First station not saved');
    nfp_settings_assert(($saved['stations']['b']['frontend_enabled'] ?? null) === false, 'Disabled station not preserved');
    unset($_POST[Net_Fm_Player_Plugin::OPTION_NAME]['submission_complete']);
    update_option(Net_Fm_Player_Plugin::OPTION_NAME, ['stations' => ['a' => $input['stations']['a']]]);
    nfp_settings_assert(get_option(Net_Fm_Player_Plugin::OPTION_NAME) === $saved, 'Truncated form overwrote saved stations');
    echo "WordPress first save passed repeated sanitization ($calls calls); truncated save preserved stations.\n";
} finally {
    $wpdb->query('ROLLBACK');
    unset($_POST['option_page'], $_POST[Net_Fm_Player_Plugin::OPTION_NAME]);
    wp_cache_delete(Net_Fm_Player_Plugin::OPTION_NAME, 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
}
nfp_settings_assert(hash('sha256', serialize(get_option(Net_Fm_Player_Plugin::OPTION_NAME))) === $before_hash, 'Sandbox settings changed after rollback');
echo "Sandbox option readback matches the original after rollback.\n";
