<?php
// Disposable test content for the explicitly designated WordPress sandbox.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
if (get_option('net_fm_player_options', false) !== false) {
    fwrite(STDERR, "Existing player settings found; refusing to replace them.\n");
    exit(1);
}
if (get_page_by_path('iradio-player-test')) {
    fwrite(STDERR, "Test page already exists; refusing to replace it.\n");
    exit(1);
}
$plugin = Net_Fm_Player_Plugin::instance();
$settings = $plugin->sanitize_options([
    'stations' => [
        'upwave' => ['station_label' => 'UpWave Radio', 'azuracast_base_url' => 'https://stream.net-fm.ru', 'station_shortcode' => 'upwaveradio', 'stream_primary_url' => 'https://stream.net-fm.ru/listen/upwaveradio/stream128'],
        'steelballs' => ['station_label' => 'SteelBalls Radio', 'azuracast_base_url' => 'https://stream.net-fm.ru', 'station_shortcode' => 'steelballsradio', 'stream_primary_url' => 'https://stream.net-fm.ru/listen/steelballsradio/radio.mp3'],
    ],
]);
add_option('net_fm_player_options', $settings);
$post_id = wp_insert_post([
    'post_title' => 'IRadio Player Test',
    'post_name' => 'iradio-player-test',
    'post_status' => 'publish',
    'post_type' => 'page',
    'post_content' => '<!-- wp:shortcode -->[net_fm_player stations="upwave,steelballs"]<!-- /wp:shortcode -->' . "\n\n" .
        '<!-- wp:net-fm-player/player {"station":"steelballs"} /-->',
], true);
if (is_wp_error($post_id)) {
    fwrite(STDERR, $post_id->get_error_message() . PHP_EOL);
    exit(1);
}
echo "Test page ID: " . $post_id . PHP_EOL;
