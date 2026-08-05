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

        $this->add_control('default_stream', [
            'label' => __('Default stream', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => $defaults['default_stream'] ?? 'primary',
            'options' => [
                'primary' => __('Primary', 'net-fm-player'),
                'secondary' => __('Secondary', 'net-fm-player'),
            ],
            'condition' => $condition,
        ]);

        $this->add_control('fallback_art', [
            'label' => __('Fallback cover URL', 'net-fm-player'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $defaults['fallback_art'] ?? '',
            'condition' => $condition,
        ]);

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (($settings['use_global_settings'] ?? 'yes') === 'yes') {
            echo do_shortcode('[net_fm_player]');
            return;
        }

        echo do_shortcode(sprintf(
            '[net_fm_player use_global="no" station_label="%s" azuracast_base_url="%s" station_shortcode="%s" api_url="%s" stream_primary="%s" stream_primary_label="%s" stream_secondary="%s" stream_secondary_label="%s" default_stream="%s" fallback_art="%s"]',
            esc_attr($settings['station_label'] ?? ''),
            esc_url($settings['azuracast_base_url'] ?? ''),
            esc_attr($settings['station_shortcode'] ?? ''),
            esc_url($settings['api_url'] ?? ''),
            esc_url($settings['stream_primary_url'] ?? ''),
            esc_attr($settings['stream_primary_label'] ?? ''),
            esc_url($settings['stream_secondary_url'] ?? ''),
            esc_attr($settings['stream_secondary_label'] ?? ''),
            esc_attr($settings['default_stream'] ?? 'primary'),
            esc_url($settings['fallback_art'] ?? '')
        ));
    }
}

