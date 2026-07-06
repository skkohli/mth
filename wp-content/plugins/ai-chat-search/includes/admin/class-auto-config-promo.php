<?php
/**
 * Free AI Auto Config promo shell.
 *
 * Renders the locked Free UI for the Pro-only auto setup flow. The real
 * analyzer and setting writer remain in the Pro plugin.
 *
 * @package AI_Chat_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Auto_Config_Promo {

    /**
     * Constructor.
     */
    public function __construct() {
        if (!is_admin()) {
            return;
        }

        add_action('ai_chat_search_auto_config_card', array($this, 'render_card'));
        add_action('ai_chat_search_admin_modals', array($this, 'render_modal'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue locked UI assets on the settings page.
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_ai-chat-search' || $this->is_auto_config_unlocked()) {
            return;
        }

        wp_enqueue_style(
            'ai-chat-search-auto-config',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/css/auto-config.css',
            array('ai-chat-search-admin', 'ai-chat-search-admin-pro'),
            LISTEO_AI_SEARCH_VERSION
        );

        wp_enqueue_script(
            'ai-chat-search-auto-config',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/js/admin/auto-config.js',
            array('jquery', 'airs-admin-core'),
            LISTEO_AI_SEARCH_VERSION,
            true
        );

        wp_localize_script('ai-chat-search-auto-config', 'listeoAiAutoConfig', array(
            'enabled'     => false,
            'locked'      => true,
            'upgrade_url' => AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-auto-config'),
        ));
    }

    /**
     * Render the API Config tab card for Free/locked users.
     */
    public function render_card() {
        if ($this->is_auto_config_unlocked()) {
            return;
        }
        ?>
        <div class="airs-form-group listeo-ai-auto-config-group">
            <div class="airs-help-text airs-blue listeo-ai-auto-config-card listeo-ai-auto-config-card-locked">
                <span class="listeo-ai-auto-config-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" xmlns="http://www.w3.org/2000/svg">
                        <path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72Z"></path>
                        <path d="m14 7 3 3"></path>
                        <path d="M5 6v4"></path>
                        <path d="M19 14v4"></path>
                        <path d="M10 2v2"></path>
                        <path d="M7 8H3"></path>
                        <path d="M21 16h-4"></path>
                        <path d="M11 3H9"></path>
                    </svg>
                </span>
                <div class="listeo-ai-auto-config-copy">
                    <strong>
                        <?php esc_html_e('AI Auto Config', 'ai-chat-search'); ?>
                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Centralized escaped badge HTML. ?>
                    </strong>
                    <span><?php esc_html_e('Let AI draft the starter chat setup for this site.', 'ai-chat-search'); ?></span>
                </div>
                <button type="button" class="airs-button listeo-ai-auto-config-button" id="listeo-ai-auto-config-open">
                    <?php esc_html_e('Preview Auto Config', 'ai-chat-search'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render the locked modal outside settings sections.
     */
    public function render_modal() {
        if ($this->is_auto_config_unlocked()) {
            return;
        }

        $site_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M3 11l9-8 9 8"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path></svg>';
        $message_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path></svg>';
        $actions_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M13 2 3 14h8l-1 8 11-13h-8z"></path></svg>';
        $palette_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M12 22a10 10 0 1 1 10-10c0 2-1.5 3-3 3h-2a2 2 0 0 0-2 2c0 .5.2 1 .5 1.4.4.5.5 1 .5 1.4 0 1.2-1.1 2.2-4 2.2z"></path><circle cx="7.5" cy="10.5" r=".6" fill="currentColor"></circle><circle cx="10.5" cy="7.5" r=".6" fill="currentColor"></circle><circle cx="14.5" cy="7.5" r=".6" fill="currentColor"></circle><circle cx="17.5" cy="10.5" r=".6" fill="currentColor"></circle></svg>';

        $plan = array(
            array(
                'icon'  => $site_svg,
                'title' => __('AI will read your site context', 'ai-chat-search'),
                'desc'  => __('Homepage, menus, language, and key policy pages.', 'ai-chat-search'),
            ),
            array(
                'icon'  => $message_svg,
                'title' => __('AI will draft recommended settings', 'ai-chat-search'),
                'desc'  => __('Welcome message, instructions, content types, custom fields, and safe defaults.', 'ai-chat-search'),
            ),
            array(
                'icon'  => $actions_svg,
                'title' => __('AI will suggest quick actions', 'ai-chat-search'),
                'desc'  => __('Useful starter buttons based on important pages.', 'ai-chat-search'),
            ),
            array(
                'icon'  => $palette_svg,
                'title' => __('AI will match the visual style', 'ai-chat-search'),
                'desc'  => __('Widget colors based on the active site design.', 'ai-chat-search'),
            ),
        );
        ?>
        <div id="listeo-ai-auto-config-modal" class="airs-modal listeo-ai-auto-config-modal listeo-ai-auto-config-modal-locked" style="display:none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content">
                <div class="airs-modal-header">
                    <h3><?php esc_html_e('AI Auto Config', 'ai-chat-search'); ?></h3>
                    <button type="button" class="listeo-ai-modal-close" id="listeo-ai-auto-config-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="airs-modal-body">
                    <div class="listeo-ai-auto-config-step" data-step="intro">
                        <div class="listeo-ai-auto-config-onboarding-head">
                            <h4>
                                <?php esc_html_e('Configure PurioChat in One Pass', 'ai-chat-search'); ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Centralized escaped badge HTML. ?>
                            </h4>
                            <p><?php esc_html_e('Pro scans the essentials, drafts a starter setup, and waits for your approval before saving anything.', 'ai-chat-search'); ?></p>
                        </div>
                        <ol class="listeo-ai-auto-config-steps" data-mode="plan">
                            <?php foreach ($plan as $step) : ?>
                                <li class="listeo-ai-auto-config-stepitem">
                                    <span class="listeo-ai-auto-config-stepicon"><?php echo $step['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG markup. ?></span>
                                    <span class="listeo-ai-auto-config-stepbody">
                                        <strong><?php echo esc_html($step['title']); ?></strong>
                                        <span><?php echo esc_html($step['desc']); ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                    <div id="listeo-ai-auto-config-message" class="airs-alert-error" style="display:none;"></div>
                </div>
                <div class="airs-modal-footer">
                    <button type="button" class="airs-button airs-button-secondary" id="listeo-ai-auto-config-cancel">
                        <?php esc_html_e('Cancel', 'ai-chat-search'); ?>
                    </button>
                    <button type="button" class="airs-button airs-button-primary listeo-ai-auto-config-locked-action" id="listeo-ai-auto-config-analyze" disabled="disabled" aria-disabled="true">
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        <?php esc_html_e('Analyze with Pro', 'ai-chat-search'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Check whether the real Pro auto config flow is available.
     *
     * @return bool
     */
    private function is_auto_config_unlocked() {
        return class_exists('AI_Chat_Search_Pro_Manager') && AI_Chat_Search_Pro_Manager::is_pro_active();
    }
}
