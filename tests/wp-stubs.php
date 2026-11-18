<?php
define('ABSPATH', '/test/');
function add_action(...$args) {}
function add_shortcode(...$args) {}
function get_option($key, $default = []) { return $GLOBALS['test_options'] ?? $default; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
function sanitize_hex_color($v) { return preg_match('/^#[a-f0-9]{6}$/i', $v) ? $v : ''; }
function esc_url_raw($v) { return filter_var($v, FILTER_VALIDATE_URL) ? $v : ''; }
function esc_url($v) { return esc_attr($v); }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return esc_attr($v); }
function esc_html__($v, $domain = '') { return esc_html($v); }
function esc_attr__($v, $domain = '') { return esc_attr($v); }
function __($v, $domain = '') { return $v; }
function untrailingslashit($v) { return rtrim($v, '/'); }
function wp_generate_uuid4() { return '11111111-1111-4111-8111-111111111111'; }
function shortcode_atts($defaults, $atts, $shortcode = '') { return array_merge($defaults, array_intersect_key($atts, $defaults)); }
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_json_encode($v) { return json_encode($v); }
function disabled($v) { if ($v) echo ' disabled="disabled"'; }
function get_block_wrapper_attributes() { return 'class="wp-block-net-fm-player-player alignwide"'; }
function rest_ensure_response($value) { return $value; }
function add_settings_error(...$args) {}
?>
