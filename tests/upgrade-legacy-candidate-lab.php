<?php
// Simulates the documented site-side 0.3.0 settings patch in an isolated directory.
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
$root = '/tmp/nfp-legacy-candidate-' . getmypid();
define('WP_PLUGIN_DIR', $root . '/plugins');
define('WP_PLUGIN_URL', 'http://localhost.invalid/plugins');
set_exception_handler(static function (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
if (!mkdir(WP_PLUGIN_DIR, 0755, true)) { throw new RuntimeException('Cannot create isolated directory'); }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
function nfp_legacy_assert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function nfp_legacy_snapshot() { return hash('sha256', serialize([get_option('net_fm_player_options'), get_post(99), get_post(101), get_post(107)])); }
$before = nfp_legacy_snapshot();
nfp_legacy_assert(hash_file('sha256', '/tmp/nfp-legacy-20261119.zip') === 'eec4ca78d09b30daf0e91a36b1370c491e344e051e5dc5feafc0406213b95f62', 'Legacy ZIP mismatch');
ob_start();
$installer = new Plugin_Upgrader(new WP_Upgrader_Skin());
$result = $installer->install('/tmp/nfp-legacy-20261119.zip', ['clear_update_cache' => false]);
ob_end_clean();
nfp_legacy_assert($result === true, 'Legacy install failed');
$file = WP_PLUGIN_DIR . '/net-fm-player/net-fm-player.php';
$source = file_get_contents($file);
$old = "empty(\$input['submission_complete'])";
$patched = "empty(\$_POST[self::OPTION_NAME]['submission_complete'])";
nfp_legacy_assert(substr_count($source, $old) === 1, 'Legacy settings patch target changed');
nfp_legacy_assert(file_put_contents($file, str_replace($old, $patched, $source)) !== false, 'Cannot apply test-only settings patch');
ob_start();
$result = $installer->install('/tmp/nfp-candidate-20261119.zip', ['overwrite_package' => true, 'clear_update_cache' => false]);
$log = ob_get_clean();
nfp_legacy_assert($result === true, 'Candidate replacement failed: ' . $log);
nfp_legacy_assert(get_plugin_data($file, false, false)['Version'] === '0.3.2', 'Candidate version mismatch');
nfp_legacy_assert(strpos(file_get_contents($file), $patched) !== false, 'Candidate lost the existing settings fix');
nfp_legacy_assert(nfp_legacy_snapshot() === $before, 'Sandbox settings or pages changed');
echo "Patched 0.3.0 was replaced by candidate 0.3.2; settings fix, station options and pages preserved.\n";
