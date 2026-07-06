<?php

namespace ElementorListeo\Controls;

use Elementor\Base_Data_Control;

if (! defined('ABSPATH')) {
    exit;
}

class Listings_Autocomplete_Control extends Base_Data_Control
{
    const CONTROL_TYPE = 'listeo_ajax_posts';

    public function get_type()
    {
        return self::CONTROL_TYPE;
    }

    public function enqueue()
    {
        // Elementor's way of handling control scripts
        $script_url = \plugins_url('assets/js/controls/ajax-posts.js', ELEMENTOR_LISTEO);
        $version = filemtime(\plugin_dir_path(ELEMENTOR_LISTEO) . 'assets/js/controls/ajax-posts.js');

        // Use Elementor's asset manager if available
        if (class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            if (method_exists($elementor, 'wp')) {
                // Register and enqueue via Elementor
                \wp_register_script(
                    'listeo-elementor-control-posts',
                    $script_url,
                    ['jquery', 'elementor-editor'],
                    $version,
                    true
                );

                \wp_localize_script(
                    'listeo-elementor-control-posts',
                    'ListeoElementorControlPosts',
                    [
                        'ajax_url' => \admin_url('admin-ajax.php'),
                        'nonce'    => \wp_create_nonce('listeo_elementor_posts'),
                        'l10n'     => [
                            'no_results' => \esc_html__('No listings found', 'listeo_elementor'),
                        ],
                    ]
                );

                \wp_enqueue_script('listeo-elementor-control-posts');
            }
        }
    }

    protected function get_default_settings()
    {
        return array(
            'label_block'   => true,
            'multiple'      => true,
            'placeholder'   => \esc_html__('Search listings…', 'listeo_elementor'),
            'query_action'  => 'listeo_elementor_search_listings',
            'items_action'  => 'listeo_elementor_get_listings',
        );
    }

    public function content_template()
    {
        ?>

        
                <div class="elementor-control-field">
                    <# if ( data.label ) { #>
                        <label for="{{ data._cid }}" class="elementor-control-title">{{{ data.label }}}</label>
                    <# } #>
            <div class="elementor-control-input-wrapper">
                <#
                var value = data.controlValue;
                if ( _.isEmpty( value ) ) {
                    value = [];
                } else if ( ! _.isArray( value ) ) {
                    value = [ value ];
                }
                var selectedJson = JSON.stringify( value );
                #>
                <select id="{{ data._cid }}"
                        class="listeo-ajax-posts-control"
                        data-setting="{{ data.name }}"
                        data-placeholder="{{ data.placeholder }}"
                        data-query-action="{{ data.query_action }}"
                        data-items-action="{{ data.items_action }}"
                        data-multiple="{{ data.multiple }}"
                        data-selected='{{ selectedJson }}'
                        <# if ( data.multiple ) { #> multiple="multiple" <# } #>
                ></select>
            </div>
        </div>
        <# if ( data.description ) { #>
            <div class="elementor-control-field-description">{{{ data.description }}}</div>
        <# } #>
        <?php
    }
}
