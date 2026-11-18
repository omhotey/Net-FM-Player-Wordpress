<?php
// Exercise activation in the explicitly designated WordPress sandbox.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin = 'net-fm-player/net-fm-player.php';
if (!is_plugin_active($plugin)) { throw new RuntimeException('Expected sandbox plugin to be active'); }
$before = hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107)]));
try {
    deactivate_plugins($plugin);
    if (is_plugin_active($plugin)) { throw new RuntimeException('Deactivation failed'); }
    $result = activate_plugin($plugin);
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
    if (!is_plugin_active($plugin)) { throw new RuntimeException('Activation did not persist'); }
} finally {
    if (!is_plugin_active($plugin)) { activate_plugin($plugin); }
}
$after = hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107)]));
if ($before !== $after) { throw new RuntimeException('Settings or pages changed'); }
echo "WordPress activation persisted; station settings and test pages unchanged.\n";
