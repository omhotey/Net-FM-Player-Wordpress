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
function selected($value, $current = true, $display = true) { if ($value === $current && $display) { echo ' selected'; } }
function checked($value, $current = true, $display = true) { if ($value === $current && $display) { echo ' checked'; } }

if (isset($_GET['api']) && in_array($_GET['api'], ['ending', 'cycle', 'delay', 'lag'], true)) {
    $is_cycle = in_array($_GET['api'], ['cycle', 'lag'], true);
    $is_lag = $_GET['api'] === 'lag';
    $is_delay = $_GET['api'] === 'delay';
    $cycle_length = 20;
    $cycle_number = $is_cycle ? intdiv(time(), $cycle_length) : 0;
    $cycle_phase = $is_cycle ? time() % $cycle_length : 0;
    $elapsed = $is_cycle ? $cycle_phase : 168;
    if ($is_lag) {
        $cycle_number -= $cycle_phase < 10 ? 1 : 0;
        $elapsed = $cycle_phase < 10 ? min($cycle_length, 12 + $cycle_phase) : $cycle_phase - 10;
    }
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
                'art' => 'station-logo.svg?album=' . ($is_cycle ? $cycle_number : 1),
            ],
        ],
        'playing_next' => [
            'song' => [
                'id' => $is_cycle ? 'preview-cycle-' . ($cycle_number + 1) : 'preview-next-track',
                'title' => $is_cycle ? 'Тестовый трек ' . ($cycle_number + 1) : 'Следующий тестовый трек',
                'artist' => 'Preview Artist',
                'art' => 'station-logo.svg?album=' . ($is_cycle ? ($cycle_number + 1) : 2),
            ],
        ],
    ]);
    exit;
}

require_once dirname(__DIR__) . '/net-fm-player.php';

$player = Net_Fm_Player_Plugin::instance();
$theme = (isset($_GET['theme']) && $_GET['theme'] === 'vinyl') ? 'vinyl' : 'studio';
$layout = (isset($_GET['layout']) && $_GET['layout'] === 'vertical') ? 'vertical' : 'horizontal';
$direct = isset($_GET['direct']) && $_GET['direct'] === '1';
$hls = isset($_GET['hls']) && $_GET['hls'] === '1';
$fixture = isset($_GET['fixture']) && in_array($_GET['fixture'], ['ending', 'cycle', 'delay', 'lag'], true) ? $_GET['fixture'] : '';
$has_fixture = $fixture !== '';
$custom_colors = isset($_GET['custom']) && $_GET['custom'] === '1';
$markup = $player->render_shortcode([
    'use_global' => 'no',
    'station_label' => $has_fixture ? 'Упоротая волна' : 'UPWAVE Radio',
    'api_url' => $has_fixture ? '?api=' . $fixture : '',
    'azuracast_base_url' => ($direct || $has_fixture) ? '' : 'https://stream.net-fm.ru',
    'station_shortcode' => ($direct || $has_fixture) ? '' : 'upwaveradio',
    'stream_primary' => 'https://stream.net-fm.ru/listen/upwaveradio/stream128',
    'stream_primary_label' => '128 kbps',
    'stream_secondary' => 'https://stream.net-fm.ru/listen/upwaveradio/stream320',
    'stream_secondary_label' => '320 kbps',
    'stream_hls_url' => $hls ? 'https://stream.net-fm.ru/hls/upwaveradio/live.m3u8' : '',
    'fallback_art' => 'station-logo.svg',
    'theme' => $theme,
    'layout' => $layout,
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
    <link rel="stylesheet" href="../assets/net-fm-player.css">
    <link rel="stylesheet" href="preview.css">
</head>
<body>
    <main class="nfp-lab">
        <aside class="nfp-lab__panel" aria-labelledby="nfp-lab-title">
            <div class="nfp-lab__intro">
                <p class="nfp-lab__eyebrow">NET FM Player</p>
                <h1 id="nfp-lab-title">Панель разработки</h1>
                <p>Меняйте настройки плеера рядом с его реальной разметкой. Значения сохраняются только в этом браузере и могут быть переданы ссылкой.</p>
            </div>

            <form class="nfp-lab__form" data-nfp-lab-form novalidate>
                <details open>
                    <summary>Эфир</summary>
                    <div class="nfp-lab__fields">
                        <label>Название станции<input name="stationLabel" type="text" value="<?php echo esc_attr($has_fixture ? 'Упоротая волна' : 'UPWAVE Radio'); ?>"></label>
                        <label>AzuraCast Base URL<input name="azuracastBaseUrl" type="url" inputmode="url" value="<?php echo esc_attr(($direct || $has_fixture) ? '' : 'https://stream.net-fm.ru'); ?>"></label>
                        <label class="nfp-lab__field--wide">Явный Now Playing API URL<input name="apiUrl" type="url" inputmode="url" value="<?php echo esc_attr($has_fixture ? '?api=' . $fixture : ''); ?>"></label>
                        <label>URL основного потока<input name="primaryUrl" type="url" inputmode="url" value="https://stream.net-fm.ru/listen/upwaveradio/stream128"></label>
                        <label>Название основного потока<input name="primaryLabel" type="text" value="128 kbps"></label>
                        <label>URL дополнительного потока<input name="secondaryUrl" type="url" inputmode="url" value="https://stream.net-fm.ru/listen/upwaveradio/stream320"></label>
                        <label>Название дополнительного потока<input name="secondaryLabel" type="text" value="320 kbps"></label>
                        <label class="nfp-lab__field--wide">URL HLS-потока<input name="hlsUrl" type="url" inputmode="url" value="<?php echo esc_attr($hls ? 'https://stream.net-fm.ru/hls/upwaveradio/live.m3u8' : ''); ?>"></label>
                        <label>Название HLS-потока<input name="hlsLabel" type="text" value="Авто"></label>
                        <label>Поток по умолчанию<select name="defaultStream"><option value="primary">Основной</option><option value="secondary">Дополнительный</option><option value="hls">Авто (HLS)</option></select></label>
                        <label class="nfp-lab__check"><input name="metadataEnabled" type="checkbox" <?php checked(!$direct); ?>> Получать данные трека</label>
                        <label>Station shortcode<input name="stationShortcode" type="text" value="<?php echo esc_attr(($direct || $has_fixture) ? '' : 'upwaveradio'); ?>"></label>
                    </div>
                </details>

                <details>
                    <summary>Вид плеера</summary>
                    <div class="nfp-lab__fields">
                        <label>Тема<select name="theme"><option value="studio" <?php selected($theme, 'studio'); ?>>Студия</option><option value="vinyl" <?php selected($theme, 'vinyl'); ?>>Винил</option></select></label>
                        <label>Версия плеера<select name="layout"><option value="horizontal" <?php selected($layout, 'horizontal'); ?>>Горизонтальная</option><option value="vertical" <?php selected($layout, 'vertical'); ?>>Вертикальная</option></select></label>
                        <label>URL обложки<input name="fallbackArt" type="url" inputmode="url" value="station-logo.svg"></label>
                        <label class="nfp-lab__field--wide">Загрузить обложку<input name="fallbackArtFile" type="file" accept="image/*"></label>
                        <label>Размещение по горизонтали<select name="horizontalPosition"><option value="left">Слева</option><option value="center" selected>По центру</option><option value="right">Справа</option></select></label>
                        <label>Размещение по вертикали<select name="verticalPosition"><option value="top">Сверху</option><option value="center">По центру</option><option value="bottom">Снизу</option></select></label>
                        <label>Отступ от краёв<input name="pageOffset" type="range" min="0" max="120" step="4" value="48"><output data-nfp-lab-page-offset>48px</output></label>
                        <label>Фон плеера<input name="background" type="color" value="<?php echo esc_attr($custom_colors ? '#16213e' : '#16213e'); ?>"></label>
                        <label>Прозрачность фона плеера<input name="backgroundOpacity" type="range" min="0" max="1" step="0.05" value="1"><output data-nfp-lab-background-opacity>100%</output></label>
                        <label class="nfp-lab__check"><input name="playerBackgroundEnabled" type="checkbox" <?php checked($custom_colors); ?>> Включить фон плеера</label>
                        <label>Фон страницы<input name="pageBackground" type="color" value="#131416"></label>
                        <label>Прозрачность фона страницы<input name="pageBackgroundOpacity" type="range" min="0" max="1" step="0.05" value="1"><output data-nfp-lab-page-background-opacity>100%</output></label>
                        <label class="nfp-lab__check"><input name="pageBackgroundEnabled" type="checkbox" checked> Включить фон страницы</label>
                        <label class="nfp-lab__field--wide">URL фонового изображения<input name="pageBackgroundImage" type="url" inputmode="url" placeholder="https://..."></label>
                        <label class="nfp-lab__field--wide">Загрузить фоновое изображение<input name="pageBackgroundFile" type="file" accept="image/*"></label>
                    </div>
                </details>

                <details>
                    <summary>Цвета и тени</summary>
                    <div class="nfp-lab__fields">
                        <label>Текст<input name="textColor" type="color" value="#292118"></label>
                        <label>Кнопка play<input name="playButton" type="color" value="<?php echo esc_attr($custom_colors ? '#ffb703' : '#ffb703'); ?>"></label>
                        <label>Громкость<input name="volume" type="color" value="<?php echo esc_attr($custom_colors ? '#219ebc' : '#219ebc'); ?>"></label>
                        <label>Mute и качество<input name="controlsColor" type="color" value="#d8caad"></label>
                        <label>Активный поток<input name="qualityColor" type="color" value="#8f2e19"></label>
                        <label>Цвет тени<input name="shadowColor" type="color" value="#000000"></label>
                        <label>Прозрачность тени<input name="shadowOpacity" type="range" min="0" max="1" step="0.05" value="0.28"><output data-nfp-lab-shadow-opacity>28%</output></label>
                        <label>Размытие тени<input name="shadowBlur" type="range" min="0" max="120" step="4" value="56"><output data-nfp-lab-shadow-blur>56px</output></label>
                        <label>Смещение тени<input name="shadowOffsetY" type="range" min="-40" max="100" step="2" value="24"><output data-nfp-lab-shadow-offset>24px</output></label>
                    </div>
                </details>

                <div class="nfp-lab__actions">
                    <button class="nfp-lab__button" type="button" data-nfp-lab-reset>Сбросить</button>
                    <button class="nfp-lab__button nfp-lab__button--primary" type="button" data-nfp-lab-save>Сохранить</button>
                    <output data-nfp-lab-status role="status" aria-live="polite"></output>
                </div>
            </form>
        </aside>

        <section class="nfp-lab__preview" aria-label="Предпросмотр плеера">
            <p class="nfp-lab__preview-label">Live preview</p>
            <div class="nfp-lab__player" data-nfp-lab-player>
                <?php echo $markup; ?>
            </div>
        </section>
    </main>
    <script src="../assets/net-fm-player.js"></script>
    <script src="preview.js"></script>
</body>
</html>
