<?php
/**
 * Plugin Name: NET FM Player for AzuraCast
 * Plugin URI: https://vibeservice.ru/
 * Description: Universal AzuraCast/direct-stream radio player. Provides [net_fm_player] shortcode and an Elementor widget.
 * Version: 0.1.0-alpha
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: omhotey
 * Author URI: https://vibeservice.ru/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: net-fm-player
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Net_Fm_Player_Plugin
{
    public const VERSION = '0.1.0-alpha';
    public const SHORTCODE = 'net_fm_player';
    public const OPTION_NAME = 'net_fm_player_options';

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);
    }

    public static function defaults()
    {
        return [
            'station_label' => 'NET FM',
            'azuracast_base_url' => '',
            'station_shortcode' => '',
            'api_url' => '',
            'stream_primary_url' => '',
            'stream_primary_label' => 'Primary',
            'stream_secondary_url' => '',
            'stream_secondary_label' => 'Secondary',
            'default_stream' => 'primary',
            'fallback_art' => '',
            'theme' => 'studio',
            'background_color' => '',
            'play_button_color' => '',
            'volume_color' => '',
        ];
    }

    public static function get_options()
    {
        $saved = get_option(self::OPTION_NAME, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge(self::defaults(), $saved);
    }

    public static function build_api_url($api_url, $base_url, $shortcode)
    {
        $api_url = trim((string) $api_url);
        if ($api_url !== '') {
            return esc_url_raw($api_url);
        }

        $base_url = untrailingslashit(trim((string) $base_url));
        $shortcode = trim((string) $shortcode);

        if ($base_url === '' || $shortcode === '') {
            return '';
        }

        return esc_url_raw($base_url . '/api/nowplaying/' . rawurlencode($shortcode));
    }

    public function register_settings_page()
    {
        add_options_page(
            __('NET FM Player', 'net-fm-player'),
            __('NET FM Player', 'net-fm-player'),
            'manage_options',
            'net-fm-player',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting(
            'net_fm_player_settings',
            self::OPTION_NAME,
            [
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => self::defaults(),
            ]
        );
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        if ($hook_suffix !== 'settings_page_net-fm-player') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $script = sprintf(
            <<<'JS'
jQuery(function ($) {
    $('.nfp-color-field').wpColorPicker();

    $('.nfp-upload-image').on('click', function () {
        var button = $(this);
        var frame = wp.media({
            title: %s,
            button: { text: %s },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var row = button.closest('.nfp-image-field');
            row.find('input[type="url"]').val(attachment.url).trigger('change');
            row.find('.nfp-image-preview').attr('src', attachment.url).prop('hidden', false);
            row.find('.nfp-remove-image').prop('hidden', false);
        });

        frame.open();
    });

    $('.nfp-remove-image').on('click', function () {
        var row = $(this).closest('.nfp-image-field');
        row.find('input[type="url"]').val('').trigger('change');
        row.find('.nfp-image-preview').removeAttr('src').prop('hidden', true);
        $(this).prop('hidden', true);
    });
});
JS,
            wp_json_encode(__('Select station logo', 'net-fm-player')),
            wp_json_encode(__('Use this image', 'net-fm-player'))
        );

        wp_add_inline_script('wp-color-picker', $script);
    }

    private static function sanitize_color($value)
    {
        $color = sanitize_hex_color((string) $value);
        return is_string($color) ? $color : '';
    }

    public function sanitize_options($input)
    {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();

        return [
            'station_label' => sanitize_text_field($input['station_label'] ?? $defaults['station_label']),
            'azuracast_base_url' => esc_url_raw($input['azuracast_base_url'] ?? $defaults['azuracast_base_url']),
            'station_shortcode' => sanitize_text_field($input['station_shortcode'] ?? $defaults['station_shortcode']),
            'api_url' => esc_url_raw($input['api_url'] ?? $defaults['api_url']),
            'stream_primary_url' => esc_url_raw($input['stream_primary_url'] ?? $defaults['stream_primary_url']),
            'stream_primary_label' => sanitize_text_field($input['stream_primary_label'] ?? $defaults['stream_primary_label']),
            'stream_secondary_url' => esc_url_raw($input['stream_secondary_url'] ?? $defaults['stream_secondary_url']),
            'stream_secondary_label' => sanitize_text_field($input['stream_secondary_label'] ?? $defaults['stream_secondary_label']),
            'default_stream' => (($input['default_stream'] ?? $defaults['default_stream']) === 'secondary') ? 'secondary' : 'primary',
            'fallback_art' => esc_url_raw($input['fallback_art'] ?? $defaults['fallback_art']),
            'theme' => (($input['theme'] ?? $defaults['theme']) === 'vinyl') ? 'vinyl' : 'studio',
            'background_color' => self::sanitize_color($input['background_color'] ?? ''),
            'play_button_color' => self::sanitize_color($input['play_button_color'] ?? ''),
            'volume_color' => self::sanitize_color($input['volume_color'] ?? ''),
        ];
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = self::get_options();
        $computed_api_url = self::build_api_url($options['api_url'], $options['azuracast_base_url'], $options['station_shortcode']);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('NET FM Player Settings', 'net-fm-player'); ?></h1>
            <p><?php echo esc_html__('These are global defaults for [net_fm_player]. Elementor widgets can either use these defaults or override them locally.', 'net-fm-player'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('net_fm_player_settings'); ?>
                <table class="form-table" role="presentation">
                    <?php $this->render_text_row('station_label', __('Station label', 'net-fm-player'), $options['station_label']); ?>
                    <?php $this->render_text_row('azuracast_base_url', __('AzuraCast Base URL', 'net-fm-player'), $options['azuracast_base_url'], 'url', __('Example: https://stream.net-fm.ru', 'net-fm-player')); ?>
                    <?php $this->render_text_row('station_shortcode', __('AzuraCast station shortcode', 'net-fm-player'), $options['station_shortcode'], 'text', __('Example: myradio. Used to build /api/nowplaying/{shortcode}.', 'net-fm-player')); ?>
                    <?php $this->render_text_row('api_url', __('Explicit Now Playing API URL', 'net-fm-player'), $options['api_url'], 'url', __('Optional. If empty, the plugin uses Base URL + station shortcode.', 'net-fm-player')); ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Computed metadata URL', 'net-fm-player'); ?></th>
                        <td><code><?php echo esc_html($computed_api_url ?: __('not configured', 'net-fm-player')); ?></code></td>
                    </tr>
                    <?php $this->render_text_row('stream_primary_url', __('Primary stream URL', 'net-fm-player'), $options['stream_primary_url'], 'url'); ?>
                    <?php $this->render_text_row('stream_primary_label', __('Primary stream label', 'net-fm-player'), $options['stream_primary_label']); ?>
                    <?php $this->render_text_row('stream_secondary_url', __('Secondary stream URL', 'net-fm-player'), $options['stream_secondary_url'], 'url'); ?>
                    <?php $this->render_text_row('stream_secondary_label', __('Secondary stream label', 'net-fm-player'), $options['stream_secondary_label']); ?>
                    <tr>
                        <th scope="row"><label for="nfp-default-stream"><?php echo esc_html__('Default stream', 'net-fm-player'); ?></label></th>
                        <td>
                            <select id="nfp-default-stream" name="<?php echo esc_attr(self::OPTION_NAME); ?>[default_stream]">
                                <option value="primary" <?php selected($options['default_stream'], 'primary'); ?>><?php echo esc_html__('Primary', 'net-fm-player'); ?></option>
                                <option value="secondary" <?php selected($options['default_stream'], 'secondary'); ?>><?php echo esc_html__('Secondary', 'net-fm-player'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php $this->render_image_row('fallback_art', __('Default station logo', 'net-fm-player'), $options['fallback_art'], __('Displayed when AzuraCast does not provide track artwork.', 'net-fm-player')); ?>
                    <tr>
                        <th scope="row"><label for="nfp-theme"><?php echo esc_html__('Player theme', 'net-fm-player'); ?></label></th>
                        <td>
                            <select id="nfp-theme" name="<?php echo esc_attr(self::OPTION_NAME); ?>[theme]">
                                <option value="studio" <?php selected($options['theme'], 'studio'); ?>><?php echo esc_html__('Studio', 'net-fm-player'); ?></option>
                                <option value="vinyl" <?php selected($options['theme'], 'vinyl'); ?>><?php echo esc_html__('Vinyl', 'net-fm-player'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php $this->render_color_row('background_color', __('Player background color', 'net-fm-player'), $options['background_color']); ?>
                    <?php $this->render_color_row('play_button_color', __('Play/pause button color', 'net-fm-player'), $options['play_button_color']); ?>
                    <?php $this->render_color_row('volume_color', __('Volume slider color', 'net-fm-player'), $options['volume_color']); ?>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php echo esc_html__('Shortcode examples', 'net-fm-player'); ?></h2>
            <p><code>[net_fm_player]</code></p>
            <p><code>[net_fm_player station_label="My Radio" azuracast_base_url="https://stream.example.com" station_shortcode="myradio" stream_primary="https://stream.example.com/listen/myradio/high" stream_primary_label="HD"]</code></p>
        </div>
        <?php
    }

    private function render_text_row($key, $label, $value, $type = 'text', $description = '')
    {
        ?>
        <tr>
            <th scope="row"><label for="nfp-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input id="nfp-<?php echo esc_attr($key); ?>" class="regular-text" type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                <?php if ($description) : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function render_color_row($key, $label, $value)
    {
        ?>
        <tr>
            <th scope="row"><label for="nfp-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input id="nfp-<?php echo esc_attr($key); ?>" class="nfp-color-field" type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" data-default-color="">
                <p class="description"><?php echo esc_html__('Leave empty to use the selected theme default.', 'net-fm-player'); ?></p>
            </td>
        </tr>
        <?php
    }

    private function render_image_row($key, $label, $value, $description = '')
    {
        ?>
        <tr>
            <th scope="row"><label for="nfp-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <div class="nfp-image-field">
                    <input id="nfp-<?php echo esc_attr($key); ?>" class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                    <button class="button nfp-upload-image" type="button"><?php echo esc_html__('Choose image', 'net-fm-player'); ?></button>
                    <button class="button-link-delete nfp-remove-image" type="button"<?php echo $value ? '' : ' hidden'; ?>><?php echo esc_html__('Remove image', 'net-fm-player'); ?></button>
                    <p><img class="nfp-image-preview" src="<?php echo esc_url($value); ?>" alt="" style="max-width:160px;max-height:160px;object-fit:contain;"<?php echo $value ? '' : ' hidden'; ?>></p>
                </div>
                <?php if ($description) : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function register_assets()
    {
        $base_url = plugin_dir_url(__FILE__);

        wp_register_style('net-fm-player', $base_url . 'assets/net-fm-player.css', [], self::VERSION);
        wp_register_script('net-fm-player', $base_url . 'assets/net-fm-player.js', [], self::VERSION, true);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('net-fm-player');
        wp_enqueue_script('net-fm-player');
    }

    private function build_player_config($atts)
    {
        $default_stream = $atts['default_stream'] === 'secondary' ? 'secondary' : 'primary';
        $primary_label = sanitize_text_field($atts['stream_primary_label'] ?: __('Primary', 'net-fm-player'));
        $secondary_label = sanitize_text_field($atts['stream_secondary_label'] ?: __('Secondary', 'net-fm-player'));
        $station_label = sanitize_text_field($atts['station_label'] ?: __('Radio', 'net-fm-player'));

        $config = [
            'apiUrl' => self::build_api_url($atts['api_url'], $atts['azuracast_base_url'], $atts['station_shortcode']),
            'azuracastBaseUrl' => esc_url_raw($atts['azuracast_base_url']),
            'stationShortcode' => sanitize_text_field($atts['station_shortcode']),
            'streams' => [
                'primary' => esc_url_raw($atts['stream_primary_url']),
                'secondary' => esc_url_raw($atts['stream_secondary_url']),
            ],
            'streamLabels' => [
                'primary' => $primary_label,
                'secondary' => $secondary_label,
            ],
            'defaultStream' => $default_stream,
            'fallbackArt' => esc_url_raw($atts['fallback_art']),
            'stationLabel' => $station_label,
            'theme' => $atts['theme'] === 'vinyl' ? 'vinyl' : 'studio',
            'colors' => [
                'background' => self::sanitize_color($atts['background_color']),
                'playButton' => self::sanitize_color($atts['play_button_color']),
                'volume' => self::sanitize_color($atts['volume_color']),
            ],
        ];

        if (empty($config['streams'][$config['defaultStream']])) {
            $config['defaultStream'] = empty($config['streams']['primary']) ? 'secondary' : 'primary';
        }

        return $config;
    }

    private function build_player_style($colors)
    {
        $styles = [];

        if (!empty($colors['background'])) {
            $styles[] = '--nfp-player-background:' . $colors['background'];
        }

        if (!empty($colors['playButton'])) {
            $styles[] = '--nfp-play-color:' . $colors['playButton'];
            $styles[] = '--nfp-play-contrast:' . $this->contrast_color($colors['playButton']);
        }

        if (!empty($colors['volume'])) {
            $styles[] = '--nfp-volume-color:' . $colors['volume'];
        }

        return implode(';', $styles);
    }

    private function contrast_color($hex_color)
    {
        $hex_color = ltrim((string) $hex_color, '#');
        if (strlen($hex_color) !== 6) {
            return '#ffffff';
        }

        $red = hexdec(substr($hex_color, 0, 2));
        $green = hexdec(substr($hex_color, 2, 2));
        $blue = hexdec(substr($hex_color, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 150 ? '#061006' : '#ffffff';
    }

    public function render_shortcode($atts = [])
    {
        $use_global = ($atts['use_global'] ?? 'yes') !== 'no';
        $defaults = $use_global ? self::get_options() : self::defaults();

        $atts = shortcode_atts(
            [
                'use_global' => 'yes',
                'api_url' => $defaults['api_url'],
                'azuracast_base_url' => $defaults['azuracast_base_url'],
                'station_shortcode' => $defaults['station_shortcode'],
                'stream_primary' => '',
                'stream_primary_url' => $defaults['stream_primary_url'],
                'stream_primary_label' => $defaults['stream_primary_label'],
                'stream_secondary' => '',
                'stream_secondary_url' => $defaults['stream_secondary_url'],
                'stream_secondary_label' => $defaults['stream_secondary_label'],
                'default_stream' => $defaults['default_stream'],
                'fallback_art' => $defaults['fallback_art'],
                'station_label' => $defaults['station_label'],
                'theme' => $defaults['theme'],
                'background_color' => $defaults['background_color'],
                'play_button_color' => $defaults['play_button_color'],
                'volume_color' => $defaults['volume_color'],
            ],
            $atts,
            self::SHORTCODE
        );

        if (!empty($atts['stream_primary'])) {
            $atts['stream_primary_url'] = $atts['stream_primary'];
        }

        if (!empty($atts['stream_secondary'])) {
            $atts['stream_secondary_url'] = $atts['stream_secondary'];
        }

        $this->enqueue_assets();
        $config = $this->build_player_config($atts);
        $config_json = wp_json_encode($config);
        $has_streams = !empty($config['streams']['primary']) || !empty($config['streams']['secondary']);
        $root_classes = ['nfp', 'nfp--theme-' . $config['theme']];
        $initial_live_label = __('Проверка эфира...', 'net-fm-player');
        $initial_count_label = $config['stationLabel'];
        $initial_title = __('Загрузка метаданных...', 'net-fm-player');
        $initial_artist = '';

        if (!$config['apiUrl']) {
            if ($has_streams) {
                $root_classes[] = 'is-live';
                $initial_live_label = sprintf(__('В эфире %s', 'net-fm-player'), $config['stationLabel']);
                $initial_count_label = '';
                $initial_title = $config['stationLabel'];
                $initial_artist = __('Прямой поток', 'net-fm-player');
            } else {
                $root_classes[] = 'is-offline';
                $initial_live_label = sprintf(__('Не настроено: %s', 'net-fm-player'), $config['stationLabel']);
                $initial_count_label = '';
                $initial_title = $config['stationLabel'];
            }
        }

        $player_style = $this->build_player_style($config['colors']);

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $root_classes)); ?>" data-nfp data-config="<?php echo esc_attr($config_json); ?>"<?php echo $player_style ? ' style="' . esc_attr($player_style) . '"' : ''; ?>>
            <div class="nfp__head">
                <span class="nfp__live" data-nfp-live role="status" aria-live="polite"><?php echo esc_html($initial_live_label); ?></span>
                <span class="nfp__count" data-nfp-count><?php echo esc_html($initial_count_label); ?></span>
            </div>

            <div class="nfp__cover <?php echo $config['fallbackArt'] ? 'is-fallback-art' : 'is-placeholder'; ?>" data-nfp-cover>
                <img class="nfp__art" data-nfp-art <?php echo $config['fallbackArt'] ? 'src="' . esc_url($config['fallbackArt']) . '"' : ''; ?> alt="" loading="eager">
                <div class="nfp__placeholder" data-nfp-placeholder><?php echo esc_html($config['stationLabel']); ?></div>
                <canvas class="nfp__scope" data-nfp-scope width="640" height="160" aria-hidden="true"></canvas>
                <div class="nfp__eq" data-nfp-eq aria-hidden="true">
                    <?php for ($i = 0; $i < 24; $i++) : ?>
                        <i></i>
                    <?php endfor; ?>
                </div>
                <div class="nfp__eq-note" data-nfp-eq-note hidden><?php echo esc_html__('Визуализация эфира', 'net-fm-player'); ?></div>
            </div>
            <div class="nfp__vinyl-arm" aria-hidden="true">
                <span class="nfp__vinyl-arm-body"><span class="nfp__vinyl-head"></span></span>
            </div>

            <div class="nfp__info">
                <div class="nfp__kicker"><?php echo esc_html__('Сейчас играет', 'net-fm-player'); ?></div>
                <div class="nfp__title" data-nfp-title><?php echo esc_html($initial_title); ?></div>
                <div class="nfp__artist" data-nfp-artist><?php echo esc_html($initial_artist); ?></div>
            </div>

            <div class="nfp__panel">
                <button class="nfp__play" type="button" data-nfp-toggle aria-label="<?php echo esc_attr__('Включить радио', 'net-fm-player'); ?>"<?php disabled(!$has_streams); ?>>
                    <span class="nfp__play-icon" aria-hidden="true"></span>
                </button>

                <div class="nfp__quality" role="group" aria-label="<?php echo esc_attr__('Поток', 'net-fm-player'); ?>">
                    <?php foreach ($config['streams'] as $stream_key => $stream_url) : ?>
                        <?php if (!$stream_url) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <button class="<?php echo $config['defaultStream'] === $stream_key ? 'is-active' : ''; ?>" type="button" data-nfp-stream="<?php echo esc_attr($stream_key); ?>" aria-pressed="<?php echo $config['defaultStream'] === $stream_key ? 'true' : 'false'; ?>"><?php echo esc_html($config['streamLabels'][$stream_key]); ?></button>
                    <?php endforeach; ?>
                </div>

                <button class="nfp__mute" type="button" data-nfp-mute aria-label="<?php echo esc_attr__('Выключить звук', 'net-fm-player'); ?>" aria-pressed="false">
                    <svg class="nfp__mute-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path class="nfp__mute-speaker" d="M4 10v4h4l5 5V5l-5 5H4z"></path>
                        <path class="nfp__mute-wave" d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05A4.47 4.47 0 0 0 16.5 12zm-2.5-8.77v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.5 7-8.77s-2.99-7.86-7-8.77z"></path>
                        <path class="nfp__mute-slash" d="M4 4l16 16"></path>
                    </svg>
                </button>
            </div>

            <div class="nfp__volume">
                <span class="nfp__volume-label"><?php echo esc_html__('Громкость', 'net-fm-player'); ?></span>
                <input class="nfp__volume-range" data-nfp-volume type="range" min="0" max="1" step="0.01" value="0.85" aria-label="<?php echo esc_attr__('Громкость', 'net-fm-player'); ?>">
            </div>

            <div class="nfp__error" data-nfp-error role="alert" hidden><?php echo esc_html__('Поток не запустился. Нажмите play еще раз или переключите поток.', 'net-fm-player'); ?></div>

            <audio data-nfp-audio preload="none"></audio>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function register_elementor_widget($widgets_manager)
    {
        if (!did_action('elementor/loaded')) {
            return;
        }

        require_once __DIR__ . '/includes/class-net-fm-elementor-widget.php';
        $widgets_manager->register(new Net_Fm_Player_Elementor_Widget());
    }
}

Net_Fm_Player_Plugin::instance();


