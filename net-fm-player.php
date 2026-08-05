<?php
/**
 * Plugin Name: NET FM Player
 * Description: Universal AzuraCast/direct-stream radio player. Provides [net_fm_player] shortcode and an Elementor widget.
 * Version: 1.3.1
 * Author: NET FM
 * License: GPL-2.0-or-later
 * Text Domain: net-fm-player
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Net_Fm_Player_Plugin
{
    public const VERSION = '1.3.1';
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
                    <?php $this->render_text_row('fallback_art', __('Fallback cover URL', 'net-fm-player'), $options['fallback_art'], 'url', __('Optional. Used only when metadata has no cover art.', 'net-fm-player')); ?>
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

    public function render_shortcode($atts = [])
    {
        $defaults = self::get_options();

        $atts = shortcode_atts(
            [
                'use_global' => 'yes',
                'api_url' => $defaults['api_url'],
                'azuracast_base_url' => $defaults['azuracast_base_url'],
                'station_shortcode' => $defaults['station_shortcode'],
                'stream_primary' => $defaults['stream_primary_url'],
                'stream_primary_url' => $defaults['stream_primary_url'],
                'stream_primary_label' => $defaults['stream_primary_label'],
                'stream_secondary' => $defaults['stream_secondary_url'],
                'stream_secondary_url' => $defaults['stream_secondary_url'],
                'stream_secondary_label' => $defaults['stream_secondary_label'],
                'default_stream' => $defaults['default_stream'],
                'fallback_art' => $defaults['fallback_art'],
                'station_label' => $defaults['station_label'],
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

        $default_stream = $atts['default_stream'] === 'secondary' ? 'secondary' : 'primary';
        $primary_label = sanitize_text_field($atts['stream_primary_label'] ?: __('Primary', 'net-fm-player'));
        $secondary_label = sanitize_text_field($atts['stream_secondary_label'] ?: __('Secondary', 'net-fm-player'));
        $station_label = sanitize_text_field($atts['station_label'] ?: __('Radio', 'net-fm-player'));
        $api_url = self::build_api_url($atts['api_url'], $atts['azuracast_base_url'], $atts['station_shortcode']);
        $fallback_art = esc_url_raw($atts['fallback_art']);

        $config = [
            'apiUrl' => $api_url,
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
            'fallbackArt' => $fallback_art,
            'stationLabel' => $station_label,
        ];

        $config_json = wp_json_encode($config);

        ob_start();
        ?>
        <div class="nfp" data-nfp data-config="<?php echo esc_attr($config_json); ?>">
            <div class="nfp__head">
                <span class="nfp__live" data-nfp-live><?php echo esc_html__('В эфире', 'net-fm-player'); ?></span>
                <span class="nfp__count" data-nfp-count><?php echo esc_html($station_label); ?></span>
            </div>

            <div class="nfp__cover <?php echo $fallback_art ? '' : 'is-placeholder'; ?>" data-nfp-cover>
                <img class="nfp__art" data-nfp-art <?php echo $fallback_art ? 'src="' . esc_url($fallback_art) . '"' : ''; ?> alt="" loading="eager">
                <div class="nfp__placeholder" data-nfp-placeholder><?php echo esc_html($station_label); ?></div>
                <canvas class="nfp__scope" data-nfp-scope width="640" height="160" aria-hidden="true"></canvas>
                <div class="nfp__eq" data-nfp-eq aria-hidden="true">
                    <?php for ($i = 0; $i < 24; $i++) : ?>
                        <i></i>
                    <?php endfor; ?>
                </div>
                <div class="nfp__eq-note" data-nfp-eq-note hidden><?php echo esc_html__('Для живого эквалайзера нужен CORS на потоке', 'net-fm-player'); ?></div>
            </div>

            <div class="nfp__info">
                <div class="nfp__kicker"><?php echo esc_html__('Сейчас играет', 'net-fm-player'); ?></div>
                <div class="nfp__title" data-nfp-title><?php echo esc_html__('Загрузка эфира...', 'net-fm-player'); ?></div>
                <div class="nfp__artist" data-nfp-artist><?php echo esc_html($station_label); ?></div>
            </div>

            <div class="nfp__panel">
                <button class="nfp__play" type="button" data-nfp-toggle aria-label="<?php echo esc_attr__('Включить радио', 'net-fm-player'); ?>">
                    <span class="nfp__play-icon" aria-hidden="true"></span>
                </button>

                <div class="nfp__quality" role="group" aria-label="<?php echo esc_attr__('Поток', 'net-fm-player'); ?>">
                    <button class="<?php echo $default_stream === 'primary' ? 'is-active' : ''; ?>" type="button" data-nfp-stream="primary"><?php echo esc_html($primary_label); ?></button>
                    <button class="<?php echo $default_stream === 'secondary' ? 'is-active' : ''; ?>" type="button" data-nfp-stream="secondary"><?php echo esc_html($secondary_label); ?></button>
                </div>

                <button class="nfp__mute" type="button" data-nfp-mute aria-label="<?php echo esc_attr__('Выключить звук', 'net-fm-player'); ?>">
                    <span class="nfp__mute-icon" aria-hidden="true"></span>
                </button>
            </div>

            <div class="nfp__volume">
                <span class="nfp__volume-label"><?php echo esc_html__('Громкость', 'net-fm-player'); ?></span>
                <input class="nfp__volume-range" data-nfp-volume type="range" min="0" max="1" step="0.01" value="0.85" aria-label="<?php echo esc_attr__('Громкость', 'net-fm-player'); ?>">
            </div>

            <div class="nfp__reactions" role="group" aria-label="<?php echo esc_attr__('Оценка трека', 'net-fm-player'); ?>">
                <button class="nfp__reaction" type="button" data-nfp-reaction="like" aria-pressed="false"><span class="nfp__reaction-icon nfp__reaction-icon--like" aria-hidden="true"></span><span><?php echo esc_html__('Лайк', 'net-fm-player'); ?></span></button>
                <button class="nfp__reaction" type="button" data-nfp-reaction="dislike" aria-pressed="false"><span class="nfp__reaction-icon nfp__reaction-icon--dislike" aria-hidden="true"></span><span><?php echo esc_html__('Дизлайк', 'net-fm-player'); ?></span></button>
            </div>

            <div class="nfp__error" data-nfp-error hidden><?php echo esc_html__('Поток не запустился. Нажмите play еще раз или переключите поток.', 'net-fm-player'); ?></div>

            <audio data-nfp-audio preload="auto"></audio>
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


