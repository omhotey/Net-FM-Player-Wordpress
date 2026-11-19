<?php
// Install the tested candidate in the WordPress Lab, preserving site data.
define('FS_METHOD', 'direct');
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$file = 'net-fm-player/net-fm-player.php';
$snapshot = static function () { return hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107), get_option('active_plugins')])); };
$before = $snapshot();
if (!is_plugin_active($file)) { throw new RuntimeException('Lab plugin is not active'); }
$installer = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $installer->install('/tmp/nfp-candidate-20261119.zip', ['overwrite_package' => true, 'clear_update_cache' => false]);
if (is_wp_error($result) || !$result) { throw new RuntimeException('Candidate installation failed'); }
if (get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false)['Version'] !== '0.3.2') { throw new RuntimeException('Wrong installed version'); }
if ($snapshot() !== $before) { throw new RuntimeException('Lab settings, pages or active plugins changed'); }
echo "Active sandbox candidate installed; settings, pages and active plugins preserved.\n";
