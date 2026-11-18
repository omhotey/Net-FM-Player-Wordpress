<?php
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
if (get_page_by_path('iradio-player-failure-test')) {
    fwrite(STDERR, "Failure test page exists; refusing to replace it.\n");
    exit(1);
}
$settings = get_option('net_fm_player_options');
if (!is_array($settings) || !isset($settings['stations']['upwave']) || isset($settings['stations']['failure-test'])) {
    fwrite(STDERR, "Unexpected test settings; refusing to change them.\n");
    exit(1);
}
$settings['stations']['failure-test'] = [
    'station_label' => 'Проверка резерва',
    'api_url' => 'https://stream.net-fm.ru/api/nowplaying/nonexistent-iradio-test',
    'stream_primary_url' => 'https://stream.net-fm.ru/nonexistent-iradio-test.mp3',
    'stream_secondary_url' => 'https://stream.net-fm.ru/listen/upwaveradio/stream128',
];
update_option('net_fm_player_options', $settings);
$id = wp_insert_post([
    'post_title' => 'IRadio Player Failure Test',
    'post_name' => 'iradio-player-failure-test',
    'post_status' => 'publish',
    'post_type' => 'page',
    'post_content' => '[net_fm_player station="failure-test"]',
], true);
if (is_wp_error($id)) { fwrite(STDERR, $id->get_error_message()); exit(1); }
echo "Failure test page ID: " . $id . PHP_EOL;
