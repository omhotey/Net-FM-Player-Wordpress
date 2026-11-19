<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Supplies WordPress with verified release metadata from the public repository. */
final class Net_Fm_Player_Updater
{
    private const REPOSITORY = 'https://github.com/omhotey/Net-FM-Player-Wordpress';
    private const API = 'https://api.github.com/repos/omhotey/Net-FM-Player-Wordpress/releases/latest';
    private const CACHE_KEY = 'net_fm_player_github_release';

    public static function register()
    {
        add_filter('update_plugins_github.com', [__CLASS__, 'check'], 10, 4);
        add_filter('plugins_api', [__CLASS__, 'plugin_information'], 10, 3);
    }

    public static function plugin_information($result, $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== 'net-fm-player') {
            return $result;
        }
        $release = self::check(false, [], plugin_basename(dirname(__DIR__) . '/net-fm-player.php'), []);
        return (object) [
            'name' => 'NET FM Player for AzuraCast',
            'slug' => 'net-fm-player',
            'version' => is_array($release) ? $release['version'] : Net_Fm_Player_Plugin::VERSION,
            'author' => 'Юрий Струк (omhotey)',
            'homepage' => self::REPOSITORY,
            'requires' => '6.5',
            'requires_php' => '8.2',
            'sections' => [
                'description' => '<p>' . esc_html__('Радиоплеер для WordPress: станции AzuraCast, выбор станции и качества, Gutenberg и Elementor.', 'net-fm-player') . '</p>',
                'changelog' => '<p><a href="' . esc_url(self::REPOSITORY . '/releases') . '">' . esc_html__('История выпусков на GitHub', 'net-fm-player') . '</a></p>',
            ],
            'download_link' => is_array($release) ? $release['package'] : '',
        ];
    }

    public static function check($update, $plugin_data, $plugin_file, $locales)
    {
        if ($plugin_file !== plugin_basename(dirname(__DIR__) . '/net-fm-player.php')) {
            return $update;
        }

        // The native "Check again" action should actually ask GitHub rather
        // than reuse our six-hour cache. Other checks retain the rate limit.
        $force_refresh = is_admin() && current_user_can('update_plugins')
            && ($GLOBALS['pagenow'] ?? '') === 'update-core.php'
            && !empty($_GET['force-check']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $release = $force_refresh ? false : get_site_transient(self::CACHE_KEY);
        $fetched = false;
        if (!is_array($release)) {
            $response = wp_safe_remote_get(self::API, [
                'timeout' => 5,
                'redirection' => 2,
                'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'NET-FM-Player/' . Net_Fm_Player_Plugin::VERSION],
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return $update;
            }
            $release = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($release)) {
                return $update;
            }
            $fetched = true;
        }

        if (!empty($release['draft']) || !empty($release['prerelease']) || !isset($release['tag_name']) || !is_string($release['tag_name']) || !preg_match('/^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $release['tag_name'])) {
            return $update;
        }
        $version = substr($release['tag_name'], 1);
        $package = self::REPOSITORY . '/releases/download/' . $release['tag_name'] . '/net-fm-player-' . $version . '.zip';
        $asset_found = false;
        foreach (is_array($release['assets'] ?? null) ? $release['assets'] : [] as $asset) {
            if (is_array($asset) && ($asset['name'] ?? '') === 'net-fm-player-' . $version . '.zip' && ($asset['browser_download_url'] ?? '') === $package && ($asset['state'] ?? '') === 'uploaded') {
                $asset_found = true;
                break;
            }
        }
        if (!$asset_found) {
            return $update;
        }

        if ($fetched) {
            set_site_transient(self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS);
        }
        return [
            'id' => self::REPOSITORY,
            'slug' => 'net-fm-player',
            'version' => $version,
            'url' => self::REPOSITORY . '/releases/tag/' . $release['tag_name'],
            'package' => $package,
            'requires_php' => '8.2',
        ];
    }
}
