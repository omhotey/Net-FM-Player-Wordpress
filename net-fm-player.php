<?php
/**
 * Plugin Name: NET FM Player for AzuraCast
 * Plugin URI: https://vibeservice.ru/
 * Description: AzuraCast and direct-stream radio player with shortcode, Gutenberg and Elementor integrations.
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: Юрий Струк (omhotey)
 * Author URI: https://vibeservice.ru/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: net-fm-player
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Net_Fm_Player_Plugin
{
    public const VERSION = '0.3.0';
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
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this, 'register_block'], 11);
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_station_api']);
    }

    public static function defaults()
    {
        return [
            'station_label' => 'NET FM',
            'azuracast_base_url' => '',
            'station_shortcode' => '',
            'api_url' => '',
            'stream_primary_url' => '',
            'stream_primary_label' => 'Основной',
            'stream_secondary_url' => '',
            'stream_secondary_label' => 'Дополнительный',
            'stream_hls_url' => '',
            'default_stream' => 'primary',
            'fallback_art' => '',
            'theme' => 'vinyl',
            'layout' => 'horizontal',
            'background_color' => '',
            'play_button_color' => '',
            'volume_color' => '',
            'stations' => [],
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

    public function register_station_api()
    {
        register_rest_route('net-fm-player/v1', '/stations', [
            'methods' => 'GET',
            'callback' => [$this, 'public_stations'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function public_stations()
    {
        $result = [];
        $stations = self::get_options()['stations'];
        foreach (is_array($stations) ? $stations : [] as $id => $station) {
            if (!is_array($station) || (isset($station['frontend_enabled']) && !$station['frontend_enabled'])) {
                continue;
            }
            $station = array_merge(self::defaults(), $station);
            $result[] = [
                'id' => sanitize_key($id),
                'name' => sanitize_text_field($station['station_label']),
                'api_url' => self::build_api_url($station['api_url'], $station['azuracast_base_url'], $station['station_shortcode']),
                'stream_primary_url' => esc_url_raw($station['stream_primary_url']),
                'stream_primary_label' => sanitize_text_field($station['stream_primary_label'] ?? ''),
                'stream_secondary_url' => esc_url_raw($station['stream_secondary_url']),
                'stream_secondary_label' => sanitize_text_field($station['stream_secondary_label'] ?? ''),
                'stream_hls_url' => esc_url_raw($station['stream_hls_url']),
                'logo_url' => esc_url_raw($station['fallback_art']),
            ];
        }
        return rest_ensure_response(['stations' => $result]);
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
        wp_enqueue_style('net-fm-player-admin', plugin_dir_url(__FILE__) . 'assets/net-fm-player-admin.css', [], self::VERSION);
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
        // options.php checks the settings nonce before invoking this callback.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'net_fm_player_settings' && empty($input['submission_complete'])) {
            add_settings_error(self::OPTION_NAME, 'nfp_incomplete', __('Настройки не сохранены: форма была передана не полностью. Проверьте ограничение max_input_vars на сервере.', 'net-fm-player'), 'error');
            return self::get_options();
        }
        $defaults = self::defaults();

        $stations = [];
        foreach ((is_array($input['stations'] ?? null) ? $input['stations'] : []) as $id => $station) {
            $id = sanitize_key($id);
            if (!$id || !is_array($station) || !empty($station['delete'])) {
                continue;
            }
            $label = sanitize_text_field($station['station_label'] ?? '');
            if (!$label) {
                continue;
            }
            if ($id === 'new') {
                do {
                    $id = 'station-' . wp_generate_uuid4();
                } while (isset($input['stations'][$id]) || isset($stations[$id]));
            }
            $stations[$id] = [
                'station_label' => $label,
                'azuracast_base_url' => esc_url_raw($station['azuracast_base_url'] ?? ''),
                'station_shortcode' => sanitize_text_field($station['station_shortcode'] ?? ''),
                'api_url' => esc_url_raw($station['api_url'] ?? ''),
                'stream_primary_url' => esc_url_raw($station['stream_primary_url'] ?? ''),
                'stream_primary_label' => sanitize_text_field($station['stream_primary_label'] ?? ''),
                'stream_secondary_url' => esc_url_raw($station['stream_secondary_url'] ?? ''),
                'stream_secondary_label' => sanitize_text_field($station['stream_secondary_label'] ?? ''),
                'stream_hls_url' => esc_url_raw($station['stream_hls_url'] ?? ''),
                'fallback_art' => esc_url_raw($station['fallback_art'] ?? ''),
                'frontend_enabled' => !isset($station['frontend_enabled']) || (string) $station['frontend_enabled'] === '1',
            ];
        }
        return [
            'station_label' => sanitize_text_field($input['station_label'] ?? $defaults['station_label']),
            'azuracast_base_url' => esc_url_raw($input['azuracast_base_url'] ?? $defaults['azuracast_base_url']),
            'station_shortcode' => sanitize_text_field($input['station_shortcode'] ?? $defaults['station_shortcode']),
            'api_url' => esc_url_raw($input['api_url'] ?? $defaults['api_url']),
            'stream_primary_url' => esc_url_raw($input['stream_primary_url'] ?? $defaults['stream_primary_url']),
            'stream_primary_label' => sanitize_text_field($input['stream_primary_label'] ?? $defaults['stream_primary_label']),
            'stream_secondary_url' => esc_url_raw($input['stream_secondary_url'] ?? $defaults['stream_secondary_url']),
            'stream_secondary_label' => sanitize_text_field($input['stream_secondary_label'] ?? $defaults['stream_secondary_label']),
            'stream_hls_url' => esc_url_raw($input['stream_hls_url'] ?? $defaults['stream_hls_url']),
            'default_stream' => in_array(($input['default_stream'] ?? $defaults['default_stream']), ['primary', 'secondary', 'hls'], true) ? ($input['default_stream'] ?? $defaults['default_stream']) : 'primary',
            'fallback_art' => esc_url_raw($input['fallback_art'] ?? $defaults['fallback_art']),
            'theme' => (($input['theme'] ?? $defaults['theme']) === 'vinyl') ? 'vinyl' : 'studio',
            'layout' => (($input['layout'] ?? $defaults['layout']) === 'vertical') ? 'vertical' : 'horizontal',
            'background_color' => self::sanitize_color($input['background_color'] ?? ''),
            'play_button_color' => self::sanitize_color($input['play_button_color'] ?? ''),
            'volume_color' => self::sanitize_color($input['volume_color'] ?? ''),
            'stations' => $stations,
        ];
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = self::get_options();
        $computed_api_url = self::build_api_url($options['api_url'], $options['azuracast_base_url'], $options['station_shortcode']);
        $has_stream = !empty($options['stream_primary_url']) || !empty($options['stream_secondary_url']) || !empty($options['stream_hls_url']);
        $is_ready = $has_stream;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Настройки NET FM Player', 'net-fm-player'); ?></h1>
            <p><?php echo esc_html__('Глобальные настройки для shortcode, блока Gutenberg и виджета Elementor.', 'net-fm-player'); ?></p>

            <?php if ($is_ready) : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html__('Указан URL потока. Его доступность и настройки браузера необходимо проверить отдельно.', 'net-fm-player'); ?></p></div>
            <?php elseif (!empty($options['stations'])) : ?>
                <div class="notice notice-info inline"><p><?php echo esc_html__('Каналы добавлены ниже. Для shortcode без указания канала настройте также глобальный поток.', 'net-fm-player'); ?></p></div>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php echo esc_html__('Плеер пока не настроен.', 'net-fm-player'); ?></strong> <?php echo esc_html__('Укажите хотя бы один URL потока. Метаданные AzuraCast можно настроить отдельно.', 'net-fm-player'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('net_fm_player_settings'); ?>
                <section class="nfp-admin-stations">
                    <h2><?php echo esc_html__('Радиостанции', 'net-fm-player'); ?></h2>
                    <p><?php echo esc_html__('Добавьте каналы и отметьте те, которые слушатели смогут выбрать в плеере. Для отдельной станции её можно указать напрямую.', 'net-fm-player'); ?></p>
                    <p><?php echo esc_html__('Адреса включённых станций доступны посетителям сайта и приложению. Указывайте только публичные URL без паролей и ключей.', 'net-fm-player'); ?></p>
                    <p><code>[net_fm_player stations="all"]</code> — <?php echo esc_html__('плеер с выбранными станциями', 'net-fm-player'); ?></p>
                    <div class="nfp-admin-station-grid">
                        <?php foreach ((array) $options['stations'] as $station_id => $station) : ?>
                            <?php $this->render_station_fields($station_id, $station, true); ?>
                        <?php endforeach; ?>
                        <?php $this->render_station_fields('new', [], false); ?>
                    </div>
                </section>
                <details class="nfp-admin-global">
                    <summary><?php echo esc_html__('Общие настройки плеера и прежней станции', 'net-fm-player'); ?></summary>
                <table class="form-table" role="presentation">
                    <?php $this->render_section_row(__('Станция и metadata', 'net-fm-player')); ?>
                    <?php $this->render_text_row('station_label', __('Название станции', 'net-fm-player'), $options['station_label']); ?>
                    <?php $this->render_text_row('azuracast_base_url', __('Base URL AzuraCast', 'net-fm-player'), $options['azuracast_base_url'], 'url', __('Например: https://stream.net-fm.ru', 'net-fm-player')); ?>
                    <?php $this->render_text_row('station_shortcode', __('Shortcode станции AzuraCast', 'net-fm-player'), $options['station_shortcode'], 'text', __('Например: myradio. Используется для /api/nowplaying/{shortcode}.', 'net-fm-player')); ?>
                    <?php $this->render_text_row('api_url', __('Явный URL Now Playing API', 'net-fm-player'), $options['api_url'], 'url', __('Необязательно. Если поле пустое, URL строится из Base URL и shortcode станции.', 'net-fm-player')); ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Итоговый metadata URL', 'net-fm-player'); ?></th>
                        <td><code><?php echo esc_html($computed_api_url ?: __('не настроен', 'net-fm-player')); ?></code></td>
                    </tr>
                    <?php $this->render_section_row(__('Потоки', 'net-fm-player')); ?>
                    <?php $this->render_text_row('stream_primary_url', __('URL основного потока', 'net-fm-player'), $options['stream_primary_url'], 'url'); ?>
                    <?php $this->render_text_row('stream_primary_label', __('Название основного потока', 'net-fm-player'), $options['stream_primary_label']); ?>
                    <?php $this->render_text_row('stream_secondary_url', __('URL дополнительного потока', 'net-fm-player'), $options['stream_secondary_url'], 'url'); ?>
                    <?php $this->render_text_row('stream_secondary_label', __('Название дополнительного потока', 'net-fm-player'), $options['stream_secondary_label']); ?>
                    <?php $this->render_text_row('stream_hls_url', __('URL HLS-потока', 'net-fm-player'), $options['stream_hls_url'], 'url', __('Необязательно. HLS показывается только в браузерах с нативной поддержкой.', 'net-fm-player')); ?>
                    <tr>
                        <th scope="row"><label for="nfp-default-stream"><?php echo esc_html__('Поток по умолчанию', 'net-fm-player'); ?></label></th>
                        <td>
                            <select id="nfp-default-stream" name="<?php echo esc_attr(self::OPTION_NAME); ?>[default_stream]">
                                 <option value="primary" <?php selected($options['default_stream'], 'primary'); ?>><?php echo esc_html__('Primary', 'net-fm-player'); ?></option>
                                 <option value="secondary" <?php selected($options['default_stream'], 'secondary'); ?>><?php echo esc_html__('Secondary', 'net-fm-player'); ?></option>
                                  <option value="hls" <?php selected($options['default_stream'], 'hls'); ?>><?php echo esc_html__('Auto (HLS)', 'net-fm-player'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Если выбранный URL пуст, плеер автоматически использует первый доступный поток.', 'net-fm-player'); ?></p>
                        </td>
                    </tr>
                    <?php $this->render_image_row('fallback_art', __('Логотип станции по умолчанию', 'net-fm-player'), $options['fallback_art'], __('Показывается, когда AzuraCast не передаёт обложку текущего трека.', 'net-fm-player')); ?>
                    <?php $this->render_section_row(__('Оформление', 'net-fm-player')); ?>
                    <tr>
                        <th scope="row"><label for="nfp-theme"><?php echo esc_html__('Тема плеера', 'net-fm-player'); ?></label></th>
                        <td>
                            <select id="nfp-theme" name="<?php echo esc_attr(self::OPTION_NAME); ?>[theme]">
                                <option value="vinyl" <?php selected($options['theme'], 'vinyl'); ?>><?php echo esc_html__('Vinyl', 'net-fm-player'); ?></option>
                                <option value="studio" <?php selected($options['theme'], 'studio'); ?>><?php echo esc_html__('Studio', 'net-fm-player'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Vinyl - рекомендуемая тема. Studio остаётся доступной для существующих и новых вставок.', 'net-fm-player'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nfp-layout"><?php echo esc_html__('Компоновка плеера', 'net-fm-player'); ?></label></th>
                        <td>
                            <select id="nfp-layout" name="<?php echo esc_attr(self::OPTION_NAME); ?>[layout]">
                                <option value="horizontal" <?php selected($options['layout'], 'horizontal'); ?>><?php echo esc_html__('Horizontal', 'net-fm-player'); ?></option>
                                <option value="vertical" <?php selected($options['layout'], 'vertical'); ?>><?php echo esc_html__('Vertical', 'net-fm-player'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php $this->render_color_row('background_color', __('Player background color', 'net-fm-player'), $options['background_color']); ?>
                    <?php $this->render_color_row('play_button_color', __('Play/pause button color', 'net-fm-player'), $options['play_button_color']); ?>
                    <?php $this->render_color_row('volume_color', __('Volume slider color', 'net-fm-player'), $options['volume_color']); ?>
                </table>
                </details>
                <input type="hidden" name="<?php echo esc_attr(self::OPTION_NAME); ?>[submission_complete]" value="1">
                <?php submit_button(); ?>
            </form>

            <h2><?php echo esc_html__('Shortcode examples', 'net-fm-player'); ?></h2>
            <p><code>[net_fm_player]</code></p>
            <p><code>[net_fm_player station_label="My Radio" azuracast_base_url="https://stream.example.com" station_shortcode="myradio" stream_primary="https://stream.example.com/listen/myradio/high" stream_primary_label="HD"]</code></p>
        </div>
        <?php
    }

    private function render_station_fields($id, $station, $existing)
    {
        $station = is_array($station) ? $station : [];
        $fields = [
            'station_label' => __('Название', 'net-fm-player'),
            'azuracast_base_url' => __('Base URL AzuraCast', 'net-fm-player'),
            'station_shortcode' => __('Shortcode станции AzuraCast', 'net-fm-player'),
            'api_url' => __('URL metadata API', 'net-fm-player'),
            'stream_primary_url' => __('Основной поток', 'net-fm-player'),
            'stream_primary_label' => __('Название основного потока, например 128 кбит/с', 'net-fm-player'),
            'stream_secondary_url' => __('Дополнительный поток (качество или резерв)', 'net-fm-player'),
            'stream_secondary_label' => __('Название дополнительного потока, например 320 кбит/с', 'net-fm-player'),
            'stream_hls_url' => __('HLS-поток', 'net-fm-player'),
            'fallback_art' => __('URL логотипа', 'net-fm-player'),
        ];
        // New rows receive a stable identifier at save time.
        $prefix = self::OPTION_NAME . '[stations][' . $id . ']';
        ?>
        <fieldset class="nfp-admin-station-card">
            <legend><?php echo esc_html($existing ? ($station['station_label'] ?? $id) : __('Новая станция', 'net-fm-player')); ?></legend>
            <?php if ($existing) : ?><p class="nfp-admin-station-id"><?php echo esc_html__('Идентификатор:', 'net-fm-player'); ?> <code><?php echo esc_html($id); ?></code></p><?php endif; ?>
            <label class="nfp-admin-station-toggle"><input type="hidden" name="<?php echo esc_attr($prefix . '[frontend_enabled]'); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr($prefix . '[frontend_enabled]'); ?>" value="1" <?php checked(!isset($station['frontend_enabled']) || $station['frontend_enabled']); ?>> <?php echo esc_html__('Включить в выбор на сайте', 'net-fm-player'); ?></label>
            <?php foreach ($fields as $key => $label) : ?>
                <p class="nfp-admin-station-field"><label><?php echo esc_html($label); ?><br>
                    <input class="regular-text" type="<?php echo strpos($key, 'url') !== false || $key === 'fallback_art' ? 'url' : 'text'; ?>" name="<?php echo esc_attr($prefix . '[' . $key . ']'); ?>" value="<?php echo esc_attr($station[$key] ?? ''); ?>">
                </label></p>
            <?php endforeach; ?>
            <?php if ($existing) : ?>
                <label class="nfp-admin-station-delete"><input type="checkbox" name="<?php echo esc_attr($prefix . '[delete]'); ?>" value="1"> <?php echo esc_html__('Удалить станцию после сохранения', 'net-fm-player'); ?></label>
            <?php endif; ?>
        </fieldset>
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

    private function render_section_row($label)
    {
        ?>
        <tr>
            <th colspan="2" scope="col"><h2 style="margin:24px 0 4px;"><?php echo esc_html($label); ?></h2></th>
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
        $style_path = __DIR__ . '/assets/net-fm-player.css';
        $script_path = __DIR__ . '/assets/net-fm-player.js';

        // File timestamps keep live bind-mounted development assets fresh in browsers.
        wp_register_style('net-fm-player', $base_url . 'assets/net-fm-player.css', [], (string) filemtime($style_path));
        wp_register_script('net-fm-player', $base_url . 'assets/net-fm-player.js', [], (string) filemtime($script_path), true);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('net-fm-player');
        wp_enqueue_script('net-fm-player');
    }

    public function register_block()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(__DIR__, [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function render_block($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $atts = ['use_global' => 'yes'];
        if (!empty($attributes['stations'])) {
            $atts['stations'] = $attributes['stations'];
        } elseif (!empty($attributes['station'])) {
            $atts['station'] = $attributes['station'];
        }

        if (isset($attributes['theme']) && in_array($attributes['theme'], ['studio', 'vinyl'], true)) {
            $atts['theme'] = $attributes['theme'];
        }

        if (isset($attributes['layout']) && in_array($attributes['layout'], ['horizontal', 'vertical'], true)) {
            $atts['layout'] = $attributes['layout'];
        }

        return '<div ' . get_block_wrapper_attributes() . '>' . $this->render_shortcode($atts) . '</div>';
    }

    private function build_player_config($atts, $station_label_override)
    {
        $default_stream = in_array($atts['default_stream'], ['primary', 'secondary', 'hls'], true) ? $atts['default_stream'] : 'primary';
        $primary_label = sanitize_text_field($atts['stream_primary_label'] ?: __('Основной', 'net-fm-player'));
        $secondary_label = sanitize_text_field($atts['stream_secondary_label'] ?: __('Дополнительный', 'net-fm-player'));
        $station_label = sanitize_text_field($atts['station_label'] ?: __('Radio', 'net-fm-player'));

        $config = [
            'apiUrl' => self::build_api_url($atts['api_url'], $atts['azuracast_base_url'], $atts['station_shortcode']),
            'azuracastBaseUrl' => esc_url_raw($atts['azuracast_base_url']),
            'stationShortcode' => sanitize_text_field($atts['station_shortcode']),
            'streams' => [
                'primary' => esc_url_raw($atts['stream_primary_url']),
                'secondary' => esc_url_raw($atts['stream_secondary_url']),
                'hls' => esc_url_raw($atts['stream_hls_url']),
            ],
            'streamLabels' => [
                'primary' => $primary_label,
                'secondary' => $secondary_label,
                'hls' => __('Auto', 'net-fm-player'),
            ],
            'defaultStream' => $default_stream,
            'fallbackArt' => esc_url_raw($atts['fallback_art']),
            'stationLabel' => $station_label,
            'stationLabelOverride' => $station_label_override,
            'theme' => $atts['theme'] === 'vinyl' ? 'vinyl' : 'studio',
            'layout' => $atts['layout'] === 'vertical' ? 'vertical' : 'horizontal',
            'colors' => [
                'background' => self::sanitize_color($atts['background_color']),
                'playButton' => self::sanitize_color($atts['play_button_color']),
                'volume' => self::sanitize_color($atts['volume_color']),
            ],
        ];

        if (empty($config['streams'][$config['defaultStream']])) {
            foreach (['primary', 'secondary', 'hls'] as $stream_key) {
                if (!empty($config['streams'][$stream_key])) {
                    $config['defaultStream'] = $stream_key;
                    break;
                }
            }
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
        $raw_atts = is_array($atts) ? $atts : [];
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
                'stream_hls_url' => $defaults['stream_hls_url'],
                'default_stream' => $defaults['default_stream'],
                'fallback_art' => $defaults['fallback_art'],
                'station_label' => $defaults['station_label'],
                'theme' => $defaults['theme'],
                'layout' => $defaults['layout'],
                'background_color' => $defaults['background_color'],
                'play_button_color' => $defaults['play_button_color'],
                'volume_color' => $defaults['volume_color'],
                'station' => '',
                'stations' => '',
            ],
            $atts,
            self::SHORTCODE
        );

        $available_stations = self::get_options()['stations'];
        $available_stations = is_array($available_stations) ? $available_stations : [];
        if ($atts['stations'] !== '') {
            $ids = $atts['stations'] === 'all'
                ? array_keys($available_stations)
                : array_values(array_unique(array_filter(array_map('sanitize_key', explode(',', $atts['stations'])))));
            if (!$ids || count(array_diff($ids, array_keys($available_stations)))) {
                return '<p class="nfp-error">' . esc_html__('Одна или несколько станций не найдены в настройках плеера.', 'net-fm-player') . '</p>';
            }
            $ids = array_values(array_filter($ids, static function ($id) use ($available_stations) {
                return !isset($available_stations[$id]['frontend_enabled']) || (bool) $available_stations[$id]['frontend_enabled'];
            }));
            if (!$ids) {
                return '<p class="nfp-error">' . esc_html__('Нет станций, включённых для выбора на сайте.', 'net-fm-player') . '</p>';
            }
            if (count($ids) === 1) {
                $atts['station'] = $ids[0];
            } else {
                $output = '<div class="nfp-station-switcher" data-nfp-switcher><div data-nfp-station-current>';
                foreach ($ids as $index => $id) {
                    $child = $raw_atts;
                    unset($child['stations']);
                    $child['station'] = $id;
                    $html = $this->render_shortcode($child);
                    $menu = '<div class="nfp__station-menu"><button class="nfp__menu-slot" type="button" data-nfp-menu-toggle aria-label="' . esc_attr__('Выбрать радиостанцию', 'net-fm-player') . '" aria-expanded="false"><i></i><i></i><i></i></button><div class="nfp__station-list" data-nfp-station-list hidden>';
                    foreach ($ids as $menu_id) {
                        $menu .= '<button type="button" data-nfp-station-target="' . esc_attr($menu_id) . '"' . ($menu_id === $id ? ' aria-current="true"' : '') . '>' . esc_html($available_stations[$menu_id]['station_label']) . '</button>';
                    }
                    $menu .= '</div></div>';
                    $placeholder = '<button class="nfp__menu-slot" type="button" aria-label="' . esc_attr__('Выбрать радиостанцию', 'net-fm-player') . '" disabled><i></i><i></i><i></i></button>';
                    $html = str_replace($placeholder, $menu, $html);
                    if ($index === 0) {
                        $output .= $html . '</div>';
                    }
                    $output .= '<template data-nfp-station-template="' . esc_attr($id) . '">' . $html . '</template>';
                }
                return $output . '</div>';
            }
        }
        if ($atts['station'] !== '') {
            $id = sanitize_key($atts['station']);
            if (!isset($available_stations[$id])) {
                return '<p class="nfp-error">' . esc_html__('Станция не найдена в настройках плеера.', 'net-fm-player') . '</p>';
            }
            $station = array_merge(self::defaults(), $available_stations[$id]);
            foreach (['station_label', 'azuracast_base_url', 'station_shortcode', 'api_url', 'stream_primary_url', 'stream_primary_label', 'stream_secondary_url', 'stream_secondary_label', 'stream_hls_url', 'fallback_art'] as $key) {
                $atts[$key] = $station[$key];
            }
            $atts['stream_primary'] = '';
            $atts['stream_secondary'] = '';
        }

        if (!empty($atts['stream_primary'])) {
            $atts['stream_primary_url'] = $atts['stream_primary'];
        }

        if (!empty($atts['stream_secondary'])) {
            $atts['stream_secondary_url'] = $atts['stream_secondary'];
        }

        $this->enqueue_assets();
        // A selected station or an explicitly supplied label wins over metadata.
        // The untouched default is a placeholder, so AzuraCast may replace it.
        $station_label_override = $atts['station'] !== ''
            || (isset($raw_atts['station_label']) && trim((string) $raw_atts['station_label']) !== '')
            || trim((string) $atts['station_label']) !== self::defaults()['station_label'];
        $config = $this->build_player_config($atts, $station_label_override);
        $config_json = wp_json_encode($config);
        $has_streams = !empty($config['streams']['primary'])
            || !empty($config['streams']['secondary'])
            || !empty($config['streams']['hls']);
        $root_classes = ['nfp', 'nfp--theme-' . $config['theme'], 'nfp--layout-' . $config['layout']];
        $initial_live_label = __('Проверка эфира...', 'net-fm-player');
        $initial_count_label = __('— СЛУШАЮТ', 'net-fm-player');
        $initial_title = __('Идёт загрузка', 'net-fm-player');
        $initial_artist = '';

        if (!$config['apiUrl']) {
            if ($has_streams) {
                $initial_live_label = __('Состояние эфира неизвестно', 'net-fm-player');
                $initial_count_label = __('— СЛУШАЮТ', 'net-fm-player');
                $initial_title = $config['stationLabel'];
                $initial_artist = __('Прямой поток', 'net-fm-player');
            } else {
                $root_classes[] = 'is-offline';
                /* translators: %s is the station name. */
                $initial_live_label = sprintf(__('Не настроено: %s', 'net-fm-player'), $config['stationLabel']);
                $initial_count_label = __('— СЛУШАЮТ', 'net-fm-player');
                $initial_title = $config['stationLabel'];
            }
        }

        $player_style = $this->build_player_style($config['colors']);

        ob_start();
        ?>
        <div class="nfp-container">
            <div class="<?php echo esc_attr(implode(' ', $root_classes)); ?>" data-nfp data-config="<?php echo esc_attr($config_json); ?>"<?php echo $player_style ? ' style="' . esc_attr($player_style) . '"' : ''; ?>>
            <div class="nfp__head">
                <span class="nfp__count" data-nfp-count><?php echo esc_html($initial_count_label); ?></span>
                <span class="nfp__station-label" data-nfp-station-label><?php echo esc_html($config['stationLabel']); ?></span>
                <span class="nfp__live nfp__sr-only" data-nfp-live role="status" aria-live="polite"><?php echo esc_html($initial_live_label); ?></span>
                <button class="nfp__menu-slot" type="button" aria-label="<?php echo esc_attr__('Выбрать радиостанцию', 'net-fm-player'); ?>" disabled><i></i><i></i><i></i></button>
            </div>

                <div class="nfp__vinyl-media">
                    <div class="nfp__cover <?php echo $config['fallbackArt'] ? 'is-fallback-art' : 'is-placeholder'; ?>" data-nfp-cover>
                    <img class="nfp__station-art" data-nfp-station-art <?php echo $config['fallbackArt'] ? 'src="' . esc_url($config['fallbackArt']) . '"' : ''; ?> alt="" loading="eager">
                    <img class="nfp__art" data-nfp-art alt="" loading="eager" hidden>
                    <div class="nfp__placeholder" data-nfp-placeholder>
                        <span class="nfp__station-name" data-nfp-placeholder-name><?php echo esc_html($config['stationLabel']); ?></span>
                        <span class="nfp__station-radio" aria-hidden="true">RADIO</span>
                    </div>
                    <div class="nfp__vinyl-reflections" aria-hidden="true"></div>
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
                </div>

            <div class="nfp__info">
                <div class="nfp__kicker"><?php echo esc_html__('Сейчас играет', 'net-fm-player'); ?></div>
                <div class="nfp__title" data-nfp-title aria-hidden="true"><?php echo esc_html($initial_title); ?></div>
                <div class="nfp__artist" data-nfp-artist aria-hidden="true"><?php echo esc_html($initial_artist); ?></div>
                <div class="nfp__sr-only" data-nfp-track-accessible aria-live="polite" aria-atomic="true"><?php echo esc_html(trim($initial_title . ' — ' . $initial_artist)); ?></div>
                <div class="nfp__status" data-nfp-status role="status" hidden></div>
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

            </div>

            <div class="nfp__volume">
                <button class="nfp__mute" type="button" data-nfp-mute aria-label="<?php echo esc_attr__('Выключить звук', 'net-fm-player'); ?>" aria-pressed="false">
                    <svg class="nfp__mute-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path class="nfp__mute-speaker" d="M4 10v4h4l5 5V5l-5 5H4z"></path>
                        <path class="nfp__mute-wave" d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05A4.47 4.47 0 0 0 16.5 12zm-2.5-8.77v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.5 7-8.77s-2.99-7.86-7-8.77z"></path>
                        <path class="nfp__mute-slash" d="M4 4l16 16"></path>
                    </svg>
                </button>
                <input class="nfp__volume-range" data-nfp-volume type="range" min="0" max="1" step="0.01" value="0.85" aria-label="<?php echo esc_attr__('Громкость', 'net-fm-player'); ?>">
            </div>

            <div class="nfp__error" data-nfp-error role="alert" hidden><?php echo esc_html__('Поток не запустился. Нажмите play еще раз или переключите поток.', 'net-fm-player'); ?></div>

            <audio data-nfp-audio preload="none"></audio>
            </div>
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


