
$plugin = Net_Fm_Player_Plugin::instance();
$GLOBALS['test_options'] = ['station_label' => 'Legacy', 'stream_primary_url' => 'https://legacy.test/live'];
$legacy = $plugin->render_shortcode([]);
if (strpos($legacy, 'legacy.test') === false) { fwrite(STDERR, "Legacy shortcode settings not preserved\n"); exit(1); }
$GLOBALS['test_options'] = ['api_url' => 'https://example.test/api', 'stream_primary_url' => 'https://example.test/live'];
if (strpos($plugin->render_shortcode([]), '&quot;stationLabelOverride&quot;:false') === false) { fwrite(STDERR, "Default station label must allow metadata name\n"); exit(1); }
if (strpos($plugin->render_shortcode(['station_label' => 'My Radio']), '&quot;stationLabelOverride&quot;:true') === false) { fwrite(STDERR, "Explicit station label must be preserved\n"); exit(1); }
$one = ['station_label' => 'One', 'stream_primary_url' => 'https://one.test/live', 'stream_primary_label' => '128 kbps', 'stream_secondary_url' => 'https://one.test/high', 'stream_secondary_label' => '320 kbps', 'api_url' => 'https://one.test/api'];
$two = ['station_label' => 'Two', 'stream_primary_url' => 'https://two.test/live', 'api_url' => 'https://two.test/api'];
$sanitized = $plugin->sanitize_options(['stations' => ['one' => $one, 'two' => $two]]);
$GLOBALS['test_options'] = $sanitized;
function check($condition, $message) { if (!$condition) { fwrite(STDERR, $message . PHP_EOL); exit(1); } }
$html = $plugin->render_shortcode(['station' => 'one']);
check(strpos($html, '&quot;stationLabelOverride&quot;:true') !== false, 'Selected station name must be preserved');
check(strpos($html, 'one.test') !== false && strpos($html, 'two.test') === false, 'Single station isolation failed');
check(strpos($html, '128 kbps') !== false && strpos($html, '320 kbps') !== false, 'Station quality labels missing');
check(strpos($html, 'data-nfp-stream="secondary"') !== false, 'Second quality is not selectable');
$quality_api = $plugin->public_stations();
check($quality_api['stations'][0]['stream_secondary_label'] === '320 kbps', 'Station quality label missing from public API');
check(strpos($html, 'data-nfp-track-accessible aria-live="polite" aria-atomic="true"') !== false, 'Accessible track announcement missing');
check(strpos($html, 'data-nfp-title aria-hidden="true"') !== false, 'Animated title exposed to assistive technology');
$html = $plugin->render_shortcode(['stations' => 'one,two']);
check(strpos($html, 'data-nfp-switcher') !== false, 'Selector missing');
check(substr_count($html, 'data-nfp-station-template=') === 2, 'Station templates missing');
check(substr_count($plugin->render_shortcode(['stations' => 'all']), 'data-nfp-station-template=') === 2, 'All stations selector failed');
$GLOBALS['test_options']['stations']['two']['frontend_enabled'] = false;
$api = $plugin->public_stations();
check(count($api['stations']) === 1 && $api['stations'][0]['id'] === 'one', 'Public API exposed disabled station');
$html = $plugin->render_shortcode(['stations' => 'all']);
check(strpos($html, 'one.test') !== false && strpos($html, 'two.test') === false, 'Disabled station appeared in selector');
check(strpos($plugin->render_shortcode(['station' => 'two']), 'two.test') !== false, 'Direct station must remain available');
$GLOBALS['test_options']['stations']['two']['frontend_enabled'] = true;
check(strpos($plugin->render_shortcode(['station' => 'missing']), 'не найдена') !== false, 'Missing station must report an error');
check(strpos($plugin->render_block([]), 'alignwide') !== false, 'Block wrapper missing');
$existing = $plugin->sanitize_options(['stations' => ['one' => $one, 'two' => $two + ['delete' => '1']]]);
check(isset($existing['stations']['one']) && !isset($existing['stations']['two']), 'Station deletion failed');
$_POST['option_page'] = 'net_fm_player_settings';
$saved = $plugin->sanitize_options(['stations' => ['one' => $one]]);
check(isset($saved['stations']['two']), 'Truncated settings submission must preserve existing stations');
$_POST[Net_Fm_Player_Plugin::OPTION_NAME]['submission_complete'] = '1';
$first_pass = $plugin->sanitize_options(['stations' => ['one' => $one], 'submission_complete' => '1']);
$second_pass = $plugin->sanitize_options($first_pass);
check(isset($second_pass['stations']['one']) && !isset($second_pass['stations']['two']), 'Repeated sanitization rejected a complete submission');
unset($_POST[Net_Fm_Player_Plugin::OPTION_NAME]);
unset($_POST['option_page']);
echo "Synthetic station rendering and settings checks passed\n";
