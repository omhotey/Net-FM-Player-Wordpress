<?php
// Sandbox only: keep Elementor out of Plugin Check AJAX requests.
// This does not change the site's stored active_plugins option.
if (!defined('ABSPATH')) { exit; }
$nfp_check_actions = [
    'plugin_check_get_checks_to_run',
    'plugin_check_set_up_environment',
    'plugin_check_run_checks',
    'plugin_check_clean_up_environment',
];
$nfp_action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
if (in_array($nfp_action, $nfp_check_actions, true)) {
    add_filter('option_active_plugins', static function ($plugins) {
        return is_array($plugins) ? array_values(array_diff($plugins, ['elementor/elementor.php'])) : $plugins;
    }, PHP_INT_MAX);
}
