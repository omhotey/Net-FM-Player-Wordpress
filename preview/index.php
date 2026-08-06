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
function sanitize_text_field($text) { return trim(strip_tags((string) $text)); }
function untrailingslashit($value) { return rtrim($value, '/'); }
function wp_json_encode($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function get_option($name, $default = false) { return $default; }
function did_action($hook) { return false; }
function shortcode_atts($pairs, $atts) { return array_merge($pairs, array_intersect_key((array) $atts, $pairs)); }
function disabled($condition, $current = true, $display = true) { if ($condition === $current && $display) { echo ' disabled'; } }

require_once dirname(__DIR__) . '/net-fm-player.php';

$player = Net_Fm_Player_Plugin::instance();
$theme = (isset($_GET['theme']) && $_GET['theme'] === 'vinyl') ? 'vinyl' : 'studio';
$markup = $player->render_shortcode(['theme' => $theme]);
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
