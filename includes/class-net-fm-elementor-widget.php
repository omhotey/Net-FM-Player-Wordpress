<?php

if (!defined('ABSPATH')) {
    exit;
}

class Net_Fm_Player_Elementor_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'net-fm-player';
    }

    public function get_title()
    {
        return __('NET FM Player', 'net-fm-player');
    }

    public function get_icon()
    {
        return 'eicon-play';
    }

    public function get_categories()
    {
        return ['general'];
    }

    public function get_keywords()
    {
        return ['radio', 'audio', 'player', 'azuracast', 'stream'];
    }

    public function get_style_depends()
    {
        return ['net-fm-player'];
    }

    public function get_script_depends()
    {
        return ['net-fm-player'];
    }

    protected function register_controls()
    {
        $defaults = class_exists('Net_Fm_Player_Plugin')
            ? Net_Fm_Player_Plugin::get_options()
            : [];

        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Player settings', 'net-fm-player'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control('station', [
            'label' => __('Station ID', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => __('Copy the ID from NET FM Player settings.', 'net-fm-player'),
        ]);
        $this->add_control('stations', [
            'label' => __('Station IDs for selector', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => __('Enter all for all saved stations, or separate station IDs with commas. Overrides the single station ID.', 'net-fm-player'),
        ]);

        $this->add_control(
            'use_global_settings',
            [
                'label' => __('Use global WordPress settings', 'net-fm-player'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'net-fm-player'),
                'label_off' => __('No', 'net-fm-player'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Global settings live in Settings -> NET FM Player. Local widget values do not write back to that page.', 'net-fm-player'),
            ]
        );

        $condition = ['use_global_settings!' => 'yes'];

        $this->add_control('station_label', [
            'label' => __('Station label', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['station_label'] ?? 'Radio',
            'condition' => $condition,
        ]);

        $this->add_control('azuracast_base_url', [
            'label' => __('AzuraCast Base URL', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['azuracast_base_url'] ?? '',
            'description' => __('Example: https://stream.net-fm.ru', 'net-fm-player'),
            'condition' => $condition,
        ]);

        $this->add_control('station_shortcode', [
            'label' => __('AzuraCast station shortcode', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['station_shortcode'] ?? '',
            'description' => __('Example: myradio. Used to build /api/nowplaying/{shortcode}.', 'net-fm-player'),
            'condition' => $condition,
        ]);

        $this->add_control('api_url', [
            'label' => __('Explicit Now Playing API URL', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['api_url'] ?? '',
            'description' => __('Optional. Leave empty to use Base URL + shortcode, or leave all metadata fields empty for direct-stream only.', 'net-fm-player'),
            'condition' => $condition,
        ]);

        $this->add_control('stream_primary_url', [
            'label' => __('Primary stream URL', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['stream_primary_url'] ?? '',
            'condition' => $condition,
        ]);

        $this->add_control('stream_primary_label', [
            'label' => __('Primary stream label', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['stream_primary_label'] ?? 'Primary',
            'condition' => $condition,
        ]);

        $this->add_control('stream_secondary_url', [
            'label' => __('Secondary stream URL', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['stream_secondary_url'] ?? '',
            'condition' => $condition,
        ]);

        $this->add_control('stream_secondary_label', [
            'label' => __('Secondary stream label', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['stream_secondary_label'] ?? 'Secondary',
            'condition' => $condition,
        ]);

        $this->add_control('stream_hls_url', [
            'label' => __('HLS Auto stream URL', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['stream_hls_url'] ?? '',
            'description' => __('Optional adaptive HLS stream. It is shown only in browsers with native HLS playback.', 'net-fm-player'),
            'condition' => $condition,
        ]);

        $this->add_control('default_stream', [
            'label' => __('Default stream', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => $defaults['default_stream'] ?? 'primary',
            'options' => [
                'primary' => __('Primary', 'net-fm-player'),
                'secondary' => __('Secondary', 'net-fm-player'),
                'hls' => __('Auto (HLS)', 'net-fm-player'),
            ],
            'condition' => $condition,
        ]);

        $this->add_control('fallback_art', [
            'label' => __('Default station logo', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::MEDIA,
            'default' => [
                'url' => $defaults['fallback_art'] ?? '',
            ],
            'description' => __('Displayed when AzuraCast does not provide track artwork.', 'net-fm-player'),
            'condition' => $condition,
        ]);

        $this->add_control('theme', [
            'label' => __('Player theme', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => $defaults['theme'] ?? 'vinyl',
            'options' => [
                'studio' => __('Studio', 'net-fm-player'),
                'vinyl' => __('Vinyl', 'net-fm-player'),
            ],
            'condition' => $condition,
        ]);

        $this->add_control('layout', [
            'label' => __('Player version', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => $defaults['layout'] ?? 'horizontal',
            'options' => [
                'horizontal' => __('Horizontal', 'net-fm-player'),
                'vertical' => __('Vertical', 'net-fm-player'),
            ],
            'condition' => $condition,
        ]);

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Colors', 'net-fm-player'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => $condition,
            ]
        );

        $this->add_control('background_color', [
            'label' => __('Player background', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => $defaults['background_color'] ?? '',
        ]);

        $this->add_control('play_button_color', [
            'label' => __('Play/pause button', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => $defaults['play_button_color'] ?? '',
        ]);

        $this->add_control('volume_color', [
            'label' => __('Volume slider', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => $defaults['volume_color'] ?? '',
        ]);

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (!empty($settings['stations']) || !empty($settings['station'])) {
            $appearance = '';
            if (($settings['use_global_settings'] ?? 'yes') !== 'yes') {
                $appearance = sprintf(' theme="%s" layout="%s" background_color="%s" play_button_color="%s" volume_color="%s"',
                    esc_attr($settings['theme'] ?? 'vinyl'),
                    esc_attr($settings['layout'] ?? 'horizontal'),
                    esc_attr($settings['background_color'] ?? ''),
                    esc_attr($settings['play_button_color'] ?? ''),
                    esc_attr($settings['volume_color'] ?? '')
                );
            }
            echo do_shortcode(sprintf('[net_fm_player station="%s" stations="%s"%s]',
                esc_attr($settings['station'] ?? ''),
                esc_attr($settings['stations'] ?? ''),
                $appearance
            ));
            return;
        }

        if (($settings['use_global_settings'] ?? 'yes') === 'yes') {
            echo do_shortcode('[net_fm_player]');
            return;
        }

        $fallback_art = $settings['fallback_art'] ?? '';
        if (is_array($fallback_art)) {
            $fallback_art = $fallback_art['url'] ?? '';
        }

        echo do_shortcode(sprintf(
            '[net_fm_player use_global="no" station_label="%s" azuracast_base_url="%s" station_shortcode="%s" api_url="%s" stream_primary="%s" stream_primary_label="%s" stream_secondary="%s" stream_secondary_label="%s" stream_hls_url="%s" default_stream="%s" fallback_art="%s" theme="%s" layout="%s" background_color="%s" play_button_color="%s" volume_color="%s"]',
            esc_attr($settings['station_label'] ?? ''),
            esc_url($settings['azuracast_base_url'] ?? ''),
            esc_attr($settings['station_shortcode'] ?? ''),
            esc_url($settings['api_url'] ?? ''),
            esc_url($settings['stream_primary_url'] ?? ''),
            esc_attr($settings['stream_primary_label'] ?? ''),
            esc_url($settings['stream_secondary_url'] ?? ''),
            esc_attr($settings['stream_secondary_label'] ?? ''),
            esc_url($settings['stream_hls_url'] ?? ''),
            esc_attr($settings['default_stream'] ?? 'primary'),
            esc_url($fallback_art),
            esc_attr($settings['theme'] ?? 'vinyl'),
            esc_attr($settings['layout'] ?? 'horizontal'),
            esc_attr($settings['background_color'] ?? ''),
            esc_attr($settings['play_button_color'] ?? ''),
            esc_attr($settings['volume_color'] ?? '')
        ));
    }
}

