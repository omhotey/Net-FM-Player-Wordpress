<?php
/**
 * Local visual preview for the plugin. It renders the real shortcode using
 * small WordPress-compatible helpers, without loading a WordPress site.
 */

define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR);

function add_action($hook, $callback) {}
function add_shortcode($tag, $callback) {}
function wp_enqueue_style($handle) {}
function wp_enqueue_script($handle) {}
function __($text, $domain = null) { return $text; }
function esc_html__($text, $domain = null) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr__($text, $domain = null) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($url) { return trim((string) $url); }
function sanitize_hex_color($color) { return preg_match('/^#[0-9a-f]{6}$/i', (string) $color) ? strtolower((string) $color) : null; }
function sanitize_text_field($text) { return trim(strip_tags((string) $text)); }
function untrailingslashit($value) { return rtrim($value, '/'); }
function wp_json_encode($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function get_option($name, $default = false) { return $default; }
function did_action($hook) { return false; }
function shortcode_atts($pairs, $atts) { return array_merge($pairs, array_intersect_key((array) $atts, $pairs)); }
function disabled($condition, $current = true, $display = true) { if ($condition === $current && $display) { echo ' disabled'; } }

if (isset($_GET['api']) && in_array($_GET['api'], ['ending', 'cycle', 'delay'], true)) {
    $is_cycle = $_GET['api'] === 'cycle';
    $is_delay = $_GET['api'] === 'delay';
    $cycle_length = 20;
    $cycle_number = $is_cycle ? intdiv(time(), $cycle_length) : 0;
    $elapsed = $is_cycle ? time() % $cycle_length : 168;
    if ($is_delay) {
        sleep(8);
        $elapsed = 30;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode([
        'is_online' => true,
        'station' => [
            'name' => 'Упоротая волна',
            'description' => 'Тест смены трека',
        ],
        'listeners' => ['current' => 20],
        'now_playing' => [
            'elapsed' => $elapsed,
            'duration' => $is_cycle ? $cycle_length : 180,
            'song' => [
                'id' => $is_cycle ? 'preview-cycle-' . $cycle_number : 'preview-ending-track',
                'title' => $is_cycle ? 'Тестовый трек ' . $cycle_number : 'Последние секунды трека',
                'artist' => 'Preview Artist',
                'art' => '/preview/station-logo.svg?album=' . ($is_cycle ? $cycle_number : 1),
            ],
        ],
    ]);
    exit;
}

require_once dirname(__DIR__) . '/net-fm-player.php';

$player = Net_Fm_Player_Plugin::instance();
$theme = (isset($_GET['theme']) && $_GET['theme'] === 'vinyl') ? 'vinyl' : 'studio';
$direct = isset($_GET['direct']) && $_GET['direct'] === '1';
$fixture = isset($_GET['fixture']) && in_array($_GET['fixture'], ['ending', 'cycle', 'delay'], true) ? $_GET['fixture'] : '';
$has_fixture = $fixture !== '';
$custom_colors = isset($_GET['custom']) && $_GET['custom'] === '1';
$markup = $player->render_shortcode([
    'use_global' => 'no',
    'station_label' => $has_fixture ? 'Упоротая волна' : 'UPWAVE Radio',
    'api_url' => $has_fixture ? '/preview/?api=' . $fixture : '',
    'azuracast_base_url' => ($direct || $has_fixture) ? '' : 'https://stream.net-fm.ru',
    'station_shortcode' => ($direct || $has_fixture) ? '' : 'upwaveradio',
    'stream_primary' => 'https://stream.net-fm.ru/listen/upwaveradio/stream128',
    'stream_primary_label' => '128 kbps',
    'stream_secondary' => 'https://stream.net-fm.ru/listen/upwaveradio/stream320',
    'stream_secondary_label' => '320 kbps',
    'fallback_art' => '/preview/station-logo.svg',
    'theme' => $theme,
    'background_color' => $custom_colors ? '#16213e' : '',
    'play_button_color' => $custom_colors ? '#ffb703' : '',
    'volume_color' => $custom_colors ? '#219ebc' : '',
]);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NET FM Player</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%2307b00a'/%3E%3Cpath d='M25 18v28l22-14z' fill='%23fff'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="/assets/net-fm-player.css">
    <style>
        body { min-width: 320px; margin: 0; color: #f5fff7; font-family: Inter, ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; background: #171717; }
        main { width: min(100% - 32px, 960px); margin: 0 auto; padding: clamp(24px, 6vw, 72px) 0; }
    </style>
</head>
<body>
    <main>
        <?php echo $markup; ?>
    </main>
    <script src="/assets/net-fm-player.js"></script>
</body>
</html>
