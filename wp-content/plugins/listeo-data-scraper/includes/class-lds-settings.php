<?php
// /includes/class-lds-settings.php

class LDS_Settings
{
    public function __construct()
    {
        add_action("admin_init", [$this, "register_settings"]);

        $current_provider = LDS_AI_Provider::get_selected_provider();
        $current_model = get_option(
            "lds_gpt_model",
            LDS_AI_Provider::get_default_model($current_provider),
        );
        if (
            !LDS_AI_Provider::is_valid_model($current_provider, $current_model)
        ) {
            update_option(
                "lds_gpt_model",
                LDS_AI_Provider::get_default_model($current_provider),
            );
        }
    }

    public function register_settings()
    {
        // Register the setting group
        register_setting("lds_settings_group", "lds_api_source", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_google_api_key", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_outscraper_api_key", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_google_maps_api_key", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_import_limit", [
            "sanitize_callback" => [$this, "sanitize_import_limit"],
        ]);
        register_setting("lds_settings_group", "lds_description_language", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_enable_photo_import", [
            "sanitize_callback" => "absint",
        ]);
        register_setting("lds_settings_group", "lds_photo_import_limit", [
            "sanitize_callback" => [$this, "sanitize_photo_import_limit"],
        ]);
        register_setting("lds_settings_group", "lds_photo_storage_method", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_enable_ai_descriptions", [
            "sanitize_callback" => "absint",
        ]);
        register_setting("lds_settings_group", "lds_ai_provider", [
            "sanitize_callback" => [LDS_AI_Provider::class, "sanitize_provider"],
        ]);
        register_setting("lds_settings_group", "lds_openai_api_key", [
            "sanitize_callback" => [$this, "sanitize_openai_api_key"],
        ]);
        register_setting("lds_settings_group", "lds_openrouter_api_key", [
            "sanitize_callback" => [$this, "sanitize_openrouter_api_key"],
        ]);
        register_setting("lds_settings_group", "lds_gpt_model", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_description_word_length", [
            "sanitize_callback" => [$this, "sanitize_description_word_length"],
        ]);
        register_setting("lds_settings_group", "lds_ai_system_prompt", [
            "sanitize_callback" => [$this, "sanitize_ai_system_prompt"],
        ]);
        register_setting("lds_settings_group", "lds_enable_yoast_seo", [
            "sanitize_callback" => "absint",
        ]);
        register_setting("lds_settings_group", "lds_enable_debug_mode", [
            "sanitize_callback" => "absint",
        ]);
        register_setting("lds_settings_group", "lds_search_method", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        // Image regeneration settings
        register_setting(
            "lds_settings_group",
            "lds_image_regeneration_method",
            ["sanitize_callback" => "sanitize_text_field"],
        );
        // Map settings
        register_setting("lds_settings_group", "lds_enable_map_mode", [
            "sanitize_callback" => "absint",
        ]);
        register_setting("lds_settings_group", "lds_default_radius", [
            "sanitize_callback" => "floatval",
        ]);
        register_setting("lds_settings_group", "lds_default_map_center_lat", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_default_map_center_lng", [
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("lds_settings_group", "lds_map_zoom_level", [
            "sanitize_callback" => "absint",
        ]);
        // Removed lds_default_listing_type setting

        // ===== API and Import Settings Section =====
        add_settings_section(
            "lds_api_section",
            '<span class="lds-section-header">API and Import Settings</span>',
            null,
            "lds-settings-page",
        );

        add_settings_field(
            "lds_api_source",
            "Data Source",
            [$this, "render_api_source_field"],
            "lds-settings-page",
            "lds_api_section",
        );
        add_settings_field(
            "lds_google_api_key",
            "Google API Key (Server-Side Data Importing)",
            [$this, "render_api_key_field"],
            "lds-settings-page",
            "lds_api_section",
        );
        add_settings_field(
            "lds_outscraper_api_key",
            "Outscraper API Key",
            [$this, "render_outscraper_api_key_field"],
            "lds-settings-page",
            "lds_api_section",
        );
        add_settings_field(
            "lds_google_maps_api_key",
            "Google API Key for Interactive Map",
            [$this, "render_maps_api_key_field"],
            "lds-settings-page",
            "lds_api_section",
        );
        add_settings_field(
            "lds_import_limit",
            "Listings to Import",
            [$this, "render_import_limit_field"],
            "lds-settings-page",
            "lds_api_section",
        );
        add_settings_field(
            "lds_description_language",
            "Reviews & Description Language",
            [$this, "render_language_field"],
            "lds-settings-page",
            "lds_api_section",
        );
        add_settings_field(
            "lds_enable_photo_import",
            "Enable Photo Import",
            [$this, "render_photo_import_section"],
            "lds-settings-page",
            "lds_api_section",
        );

        // ===== AI Settings Section =====
        add_settings_section(
            "lds_ai_section",
            '<span class="lds-section-header">AI SEO Descriptions</span>',
            null,
            "lds-settings-page",
        );

        add_settings_field(
            "lds_enable_ai_descriptions",
            "Enable AI Descriptions",
            [$this, "render_ai_toggle_field"],
            "lds-settings-page",
            "lds_ai_section",
        );
        add_settings_field(
            "lds_ai_provider",
            esc_html__("AI Provider", "listeo-data-scraper"),
            [$this, "render_ai_provider_field"],
            "lds-settings-page",
            "lds_ai_section",
        );
        add_settings_field(
            "lds_ai_system_prompt",
            "AI System Prompt",
            [$this, "render_ai_system_prompt_field"],
            "lds-settings-page",
            "lds_ai_section",
        );
        add_settings_field(
            "lds_enable_yoast_seo",
            "Yoast and Rank Math SEO Integration",
            [$this, "render_seo_plugin_field"],
            "lds-settings-page",
            "lds_ai_section",
        );

        // ===== Tools Section =====
        add_settings_section(
            "lds_tools_section",
            '<span class="lds-section-header">Tools</span>',
            null,
            "lds-settings-page",
        );

        add_settings_field(
            "lds_image_regeneration",
            "Google API Image Regeneration",
            [$this, "render_image_regeneration_section"],
            "lds-settings-page",
            "lds_tools_section",
        );
        add_settings_field(
            "lds_photo_regeneration",
            "Listing Photo Regeneration" .
                (LDS_Pro_Manager::is_feature_locked("photo_regeneration")
                    ? " " . LDS_Pro_Manager::get_pro_badge()
                    : ""),
            [$this, "render_photo_regeneration_section"],
            "lds-settings-page",
            "lds_tools_section",
        );
        add_settings_field(
            "lds_ai_description_regeneration",
            "AI Description Regeneration" .
                (LDS_Pro_Manager::is_feature_locked(
                    "ai_description_regeneration",
                )
                    ? " " . LDS_Pro_Manager::get_pro_badge()
                    : ""),
            [$this, "render_ai_description_regeneration_section"],
            "lds-settings-page",
            "lds_tools_section",
        );
        add_settings_field(
            "lds_enable_debug_mode",
            "Enable Debug Mode",
            [$this, "render_debug_mode_toggle_field"],
            "lds-settings-page",
            "lds_tools_section",
        );
    }

    // Render AI toggle checkbox
    public function render_ai_toggle_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $value = get_option("lds_enable_ai_descriptions", 1); // Default: enabled
        $checked = checked(1, $value, false);
        $disabled = $is_locked ? "disabled" : "";

        echo "<div class='lds-feature-wrapper'>";
        echo "<label>";
        echo "<input type='checkbox' name='lds_enable_ai_descriptions' id='lds_enable_ai_descriptions' value='1' {$checked} {$disabled} /> ";
        echo esc_html__(
            "Generate AI descriptions for imported listings",
            "listeo-data-scraper",
        );
        echo "</label>";

        if ($is_locked) {
            echo "<div class='lds-upgrade-notice' style='margin-top: 10px;'>";
            echo "<p style='display: flex; align-items: center; gap: 8px; margin-bottom: 12px;'>";
            echo "<span class='dashicons dashicons-lock' style='color: #0073ee; font-size: 18px; width: 18px; height: 18px;'></span>";
            echo "<strong style='font-size: 15px;'>AI-Generated Descriptions</strong>";
            echo "</p>";
            echo "<p style='margin-bottom: 15px; line-height: 1.5;'>" .
                esc_html__(
                    "Create unique, SEO-optimized content from Google Reviews for each listing using supported AI providers.",
                    "listeo-data-scraper",
                ) .
                " <strong>" .
                esc_html__(
                    "Helps your site rank better in Google and AI search",
                    "listeo-data-scraper",
                ) .
                "</strong><br><br>" .
                esc_html__(
                    "Set custom AI instructions, choose the AI model, and pick the description length.",
                    "listeo-data-scraper",
                ) .
                "</p>";
            echo "<a href='" .
                esc_url(LDS_Pro_Manager::get_upgrade_url("ai_descriptions")) .
                "' class='button button-primary' target='_blank'>";
            echo lds_get_inline_svg_icon("unlock");
            echo esc_html__("Upgrade to Pro", "listeo-data-scraper");
            echo "</a>";
            echo "</div>";
        } else {
            echo "<p class='description'>" .
                esc_html__(
                    "When disabled, a simple fallback description will be used instead.",
                    "listeo-data-scraper",
                ) .
                "</p>";

            // Check if Listeo Core Google Reviews is disabled - warn users
            $google_reviews_enabled = get_option("listeo_google_reviews", 0);
            if (!$google_reviews_enabled) {
                echo "<div id='lds-google-reviews-warning' class='lds-google-reviews-notice' style='margin-top: 12px; padding: 10px 12px; background: #fff2f0; border-left: 4px solid #dc3545; border-radius: 4px; display: " .
                    ($value ? "block" : "none") .
                    ";'>";
                echo "<p style='margin: 0; color: #721c24; font-size: 13px;'>";
                echo "<strong>" .
                    esc_html__("Note:", "listeo-data-scraper") .
                    "</strong> ";
                echo esc_html__(
                    'Google reviews will be used for AI descriptions, but won\'t display on listings. To show reviews, enable',
                    "listeo-data-scraper",
                );
                echo " <a href='" .
                    esc_url(
                        admin_url("admin.php?page=listeo_settings&tab=single"),
                    ) .
                    "' target='_blank' style='color: #721c24; font-weight: 600;'>" .
                    esc_html__(
                        "Google Reviews in Listeo settings",
                        "listeo-data-scraper",
                    ) .
                    "</a>.";
                echo "</p>";
                echo "</div>";
            }
        }

        echo "</div>";

        // JavaScript to show/hide AI sub-fields based on checkbox state
        // (AI Provider, API Key, AI Model, and embedded Description Length - but NOT System Prompt)
        echo "<script>
        jQuery(document).ready(function($) {
            function toggleAISubFields() {
                var isChecked = $('#lds_enable_ai_descriptions').is(':checked');
                var isLocked = $('#lds_enable_ai_descriptions').is(':disabled');
                var showProviderInfo = isChecked || isLocked;
                $('#lds_ai_provider_container').closest('tr').toggle(showProviderInfo);
                $('#lds_description_length_container').toggle(isChecked && !isLocked);
                if (showProviderInfo && window.ldsUpdateAIProviderUI) {
                    window.ldsUpdateAIProviderUI($('#lds_ai_provider').val());
                } else if (!showProviderInfo) {
                    $('.lds-provider-field').hide();
                }
                // Toggle Google Reviews warning
                $('#lds-google-reviews-warning').toggle(isChecked && !isLocked);
            }

            // Initial state
            toggleAISubFields();

            // Toggle on checkbox change
            $('#lds_enable_ai_descriptions').on('change', function() {
                toggleAISubFields();
            });
        });
        </script>";
    }

    // Render AI provider toggle
    public function render_ai_provider_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $value = LDS_AI_Provider::get_selected_provider();
        $disabled = $is_locked ? "disabled" : "";
        $openai_icon = LDS_PLUGIN_URL . "assets/icons/gpt.svg";
        $openrouter_icon = LDS_PLUGIN_URL . "assets/icons/openrouter.svg";
        $models_for_js = [
            LDS_AI_Provider::OPENAI => [
                "models" => array_keys(LDS_AI_Provider::get_openai_models()),
                "default" => LDS_AI_Provider::get_default_model(
                    LDS_AI_Provider::OPENAI,
                ),
                "label" => __("OpenAI Model", "listeo-data-scraper"),
                "help" => __(
                    "Choose the OpenAI model for generating AI descriptions.",
                    "listeo-data-scraper",
                ),
            ],
            LDS_AI_Provider::OPENROUTER => [
                "models" => array_keys(LDS_AI_Provider::get_models("openrouter")),
                "freeModels" => array_keys(
                    LDS_AI_Provider::get_openrouter_free_models(),
                ),
                "default" => LDS_AI_Provider::get_default_model(
                    LDS_AI_Provider::OPENROUTER,
                ),
                "label" => __("OpenRouter Model", "listeo-data-scraper"),
                "help" => __(
                    "Choose the OpenRouter model for generating AI descriptions.",
                    "listeo-data-scraper",
                ),
            ],
        ];

        echo "<div id='lds_ai_provider_container' style='margin-top: 0;'>";
        echo "<input type='hidden' name='lds_ai_provider' id='lds_ai_provider' value='" .
            esc_attr($value) .
            "' {$disabled} />";
        echo "<div class='ai-provider-toggle ai-provider-toggle-2' data-selected='" .
            esc_attr($value) .
            "' aria-disabled='" .
            esc_attr($is_locked ? "true" : "false") .
            "'>";
        echo "<div class='ai-provider-option' data-value='openai' role='button' tabindex='0' aria-label='" .
            esc_attr__("OpenAI", "listeo-data-scraper") .
            "'>";
        echo "<div class='ai-provider-logo'><img src='" .
            esc_url($openai_icon) .
            "' alt='" .
            esc_attr__("OpenAI", "listeo-data-scraper") .
            "'></div>";
        echo "</div>";
        echo "<div class='ai-provider-option' data-value='openrouter' role='button' tabindex='0' aria-label='" .
            esc_attr__("OpenRouter", "listeo-data-scraper") .
            "'>";
        echo "<div class='ai-provider-logo'><img src='" .
            esc_url($openrouter_icon) .
            "' alt='" .
            esc_attr__("OpenRouter", "listeo-data-scraper") .
            "'></div>";
        echo "</div>";
        echo "<div class='ai-provider-slider'></div>";
        echo "</div>";
        echo "<div class='lds-openrouter-note' style='display:" .
            ($is_locked || $value === "openrouter" ? "block" : "none") .
            "; margin-top: 10px;'>";
        echo lds_render_notice(
            esc_html__(
                "OpenRouter offers free models. You just need an API key from their console.",
                "listeo-data-scraper",
            ),
            "info-subtle",
        );
        echo "</div>";
        if ($is_locked) {
            echo "</div>";
            return;
        }
        echo "<div class='lds-ai-provider-settings' style='margin-top: 16px; max-width: 760px;'>";
        $this->render_openai_api_key_field();
        $this->render_openrouter_api_key_field();
        $this->render_gpt_model_field();
        echo "</div>";
        echo "</div>";

        echo "<script>
        window.ldsAIProviderModels = " .
            wp_json_encode($models_for_js) .
            ";
        jQuery(document).ready(function($) {
            window.ldsUpdateAIProviderUI = function(provider) {
                provider = provider || $('#lds_ai_provider').val() || 'openai';
                if (!window.ldsAIProviderModels[provider]) {
                    provider = 'openai';
                }

                $('#lds_ai_provider').val(provider);
                $('.ai-provider-toggle').attr('data-selected', provider);
                $('.ai-provider-option').removeClass('active');
                $('.ai-provider-option[data-value=\"' + provider + '\"]').addClass('active');
                $('.lds-openrouter-note').toggle(provider === 'openrouter');

                $('.lds-provider-field').hide();
                if ($('#lds_enable_ai_descriptions').is(':checked')) {
                    $('.lds-provider-' + provider).show();
                }

                var config = window.ldsAIProviderModels[provider];
                $('#lds_gpt_model_help_text').text(config.help);

                $('.lds-gpt-model-select').prop('disabled', true).removeAttr('name');
                var activeModelSelect = $('.lds-gpt-model-select[data-provider=\"' + provider + '\"]');
                activeModelSelect.prop('disabled', false).attr('name', 'lds_gpt_model');

                var currentModel = activeModelSelect.val();
                if ($.inArray(currentModel, config.models) === -1) {
                    activeModelSelect.val(config.default);
                }
                window.ldsUpdateAIModelNotice();
            };

            window.ldsUpdateAIModelNotice = function() {
                var provider = $('#lds_ai_provider').val() || 'openai';
                var model = $('.lds-gpt-model-select[data-provider=\"' + provider + '\"]').val() || '';
                var config = window.ldsAIProviderModels[provider] || {};
                var freeModels = config.freeModels || [];

                $('#lds_openrouter_free_model_notice').toggle(
                    provider === 'openrouter' && $.inArray(model, freeModels) !== -1
                );
            };

            $('.ai-provider-option').on('click keydown', function(event) {
                if ($('#lds_ai_provider').prop('disabled')) {
                    return;
                }
                if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                window.ldsUpdateAIProviderUI($(this).data('value'));
            });

            $('.ai-provider-slider').on('click', function(event) {
                event.stopPropagation();
            });

            $('.lds-gpt-model-select').off('change.ldsAiProvider').on('change.ldsAiProvider', function() {
                window.ldsUpdateAIModelNotice();
            });

            window.ldsUpdateAIProviderUI($('#lds_ai_provider').val());
        });
        </script>";
    }

    // Render GPT model selection dropdown
    public function render_gpt_model_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $provider = LDS_AI_Provider::get_selected_provider();
        $value = get_option(
            "lds_gpt_model",
            LDS_AI_Provider::get_default_model($provider),
        );
        $openai_value =
            $provider === LDS_AI_Provider::OPENAI &&
            LDS_AI_Provider::is_valid_model(LDS_AI_Provider::OPENAI, $value)
                ? $value
                : LDS_AI_Provider::get_default_model(LDS_AI_Provider::OPENAI);
        $openrouter_value =
            $provider === LDS_AI_Provider::OPENROUTER &&
            LDS_AI_Provider::is_valid_model(LDS_AI_Provider::OPENROUTER, $value)
                ? $value
                : LDS_AI_Provider::get_default_model(LDS_AI_Provider::OPENROUTER);
        $disabled = $is_locked ? "disabled" : "";

        echo "<div id='lds_gpt_model_container' style='margin-top: 0;'>";

        echo "<div class='lds-provider-field lds-provider-openai' style='display:" .
            ($provider === "openai" ? "block" : "none") .
            "; margin-bottom: 10px;'>";
        echo "<label for='lds_gpt_model_openai' style='display: block; font-weight: 600; margin-bottom: 6px;'>" .
            esc_html__("OpenAI Model", "listeo-data-scraper") .
            "</label>";
        echo "<select id='lds_gpt_model_openai' class='lds-gpt-model-select' data-provider='openai' " .
            ($provider === "openai" ? "name='lds_gpt_model'" : "disabled") .
            " {$disabled}>";
        foreach (LDS_AI_Provider::get_openai_models() as $slug => $label) {
            echo "<option value='" .
                esc_attr($slug) .
                "'" .
                selected($openai_value, $slug, false) .
                ">" .
                esc_html($label) .
                "</option>";
        }
        echo "</select>";
        echo "</div>";

        echo "<div class='lds-provider-field lds-provider-openrouter' style='display:" .
            ($provider === "openrouter" ? "block" : "none") .
            "; margin-bottom: 10px;'>";
        echo "<label for='lds_gpt_model_openrouter' style='display: block; font-weight: 600; margin-bottom: 6px;'>" .
            esc_html__("OpenRouter Model", "listeo-data-scraper") .
            "</label>";
        echo "<select id='lds_gpt_model_openrouter' class='lds-gpt-model-select' data-provider='openrouter' " .
            ($provider === "openrouter" ? "name='lds_gpt_model'" : "disabled") .
            " {$disabled}>";
        echo "<optgroup label='" . esc_attr__("Free Models", "listeo-data-scraper") . "'>";
        foreach (LDS_AI_Provider::get_openrouter_free_models() as $slug => $label) {
            echo "<option value='" .
                esc_attr($slug) .
                "'" .
                selected($openrouter_value, $slug, false) .
                ">" .
                esc_html($label) .
                "</option>";
        }
        echo "</optgroup>";
        echo "<optgroup label='" . esc_attr__("Paid Models", "listeo-data-scraper") . "'>";
        foreach (LDS_AI_Provider::get_openrouter_paid_models() as $slug => $label) {
            echo "<option value='" .
                esc_attr($slug) .
                "'" .
                selected($openrouter_value, $slug, false) .
                ">" .
                esc_html($label) .
                "</option>";
        }
        echo "</optgroup>";
        echo "</select>";
        echo "</div>";

        if ($is_locked) {
            echo " " . LDS_Pro_Manager::get_pro_badge();
        }
        echo "<p class='description' id='lds_gpt_model_help_text'>" .
            esc_html(
                $provider === "openrouter"
                    ? __(
                        "Choose the OpenRouter model for generating AI descriptions.",
                        "listeo-data-scraper",
                    )
                    : __(
                        "Choose the OpenAI model for generating AI descriptions.",
                        "listeo-data-scraper",
                    ),
            ) .
            "</p>";
        $is_free_openrouter_model =
            $provider === "openrouter" &&
            isset(LDS_AI_Provider::get_openrouter_free_models()[$value]);
        echo "<div id='lds_openrouter_free_model_notice' style='display:" .
            ($is_free_openrouter_model ? "block" : "none") .
            "; margin-top: 8px;'>";
        echo lds_render_notice(
            esc_html__(
                "Free OpenRouter limits: 20 req/min, 50/day before $10 credits, 1000/day after.",
                "listeo-data-scraper",
            ),
            "warning",
        );
        echo "</div>";
        echo "</div>";
    }

    // Render description word length field
    public function render_description_length_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $value = get_option("lds_description_word_length", 300); // Default: 300 words
        $disabled = $is_locked ? "disabled" : "";

        echo "<div id='lds_description_length_container' style='margin-top: 14px;'>";
        echo "<label for='lds_description_word_length' style='display: block; font-weight: 600; margin-bottom: 6px;'>" .
            esc_html__("Description Length", "listeo-data-scraper") .
            "</label>";
        echo "<input type='number' name='lds_description_word_length' value='" .
            esc_attr($value) .
            "' id='lds_description_word_length' min='50' max='500' step='10' {$disabled} />";
        if ($is_locked) {
            echo " " . LDS_Pro_Manager::get_pro_badge();
        }
        echo "<p class='description'>" .
            esc_html__(
                "Approximate number of words for AI-generated descriptions (50-500 words).",
                "listeo-data-scraper",
            ) .
            "</p>";
        echo "</div>";
    }

    /**
     * Get the locked system prompt (technical requirements - not editable by users)
     */
    private function get_locked_system_prompt()
    {
        return "You are an expert local SEO and marketing copywriter creating content for a premium business directory.

**Source Material You Will Be Given for each business:**
- `name`: The business name.
- `address`: The full address.
- `types`: Google's categories for the business (e.g., 'cafe', 'coffee_shop').
- `rating`: The overall star rating.
- `rating_count`: The total number of reviews.
- `review_snippets`: An array of short, relevant text from up to 3 real customer reviews.

**Technical Requirements (DO NOT MODIFY):**
- Write all descriptions in {{LANGUAGE}}.
- Target approximately {{WORD_LENGTH}} words for each description.
- **CRITICAL OUTPUT FORMAT:** For each business, you must generate a single string of HTML with the following structure:
    - A short, catchy, SEO-friendly headline enclosed in `<h2>` tags.
    - Following the headline, the main description paragraph(s).
    - Within the paragraph, strategically wrap 3-4 important keywords in `<strong>` tags.
    - Example: `<h2>A Coffee Lover's Paradise in Downtown</h2><p><strong>Example Cafe</strong> is a gem in the heart of <strong>Example City</strong>, beloved for its...</p>`
- **Final JSON Structure:** Return ONLY a valid JSON object: {\"descriptions\": [\"<h2>...</h2><p>...</p>\", \"<h2>...</h2><p>...</p>\"]} in the exact same order as the input array.";
    }

    /**
     * Get the default editable instructions (user can customize this)
     */
    private function get_default_editable_instructions()
    {
        return "**Your Writing & Formatting Instructions:**

1.  **Synthesize, Don't Just List:** Weave the information together naturally. Instead of \"They have good coffee,\" write \"Customers consistently rave about the <strong>rich, aromatic coffee</strong>, calling it a must-try.\"

2.  **Incorporate Keywords:** Naturally include the business name, its type (e.g., \"cafe,\" \"restaurant\"), and its city/neighborhood.

3.  **Highlight Social Proof:** If the rating count is over 10, mention it (e.g., \"...trusted by over 500 reviewers\").

4.  **Use Review Content:** Use the `review_snippets` as the primary source for what makes the business special.

5.  **Professional Tone:** The description must be professional, inviting, and trustworthy.

6.  **DO NOT mention \"Google,\" \"reviews,\" or \"data.\"** Do not mention amount of reviews and average rating. Write as if you are a local expert recommending the place.

7.  **Add paragraphs:** Use <p> tags to make description easier to read. Language should be casual, not like AI-generated content, and not clunky.

8.  **Keywords in bold:** Use <strong> tags for the business name, the city, and key services (e.g., \"specialty coffee\", \"artisanal pastries\", \"cozy atmosphere\"). Do not overuse bolding.";
    }

    /**
     * Sanitize description word length with min/max enforcement.
     * Falls back to 300 if value is below minimum or invalid.
     *
     * @param mixed $value The submitted value.
     * @return int Sanitized value between 50 and 500.
     */
    public function sanitize_description_word_length($value)
    {
        $value = absint($value);

        // Enforce minimum of 50, fallback to 300 if below or zero
        if ($value < 50) {
            return 300;
        }

        // Enforce maximum of 500
        if ($value > 500) {
            return 500;
        }

        return $value;
    }

    /**
     * Sanitize photo import limit. Clamps to Free/Pro max so a downgraded
     * site cannot keep a previously-saved Pro value above the Free cap.
     */
    public function sanitize_photo_import_limit($value)
    {
        return min(absint($value), LDS_Pro_Manager::get_max_photo_limit());
    }

    /**
     * Sanitize import limit. Clamps to Free/Pro max to prevent
     * devtools tampering from saving values above the license cap.
     */
    public function sanitize_import_limit($value)
    {
        return min(absint($value), LDS_Pro_Manager::get_max_import_limit());
    }

    public function sanitize_openai_api_key($value)
    {
        return $this->sanitize_provider_api_key(
            $value,
            LDS_AI_Provider::OPENAI,
        );
    }

    public function sanitize_openrouter_api_key($value)
    {
        return $this->sanitize_provider_api_key(
            $value,
            LDS_AI_Provider::OPENROUTER,
        );
    }

    private function sanitize_provider_api_key($value, $provider)
    {
        $selected_provider = LDS_AI_Provider::sanitize_provider(
            wp_unslash($_POST["lds_ai_provider"] ?? LDS_AI_Provider::get_selected_provider()),
        );

        if ($selected_provider !== $provider) {
            return get_option(LDS_AI_Provider::get_api_key_option($provider), "");
        }

        return sanitize_text_field($value);
    }

    /**
     * Custom sanitize callback for AI system prompt.
     * Prevents empty values from overwriting existing prompt (e.g., when field is disabled).
     * Also ensures default value is used if no value exists.
     *
     * @param string $value The submitted value.
     * @return string The sanitized value.
     */
    public function sanitize_ai_system_prompt($value)
    {
        // If value is empty (field was disabled/not submitted or user cleared it)
        if (empty(trim($value))) {
            // Get existing value from DB (without default fallback)
            $existing = get_option("lds_ai_system_prompt");

            // If existing is also empty, use default
            if (empty($existing)) {
                return $this->get_default_editable_instructions();
            }

            // Preserve existing value
            return $existing;
        }

        // Sanitize and return the submitted value
        return wp_kses_post($value);
    }

    /**
     * Render AI system prompt textarea field
     */
    public function render_ai_system_prompt_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $value = get_option(
            "lds_ai_system_prompt",
            $this->get_default_editable_instructions(),
        );
        $disabled = $is_locked ? "disabled" : "";
        $locked_prompt = $this->get_locked_system_prompt();

        echo "<div id='lds_ai_system_prompt_container' style='margin-top: 0;'>";

        // Editable section
        echo "<div style='background: #f5f5f5; border-radius: 5px; padding: 15px;'>";
        echo "<h4 style='margin-top: 0; color: #333;'>" .
            lds_get_inline_svg_icon("pencil") .
            "AI Writing Instructions</h4>";
        echo "<p style='color: #666; font-size: 12px; margin-bottom: 10px;'>Customize the writing style, tone, and formatting instructions for AI-generated descriptions.</p>";
        echo "<textarea name='lds_ai_system_prompt' id='lds_ai_system_prompt' rows='15' style='width: 100%; font-family: monospace; font-size: 13px; border: 1px solid #ccc;' {$disabled}>" .
            esc_textarea($value) .
            "</textarea>";

        if (!$is_locked) {
            echo "<div style='margin-top: 10px; display: flex; gap: 10px; align-items: center;'>";
            echo "<button type='button' id='lds_reset_system_prompt' class='button button-secondary'>Reset to Default</button>";
            echo "<span id='lds_prompt_char_count' style='color: #666; font-size: 12px;'></span>";
            echo "</div>";
        }

        $this->render_description_length_field();

        echo "</div>"; // Close editable section

        // Note below the box - only show if Pro is active
        if (!$is_locked) {
            echo lds_render_notice(
                "<strong>Note:</strong> AI only receives: business name, address, Google categories, rating, review count, and 5 Google Reviews. <strong>AI cannot access the internet or fetch additional data.</strong> Technical requirements (JSON format, data structure) are handled automatically in the background.",
                "info-subtle",
                "lightbulb",
            );
        }

        echo "</div>"; // Close container

        // JavaScript for character count and reset button
        echo "<script>
        jQuery(document).ready(function($) {
            function updateCharCount() {
                var text = $('#lds_ai_system_prompt').val();
                var charCount = text.length;
                $('#lds_prompt_char_count').text(charCount + ' characters');
            }

            // Initial state
            updateCharCount();

            // Update character count on input
            $('#lds_ai_system_prompt').on('input', function() {
                updateCharCount();
            });

            // Reset to default button
            $('#lds_reset_system_prompt').on('click', function() {
                if (confirm('Are you sure you want to reset the writing instructions to default? This will overwrite your current instructions.')) {
                    var defaultPrompt = " .
            json_encode($this->get_default_editable_instructions()) .
            ";
                    $('#lds_ai_system_prompt').val(defaultPrompt);
                    updateCharCount();
                }
            });
        });
        </script>";
    }

    /**
     * Render SEO plugin integration field
     * Supports both Yoast SEO and Rank Math - auto-detects which is installed
     */
    public function render_seo_plugin_field()
    {
        $is_ai_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $yoast_installed = defined("WPSEO_VERSION");
        $rankmath_installed =
            defined("RANK_MATH_VERSION") || class_exists("RankMath");
        $seo_plugin_installed = $yoast_installed || $rankmath_installed;
        $value = get_option("lds_enable_yoast_seo", 0);

        // Determine which SEO plugin is active
        $active_plugin = "";
        if ($yoast_installed) {
            $active_plugin = "Yoast SEO";
        } elseif ($rankmath_installed) {
            $active_plugin = "Rank Math";
        }

        // Disable if AI is locked OR no SEO plugin is installed
        $is_locked = $is_ai_locked || !$seo_plugin_installed;
        $checked = checked(1, $value, false);
        $disabled = $is_locked ? "disabled" : "";

        echo "<div id='lds_yoast_seo_container' style='margin-top: 0;'>";
        echo "<label style='display: flex; align-items: center; gap: 8px;'>";
        echo "<input type='checkbox' name='lds_enable_yoast_seo' id='lds_enable_yoast_seo' value='1' {$checked} {$disabled} /> ";
        echo __(
            "Generate SEO meta fields during import",
            "listeo-data-scraper",
        );

        echo "</label>";

        // Show appropriate notice based on status
        if (!$seo_plugin_installed) {
            echo lds_render_notice(
                __(
                    "No SEO plugin detected. Install Yoast SEO or Rank Math to use this feature.",
                    "listeo-data-scraper",
                ),
                "warning",
            );
        } else {
            echo "<p class='description' style='margin-top: 8px;'>";
            echo sprintf(
                __(
                    "Detected: %s. AI will generate optimized SEO title, meta description, and focus keyphrase for each imported listing.",
                    "listeo-data-scraper",
                ),
                "<strong>" . esc_html($active_plugin) . "</strong>",
            );
            echo "</p>";
        }

        echo "</div>";

        // JavaScript to show/hide based on AI descriptions toggle
        echo "<script>
        jQuery(document).ready(function($) {
            function toggleSeoField() {
                var isAIChecked = $('#lds_enable_ai_descriptions').is(':checked');
                $('#lds_enable_yoast_seo').closest('tr').toggle(isAIChecked);
            }

            // Initial state
            toggleSeoField();

            // Toggle on AI checkbox change
            $('#lds_enable_ai_descriptions').on('change', function() {
                toggleSeoField();
            });
        });
        </script>";
    }

    public function render_ai_cleaner_url_field()
    {
        $value = get_option("lds_ai_cleaner_url", "");
        $default_url = plugin_dir_url(__FILE__) . "../ai-cleaner.php";

        echo "<input type='url' name='lds_ai_cleaner_url' value='" .
            esc_attr($value) .
            "' class='regular-text' placeholder='" .
            esc_attr($default_url) .
            "' />";
        echo "<p class='description'>Full URL to your ai-cleaner.php file. Leave blank to use default plugin location.</p>";
        echo "<p class='description'><strong>Current default:</strong> " .
            esc_html($default_url) .
            "</p>";
        echo "<p class='description'><em>Only used when AI descriptions are enabled above.</em></p>";
    }

    // OpenAI API key field
    public function render_openai_api_key_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $value = get_option("lds_openai_api_key", "");
        $disabled = $is_locked ? "disabled" : "";
        $current_provider = LDS_AI_Provider::get_selected_provider();

        echo "<div id='lds_openai_api_key_container' class='lds-provider-field lds-provider-openai' style='display:" .
            ($current_provider === "openai" ? "block" : "none") .
            "; margin-top: 0; margin-bottom: 16px;'>";
        echo "<label for='lds_openai_api_key' style='display: block; font-weight: 600; margin-bottom: 6px;'>" .
            esc_html__("OpenAI API Key", "listeo-data-scraper") .
            "</label>";

        // Input field and test button on the same row
        echo "<div style='display: flex; align-items: center; gap: 10px; margin-bottom: 8px;'>";
        echo "<input type='password' name='lds_openai_api_key' id='lds_openai_api_key' value='" .
            esc_attr($value) .
            "' class='regular-text' style='flex: 1;' {$disabled} />";
        echo "<button type='button' id='lds_test_openai_api_key' class='button button-secondary lds-test-ai-api-key' data-provider='openai' data-key-field='#lds_openai_api_key' data-result-field='#lds_openai_api_test_result' style='background: #dcf2dc; color: #148a15; border: none; white-space: nowrap;' {$disabled}>" .
            esc_html__("Test OpenAI API", "listeo-data-scraper") .
            "</button>";
        echo "</div>";

        // Test result span on its own line
        echo "<div style='margin-bottom: 8px;'>";
        echo "<span id='lds_openai_api_test_result' style='font-weight: bold;'></span>";
        echo "</div>";

        echo "<p class='description'>" .
            esc_html__(
                "Enter your OpenAI API key. This is required when OpenAI is selected.",
                "listeo-data-scraper",
            ) .
            "</p>";

        if (!$is_locked) {
            echo "<a class='instr-btn' target='_blank' href='https://docs.purethemes.net/listeo/knowledge-base/how-to-create-open-ai-api-key/'>" .
                esc_html__("Instructions", "listeo-data-scraper") .
                " &rarr;</a>";
        } else {
            echo " " . LDS_Pro_Manager::get_pro_badge();
        }

        echo "</div>";

        // JavaScript for test button functionality
        echo "<script>
        jQuery(document).ready(function($) {
            function renderAITestResult(resultSpan, color, message, iconHtml) {
                var wrapper = $('<span>').css('color', color);
                if (iconHtml) {
                    wrapper.append(iconHtml).append(' ');
                }
                wrapper.append(document.createTextNode(message || ''));
                resultSpan.empty().append(wrapper);
            }

            $('.lds-test-ai-api-key').off('click.ldsAiTest').on('click.ldsAiTest', function() {
                var provider = $(this).data('provider');
                var apiKey = $($(this).data('key-field')).val().trim();
                var button = $(this);
                var resultSpan = $($(this).data('result-field'));
                var originalText = button.text();

                if (!apiKey) {
                    renderAITestResult(resultSpan, '#d63384', '" .
            esc_js(__("Please enter an API key first.", "listeo-data-scraper")) .
            "', '" .
            lds_get_inline_svg_icon('x') .
            "');
                    return;
                }

                button.prop('disabled', true).text('" . esc_js(__('Testing...', 'listeo-data-scraper')) . "');
                renderAITestResult(resultSpan, '#0d6efd', '" .
            esc_js(__("Testing API connection...", "listeo-data-scraper")) .
            "', '');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_test_openai_api_key',
                        provider: provider,
                        api_key: apiKey,
                        nonce: '" .
            wp_create_nonce("lds_test_openai_api_key_nonce") .
            "'
                    },
                    success: function(response) {
                        if (response.success) {
                            renderAITestResult(resultSpan, '#198754', response.data.message, '" .
            lds_get_inline_svg_icon('check') .
            "');
                        } else {
                            renderAITestResult(resultSpan, '#d63384', response.data.message, '" .
            lds_get_inline_svg_icon('x') .
            "');
                        }
                    },
                    error: function() {
                        renderAITestResult(resultSpan, '#d63384', '" .
            esc_js(__("Network error occurred", "listeo-data-scraper")) .
            "', '" .
            lds_get_inline_svg_icon('x') .
            "');
                    },
                    complete: function() {
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>";
    }

    // OpenRouter API key field
    public function render_openrouter_api_key_field()
    {
        $is_locked = LDS_Pro_Manager::is_feature_locked("ai_descriptions");
        $value = get_option("lds_openrouter_api_key", "");
        $disabled = $is_locked ? "disabled" : "";
        $current_provider = LDS_AI_Provider::get_selected_provider();

        echo "<div id='lds_openrouter_api_key_container' class='lds-provider-field lds-provider-openrouter' style='display:" .
            ($current_provider === "openrouter" ? "block" : "none") .
            "; margin-top: 0; margin-bottom: 16px;'>";
        echo "<label for='lds_openrouter_api_key' style='display: block; font-weight: 600; margin-bottom: 6px;'>" .
            esc_html__("OpenRouter API Key", "listeo-data-scraper") .
            "</label>";
        echo "<div style='display: flex; align-items: center; gap: 10px; margin-bottom: 8px;'>";
        echo "<input type='password' name='lds_openrouter_api_key' id='lds_openrouter_api_key' value='" .
            esc_attr($value) .
            "' class='regular-text' style='flex: 1;' {$disabled} />";
        echo "<button type='button' id='lds_test_openrouter_api_key' class='button button-secondary lds-test-ai-api-key' data-provider='openrouter' data-key-field='#lds_openrouter_api_key' data-result-field='#lds_openrouter_api_test_result' style='background: #dcf2dc; color: #148a15; border: none; white-space: nowrap;' {$disabled}>" .
            esc_html__("Test OpenRouter API", "listeo-data-scraper") .
            "</button>";
        echo "</div>";
        echo "<div style='margin-bottom: 8px;'>";
        echo "<span id='lds_openrouter_api_test_result' style='font-weight: bold;'></span>";
        echo "</div>";
        echo "<p class='description'>" .
            esc_html__(
                "Enter your OpenRouter API key. This is required when OpenRouter is selected.",
                "listeo-data-scraper",
            ) .
            "</p>";

        if (!$is_locked) {
            echo "<a class='instr-btn' target='_blank' href='https://openrouter.ai/keys'>" .
                esc_html__("OpenRouter Console", "listeo-data-scraper") .
                " &rarr;</a>";
        } else {
            echo " " . LDS_Pro_Manager::get_pro_badge();
        }

        echo "</div>";
    }

    public function render_api_key_field()
    {
        $value = get_option("lds_google_api_key", "");
        $test_button_label = __('Test Places API', 'listeo-data-scraper');

        // Input field and test button on the same row
        echo "<div style='display: flex; align-items: center; gap: 10px; margin-bottom: 8px;'>";
        echo "<input type='password' name='lds_google_api_key' id='lds_google_api_key' value='" .
            esc_attr($value) .
            "' class='regular-text' style='flex: 1;' />";
        echo "<button type='button' id='lds_test_api_key' class='button button-secondary' style='background: #dcf2dc; color: #148a15; border: none; white-space: nowrap;'>" . esc_html($test_button_label) . "</button>";
        echo "</div>";

        // Test result span on its own line
        echo "<div style='margin-bottom: 8px;'>";
        echo "<span id='lds_api_test_result' style='font-weight: bold;'></span>";
        echo "</div>";

        $server_ip = $this->get_server_ip();
        echo "<p class='description'>";
        echo esc_html__('Enter your Google Places API key for server-side data fetching', 'listeo-data-scraper');
        echo " <strong style='color: #222;'>(" . esc_html__('restricted to your server IP:', 'listeo-data-scraper') . " <code style='font-weight: bold; color: #d32f2f; background:transparent;'>" . esc_html($server_ip) . "</code>)</strong>. ";
        echo esc_html__('You have to enable', 'listeo-data-scraper') . " <strong style='color: #222;'>" . esc_html__('Places API (New)', 'listeo-data-scraper') . "</strong> " . esc_html__('in Google Cloud.', 'listeo-data-scraper');
        echo "</p>";
        echo "<a class='instr-btn' target='_blank' href='https://docs.purethemes.net/listeo/knowledge-base/creating-google-maps-api-key/'>Instructions →</a>";

        // Cost example (info)
        echo lds_render_notice(
            "<strong>Cost Example</strong>: Importing 100 businesses typically costs \$2-4 in Google API fees. Start with small batches and monitor your Google Cloud billing closely.",
            "info",
            "dollar",
        );

        // Charges disclaimer (danger)
        echo lds_render_notice(
            "<strong>Note:</strong> We do not take responsibility for any charges from Google related to your usage. Monitor your API usage to avoid surprise charges and <strong>set up alerts in Google Cloud</strong>.",
            "danger",
        );

        // Terms heads-up (warning)
        echo lds_render_notice(
            "<strong>Heads up!</strong> It uses Google Places data and storing that data in WordPress might go against Google’s Terms. Use it responsibly - heavy or improper use could lead to API limits or account suspension. We do not take responsibility for any issues that come up.",
            "warning",
        );

        // Add JavaScript for the test button
        echo "<script>
        jQuery(document).ready(function($) {
            $('#lds_test_api_key').on('click', function() {
                var apiKey = $('#lds_google_api_key').val().trim();
                var button = $(this);
                var resultSpan = $('#lds_api_test_result');

                if (!apiKey) {
                    resultSpan.html('<span style=\"color: #d63384;\">" . esc_js(__('Please enter an API key first', 'listeo-data-scraper')) . "</span>');
                    return;
                }

                button.prop('disabled', true).text('" . esc_js(__('Testing...', 'listeo-data-scraper')) . "');
                resultSpan.html('<span style=\"color: #0d6efd;\">" . esc_js(__('Testing Places API...', 'listeo-data-scraper')) . "</span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_test_api_key',
                        api_key: apiKey,
                        nonce: '" .
            wp_create_nonce("lds_test_api_key_nonce") .
            "'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultSpan.html('<span style=\"color: #198754;\">' + response.data.message + '</span>');
                        } else {
                            resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " " . esc_js(__('Network error occurred', 'listeo-data-scraper')) . "</span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('" . esc_js($test_button_label) . "');
                    }
                });
            });
        });
        </script>";
    }

    public function render_maps_api_key_field()
    {
        $value = get_option("lds_google_maps_api_key", "");

        // Get the site domain dynamically
        $site_url = home_url();
        $parsed_url = parse_url($site_url);
        $domain = $parsed_url["host"] ?? "yourdomain.com";

        // Input field and test button on the same row
        echo "<div style='display: flex; align-items: center; gap: 10px; margin-bottom: 8px;'>";
        echo "<input type='password' name='lds_google_maps_api_key' id='lds_google_maps_api_key' value='" .
            esc_attr($value) .
            "' class='regular-text' style='flex: 1;' />";
        echo "<button type='button' id='lds_test_maps_api_key' class='button button-secondary' style='background: #dcf2dc; color: #148a15; border: none; white-space: nowrap;'>Test Maps API</button>";
        echo "</div>";

        // Test result span on its own line
        echo "<div style='margin-bottom: 8px;'>";
        echo "<span id='lds_maps_api_test_result' style='font-weight: bold;'></span>";
        echo "</div>";

        echo "<p class='description'>Enter your Google API key for interactive map display <strong style='color: #222;'>(use HTTP referrer restrictions)</strong>.</p>";

        // Configuration instructions (neutral)
        $maps_config = "<strong>Required API Key Configuration for Maps:</strong><br>";
        $maps_config .= "1. Go to <a href='https://console.cloud.google.com/apis/credentials' target='_blank'>Google Cloud Console → Credentials</a><br>";
        $maps_config .= "2. Click your API key → <strong>Application restrictions</strong> → Select <strong>\"HTTP referrers (web sites)\"</strong><br>";
        $maps_config .= "3. Add these referrer patterns:";
        $maps_config .= "<div style='margin: 8px 0 0; padding: 8px 10px; background: #fff; border-radius: 5px; font-family: monospace; font-size: 12px; line-height: 1.7;'>";
        $maps_config .= "https://" . esc_html($domain) . "/*<br>";
        $maps_config .= "https://*." . esc_html($domain) . "/*<br>";
        if (strpos($domain, "www.") === 0) {
            $no_www_domain = substr($domain, 4);
            $maps_config .= "https://" . esc_html($no_www_domain) . "/*";
        } else {
            $maps_config .= "https://www." . esc_html($domain) . "/*";
        }
        $maps_config .= "</div>";
        echo lds_render_notice($maps_config, "neutral", "wrench");

        echo lds_render_notice(
            "<strong>Note:</strong> This key is for browser-based map display only. Use HTTP referrer restrictions instead of IP restrictions for this key.",
            "info-subtle",
            "lightbulb",
        );

        // Add JavaScript for the maps API test (client-side)
        echo "<script>
        jQuery(document).ready(function($) {
            $('#lds_test_maps_api_key').on('click', function() {
                var apiKey = $('#lds_google_maps_api_key').val().trim();
                var button = $(this);
                var resultSpan = $('#lds_maps_api_test_result');

                if (!apiKey) {
                    resultSpan.html('<span style=\"color: #d63384;\">Please enter an API key first</span>');
                    return;
                }

                button.prop('disabled', true).text('Testing...');
                resultSpan.html('<span style=\"color: #0d6efd;\">Testing Maps JavaScript API...</span>');

                // Test by loading the Maps JavaScript API directly in the browser
                var script = document.createElement('script');
                script.onload = function() {
                    try {
                        // Test basic Maps API functionality
                        if (typeof google !== 'undefined' && google.maps) {
                            // Test geocoding service (common maps feature)
                            var geocoder = new google.maps.Geocoder();
                            geocoder.geocode({ address: 'New York, NY' }, function(results, status) {
                                if (status === 'OK' && results[0]) {
                                    resultSpan.html('<span style=\"color: #198754;\">" . lds_get_inline_svg_icon('check') . " Maps API key is valid and working!</span>');
                                } else if (status === 'REQUEST_DENIED') {
                                    resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " Request denied - Check HTTP referrer restrictions in Google Cloud Console</span>');
                                } else if (status === 'OVER_QUERY_LIMIT') {
                                    resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " API quota exceeded - Check your Google Cloud billing</span>');
                                } else {
                                    resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " API test failed: ' + status + '</span>');
                                }
                                button.prop('disabled', false).text('Test Maps API');
                            });
                        } else {
                            resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " Maps API not available</span>');
                            button.prop('disabled', false).text('Test Maps API');
                        }
                    } catch (error) {
                        resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " Error: ' + error.message + '</span>');
                        button.prop('disabled', false).text('Test Maps API');
                    }
                };
                script.onerror = function() {
                    resultSpan.html('<span style=\"color: #d63384;\">" . lds_get_inline_svg_icon('x') . " Failed to load Maps API - Check API key and restrictions</span>');
                    button.prop('disabled', false).text('Test Maps API');
                };
                script.src = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&libraries=places&callback=Function.prototype';
                document.head.appendChild(script);

                // Clean up after test
                setTimeout(function() {
                    if (script.parentNode) {
                        script.parentNode.removeChild(script);
                    }
                }, 10000);
            });
        });
        </script>";
    }

    public function render_api_source_field()
    {
        $value = get_option("lds_api_source", "google");
        $is_outscraper_locked = LDS_Pro_Manager::is_feature_locked(
            "outscraper_api",
        );

        // Force Google API if Outscraper is locked and somehow Outscraper was saved
        if ($is_outscraper_locked && $value === "outscraper") {
            $value = "google";
            update_option("lds_api_source", "google");
        }
        ?>
        <fieldset>
            <!-- Toggle Buttons (Similar to Location Mode) -->
            <div class="lds-location-mode" style="margin-bottom: 15px;">
                <button type="button" class="lds-mode-btn <?php echo $value ===
                "google"
                    ? "active"
                    : ""; ?>" data-api-source="google">
                    <?php echo lds_get_inline_svg_icon("search"); ?>Google Places
                </button>
                <button type="button" class="lds-mode-btn <?php echo $value ===
                "outscraper"
                    ? "active"
                    : ""; ?>" data-api-source="outscraper">
                    <?php echo lds_get_inline_svg_icon("globe"); ?>Outscraper <?php echo $is_outscraper_locked
                        ? LDS_Pro_Manager::get_pro_badge()
                        : ""; ?>
                </button>
            </div>

            <!-- Hidden Radio Inputs (for form submission) -->
            <input type="radio" name="lds_api_source" value="google" <?php checked(
                $value,
                "google",
            ); ?> style="display: none;" id="lds_api_source_google" />
            <input type="radio" name="lds_api_source" value="outscraper" <?php checked(
                $value,
                "outscraper",
            ); ?> <?php echo $is_outscraper_locked
     ? "disabled"
     : ""; ?> style="display: none;" id="lds_api_source_outscraper" />

            <!-- API Source Descriptions -->
            <div id="lds-google-api-info" style="<?php echo $value === "google"
                ? ""
                : "display: none;"; ?>">
                <?php echo lds_render_notice(
                    "Use Google's official Places API for data extraction. Official API with direct access to Google Maps data.",
                    "info-subtle",
                ); ?>
            </div>

            <div id="lds-outscraper-api-info" style="display: none;">
                <?php echo lds_render_notice(
                    'Use Outscraper\'s API for Google Maps data extraction. Third-party service with flexible pricing. <strong>Approximately 20x cheaper.</strong> <a href="https://outscraper.com/" target="_blank">Learn more →</a>',
                    "info-subtle",
                ); ?>

                <?php if ($is_outscraper_locked): ?>
                <!-- Pro Upgrade Notice -->
<div class="lds-upgrade-notice" style="margin-top: 10px;">
    <p style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
        <span class="dashicons dashicons-lock" style="color: #0073ee; font-size: 18px; width: 18px; height: 18px;"></span>
        <strong style="font-size: 15px;">Outscraper API – Pro Feature</strong>
    </p>
    <p style="margin-bottom: 15px; line-height: 1.5; font-weight: 400;">
        Save <strong>up to 95% on API costs</strong> by using Outscraper instead of Google Places API.
    </p>
    <a href="<?php echo esc_url(
        LDS_Pro_Manager::get_upgrade_url("outscraper_api"),
    ); ?>" class="button button-primary" target="_blank">
        <?php echo lds_get_inline_svg_icon("unlock"); ?>
        <?php esc_html_e("Upgrade to Pro", "listeo-data-scraper"); ?>
    </a>
</div>

                <?php endif; ?>
            </div>
        </fieldset>

        <p class="description" style="margin-top: -5px;"><strong>Note:</strong> Both APIs access the same Google Maps data but have different pricing models. Choose based on your needs.</p>

        <script>
        jQuery(document).ready(function($) {
            const isOutscraperLocked = <?php echo $is_outscraper_locked
                ? "true"
                : "false"; ?>;

            // Handle toggle button clicks
            $('.lds-mode-btn[data-api-source]').on('click', function() {
                const apiSource = $(this).data('api-source');

                // If Outscraper is clicked but locked, show info but don't actually select it
                if (apiSource === 'outscraper' && isOutscraperLocked) {
                    // Remove active from all buttons
                    $('.lds-mode-btn[data-api-source]').removeClass('active');
                    // Keep Google button active
                    $('.lds-mode-btn[data-api-source="google"]').addClass('active');

                    // Show Outscraper info with upgrade notice
                    $('#lds-google-api-info').slideUp(200);
                    $('#lds-outscraper-api-info').slideDown(200);

                    // Keep Google selected in hidden radio
                    $('input[name="lds_api_source"]').prop('checked', false);
                    $('#lds_api_source_google').prop('checked', true);

                    toggleApiKeyFields();
                    return; // Don't proceed with normal selection
                }

                // Normal selection (not locked)
                // Update button states
                $('.lds-mode-btn[data-api-source]').removeClass('active');
                $(this).addClass('active');

                // Update hidden radio inputs
                $('input[name="lds_api_source"]').prop('checked', false);
                $('#lds_api_source_' + apiSource).prop('checked', true);

                // Update info boxes
                if (apiSource === 'google') {
                    $('#lds-google-api-info').slideDown(200);
                    $('#lds-outscraper-api-info').slideUp(200);
                } else {
                    $('#lds-google-api-info').slideUp(200);
                    $('#lds-outscraper-api-info').slideDown(200);
                }

                // Toggle API key fields visibility
                toggleApiKeyFields();
            });

            function toggleApiKeyFields() {
                var selectedSource = $('input[name="lds_api_source"]:checked').val();

                if (selectedSource === 'google') {
                    $('input[name="lds_google_api_key"]').closest('tr').show();
                    $('input[name="lds_google_maps_api_key"]').closest('tr').show();
                    $('input[name="lds_outscraper_api_key"]').closest('tr').hide();

                    // Show photo import settings for Google API
                    $('input[name="lds_enable_photo_import"]').closest('tr').show();
                } else {
                    $('input[name="lds_google_api_key"]').closest('tr').hide();
                    $('input[name="lds_google_maps_api_key"]').closest('tr').show();
                    $('input[name="lds_outscraper_api_key"]').closest('tr').show();

                    // Hide photo import settings for Outscraper API
                    $('input[name="lds_enable_photo_import"]').closest('tr').hide();
                }
            }

            // Initial state
            toggleApiKeyFields();
        });
        </script>
        <?php
    }

    public function render_outscraper_api_key_field()
    {
        $value = get_option("lds_outscraper_api_key", "");
        $nonce = wp_create_nonce("lds_test_outscraper_api_key_nonce");
        ?>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <input type="password" name="lds_outscraper_api_key" id="lds_outscraper_api_key" value="<?php echo esc_attr(
                $value,
            ); ?>" class="regular-text" style="flex: 1;" />
            <button type="button" id="lds_test_outscraper_api_key" class="button button-secondary" style="background: #dcf2dc; color: #148a15; border: none; white-space: nowrap;">Test Outscraper API</button>
        </div>

        <div style="margin-bottom: 8px;">
            <span id="lds_outscraper_api_test_result" style="font-weight: bold;"></span>
        </div>

        <p class="description">Enter your Outscraper API key for server-side data fetching. <a href="https://outscraper.com/" target="_blank">Get your API key →</a></p>

        <?php echo lds_render_notice(
            '<strong>Outscraper Pricing:</strong> Different pricing model than Google. Check <a href="https://outscraper.com/pricing/" target="_blank">Outscraper pricing</a> for current rates.',
            "info",
            "dollar",
        ); ?>

        <script>
        jQuery(document).ready(function($) {
            $('#lds_test_outscraper_api_key').on('click', function() {
                var apiKey = $('#lds_outscraper_api_key').val().trim();
                var button = $(this);
                var resultSpan = $('#lds_outscraper_api_test_result');

                if (!apiKey) {
                    resultSpan.html('<span style="color: #d63384;">Please enter an API key first</span>');
                    return;
                }

                button.prop('disabled', true).text('Testing...');
                resultSpan.html('<span style="color: #0d6efd;">Testing Outscraper API...</span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_test_outscraper_api_key',
                        api_key: apiKey,
                        nonce: '<?php echo $nonce; ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultSpan.html('<span style="color: #198754;">' + response.data.message + '</span>');
                        } else {
                            resultSpan.html('<span style="color: #d63384;"><?php echo lds_get_inline_svg_icon('x'); ?> ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        resultSpan.html('<span style="color: #d63384;"><?php echo lds_get_inline_svg_icon('x'); ?> Network error occurred</span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Outscraper API');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function render_import_limit_field()
    {
        $is_pro = LDS_Pro_Manager::is_pro_active();
        $max_limit = LDS_Pro_Manager::get_max_import_limit();
        $value = min(get_option("lds_import_limit", $max_limit), $max_limit);

        echo "<input type='number' name='lds_import_limit' value='" .
            esc_attr($value) .
            "' min='1' max='" .
            $max_limit .
            "' />";

        if (!$is_pro) {
            echo "<p class='description' style='color: #64748b;'><strong>" .
                esc_html__("Free Version:", "listeo_core") .
                "</strong> " .
                sprintf(
                    /* translators: %d: maximum number of listings */
                    esc_html__(
                        "Maximum %d listings per import.",
                        "listeo_core",
                    ),
                    $max_limit,
                ) .
                " <br><a href='" .
                LDS_Pro_Manager::get_upgrade_url("import_limit") .
                "' class='lds-upgrade-link'>" .
                esc_html__("Upgrade to Pro", "listeo_core") .
                "</a> " .
                esc_html__(
                    "for up to 100 listings per import.",
                    "listeo_core",
                ) .
                "</p>";
        } else {
            echo "<p class='description' style='color: #059669;'><strong>" .
                lds_get_inline_svg_icon("check") .
                esc_html__("Pro Activated:", "listeo_core") .
                "</strong> " .
                sprintf(
                    /* translators: %d: maximum number of listings */
                    esc_html__(
                        "Import up to %d listings per batch.",
                        "listeo_core",
                    ),
                    $max_limit,
                ) .
                "</p>";
            echo "<p class='description' style='color: #dc2626; margin-top: 8px;'><strong>" .
                esc_html__("Note:", "listeo_core") .
                "</strong> " .
                esc_html__(
                    "Depending on your server resources, you might need to lower this value in case of timeouts.",
                    "listeo_core",
                ) .
                "</p>";
        }

    }

    public function render_debug_mode_toggle_field()
    {
        $value = get_option("lds_enable_debug_mode", 0);
        $checked = checked(1, $value, false);

        echo "<label>";
        echo "<input type='checkbox' name='lds_enable_debug_mode' value='1' {$checked} /> ";
        echo "Enable logging for debugging purposes";
        echo "</label>";
        echo "<p class='description'>When enabled, the plugin will write detailed information to a <code>debug-lds.log</code> file in your <code>/wp-content/</code> directory.</p>";
        echo "<p class='description'><strong>Note:</strong> This should be left disabled during normal use to improve performance and save disk space.</p>";
    }

    public function render_language_field()
    {
        $current_lang = get_option("lds_description_language", "site-default");
        $site_locale = get_locale();

        // Check if Locale class exists and get display name with fallback
        $has_intl = class_exists("Locale");
        if ($has_intl) {
            try {
                $site_lang_name = \Locale::getDisplayLanguage(
                    $site_locale,
                    "en",
                );
            } catch (\Exception $e) {
                $site_lang_name = $site_locale;
                $has_intl = false;
            }
        } else {
            $site_lang_name = $site_locale;
        }

        $available_locales = get_available_languages();
        ?>
        <select name="lds_description_language">
            <option value="site-default" <?php selected(
                $current_lang,
                "site-default",
            ); ?>>
                WordPress Site Language (Currently: <?php echo esc_html(
                    $site_lang_name,
                ); ?>)
            </option>

            <?php
            $language_names = [];
            if (!empty($available_locales)) {
                foreach ($available_locales as $locale) {
                    if ($has_intl) {
                        try {
                            $language_names[] = \Locale::getDisplayLanguage(
                                $locale,
                                "en",
                            );
                        } catch (\Exception $e) {
                            $language_names[] = $locale;
                        }
                    } else {
                        $language_names[] = $locale;
                    }
                }
            }

            if (
                $current_lang !== "site-default" &&
                !in_array($current_lang, $language_names)
            ) {
                $language_names[] = $current_lang;
            }

            $language_names = array_unique($language_names);
            sort($language_names);

            foreach ($language_names as $lang_name) {
                echo '<option value="' .
                    esc_attr($lang_name) .
                    '" ' .
                    selected($current_lang, $lang_name, false) .
                    ">";
                echo esc_html($lang_name);
                echo "</option>";
            }
            ?>
        </select>
        <p class="description">Sets the language for AI-generated descriptions and Google Places data, including fetched reviews.</p>
        <?php if (!extension_loaded("intl")) {
            echo '<p style="color: #e74c3c;"><strong>Warning:</strong> The `intl` PHP extension is not enabled on your server. Language name display may be limited.</p>';
        }
    }

    // New method that combines photo settings
    public function render_photo_import_section()
    {
        $photo_enabled = get_option("lds_enable_photo_import", 0);
        $photo_limit = get_option("lds_photo_import_limit", 0);
        $storage_method = get_option("lds_photo_storage_method", "download");
        $max_photos = LDS_Pro_Manager::get_max_photo_limit();
        $is_pro = LDS_Pro_Manager::is_pro_active();

        $checked = checked(1, $photo_enabled, false);

        // Main checkbox
        echo "<label>";
        echo "<input type='checkbox' name='lds_enable_photo_import' id='lds_enable_photo_import' value='1' {$checked} /> ";
        echo esc_html__("Import photos from Google Places", "listeo_core");
        echo "</label>";

        // Photo settings container (hidden when checkbox is unchecked)
        echo "<div id='lds_photo_settings' style='margin-top: 15px; padding: 15px; border-radius: 5px; background: #f5f5f5;'>";

        // Number of photos field - Pro feature
        if ($is_pro) {
            echo "<h4 style='margin-top: 0;'>" .
                esc_html__("Number of Photos to Import", "listeo_core") .
                "</h4>";

            $value = min((int) $photo_limit, $max_photos);
            echo "<input type='number' name='lds_photo_import_limit' value='" .
                esc_attr($value) .
                "' min='0' max='" .
                esc_attr($max_photos) .
                "' />";

            echo "<p class='description' style='color: #059669;'><strong>" .
                lds_get_inline_svg_icon("check") .
                esc_html__("Pro Activated:", "listeo_core") .
                "</strong> " .
                esc_html__(
                    "Import up to 10 photos per listing.",
                    "listeo_core",
                ) .
                "</p>";
            echo "<p class='description' style='color: #666; font-size: 12px;'><strong>" .
                esc_html__("Note:", "listeo_core") .
                "</strong> " .
                esc_html__(
                    "Photos will increase Google API usage and cost.",
                    "listeo_core",
                ) .
                "</p>";
        } else {
            echo "<h4 style='margin-top: 0;'>" .
                esc_html__("Number of Photos to Import", "listeo_core") .
                " " .
                LDS_Pro_Manager::get_pro_badge() .
                "</h4>";
            echo "<input type='number' name='lds_photo_import_limit' value='1' min='1' max='1' readonly />";
            echo "<p class='description' style='color: #64748b;'><strong>" .
                esc_html__("Free Version:", "listeo_core") .
                "</strong> " .
                esc_html__("Maximum 1 photo per listing.", "listeo_core") .
                " <br><a href='" .
                LDS_Pro_Manager::get_upgrade_url("photo_limit") .
                "' class='lds-upgrade-link'>" .
                esc_html__("Upgrade to Pro", "listeo_core") .
                "</a> " .
                esc_html__("for up to 10 photos per listing.", "listeo_core") .
                "</p>";
            echo "<p class='description' style='color: #666; font-size: 12px;'><strong>" .
                esc_html__("Note:", "listeo_core") .
                "</strong> " .
                esc_html__(
                    "Photos will increase Google API usage and cost.",
                    "listeo_core",
                ) .
                "</p>";
            echo "<div class='lds-upgrade-notice' style='margin-top: 10px;'>";
            echo "<p style='display: flex; align-items: center; gap: 8px; margin-bottom: 12px;'>";
            echo "<span class='dashicons dashicons-lock' style='color: #0073ee; font-size: 18px; width: 18px; height: 18px;'></span>";
            echo "<strong style='font-size: 15px;'>" .
                esc_html__(
                    "Import Up To 10 Photos Per Listing",
                    "listeo_core",
                ) .
                "</strong>";
            echo "</p>";
            echo "<p style='margin-bottom: 15px; line-height: 1.5;'>" .
                esc_html__(
                    "Free version is limited to 1 photo per listing. Upgrade to Pro to import up to 10 photos per listing for richer galleries.",
                    "listeo_core",
                ) .
                "</p>";
            echo "<a href='" .
                esc_url(LDS_Pro_Manager::get_upgrade_url("photo_limit")) .
                "' class='button button-primary' target='_blank'>";
            echo lds_get_inline_svg_icon("unlock");
            echo esc_html__("Upgrade to Pro", "listeo_core");
            echo "</a>";
            echo "</div>";
        }
        // Photo storage method - always download to media library
        ?>
        <input type="hidden" name="lds_photo_storage_method" value="download" />
        <?php echo lds_render_notice(
            "<strong>" .
                esc_html__("Google's Terms of Service:", "listeo_core") .
                "</strong> " .
                esc_html__(
                    "Downloading and storing Google Places photos may violate the copyrights of their authors. Use at your own risk.",
                    "listeo_core",
                ),
            "warning",
        ); ?>
        <?php
        echo "</div>"; // End photo settings container

        // JavaScript to show/hide photo settings
        echo "<script>
        jQuery(document).ready(function($) {
            function togglePhotoSettings() {
                if ($('#lds_enable_photo_import').is(':checked')) {
                    $('#lds_photo_settings').show();
                } else {
                    $('#lds_photo_settings').hide();
                }
            }

            // Initial state
            togglePhotoSettings();

            // Toggle on checkbox change
            $('#lds_enable_photo_import').on('change', function() {
                togglePhotoSettings();
            });
        });
        </script>";
    }

    public function render_photo_limit_field()
    {
        $value = get_option("lds_photo_import_limit", 0);
        echo "<input type='number' name='lds_photo_import_limit' value='" .
            esc_attr($value) .
            "' min='0' max='5' />";
        echo "<p class='description'>Max number of photos to import per listing (0-5).</p>";
    }

    // Photo storage method - always download to media library
    public function render_photo_storage_method_field()
    {
        ?>
        <input type="hidden" name="lds_photo_storage_method" value="download" />
        <?php echo lds_render_notice(
            "<strong>" .
                esc_html__("Google's Terms of Service:", "listeo_core") .
                "</strong> " .
                esc_html__(
                    "Downloading and storing Google Places photos may violate the copyrights of their authors. Use at your own risk.",
                    "listeo_core",
                ),
            "warning",
        ); ?>
        <?php
    }

    /**
     * Render image regeneration section
     */
    public function render_image_regeneration_section()
    {
        $regeneration_method = get_option(
            "lds_image_regeneration_method",
            "refresh_api",
        );// Toggle button with nice styling
        ?>
        <div style="margin-bottom: 15px;">
            <div id="lds_image_regen_toggle">
                <span>Regeneration Settings</span>
                <span id="lds_toggle_arrow" style="font-size: 10px; transition: transform 0.3s ease;">▼</span>
            </div>
        </div>
        <?php  ?>
        <div id="lds_image_regeneration_content" style="background: #fff; display: none;">
            <!-- Info box with improved styling -->
            <div style="
                background: linear-gradient(135deg, #e8f4fd 0%, #f0f8ff 100%);
                color: #2c5aa0;
                padding: 15px;
                margin: 0 0 15px 0;
                border-radius: 5px;
            ">
                <p style="margin: 0; line-height: 1.5;">
                    Use these tools when Google-hosted images are no longer working (API key expired/changed) or when you want to clean up image storage for your listings.
                </p>
            </div>

            <h4 style="color: #333; margin: 20px 0 15px 0; font-size: 16px;">Choose Regeneration Method:</h4>

            <fieldset style="border: none; padding: 0;">
            <label style="
                display: block;
                margin-bottom: 15px;
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                border: 2px solid #e9ecef;
                transition: all 0.3s ease;
                cursor: pointer;
            " onmouseover="this.style.borderColor='#667eea'; this.style.backgroundColor='#f4f6ff';"
               onmouseout="this.style.borderColor='#e9ecef'; this.style.backgroundColor='#f8f9fa';">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <input type="radio" name="lds_image_regeneration_method" value="refresh_api" <?php checked(
                        $regeneration_method,
                        "refresh_api",
                    ); ?>
                           style="margin-top: 2px;" />
                    <div>
                        <div style="font-weight: 500; color: #333; margin-bottom: 5px;">
                            <?php _e(
                                "Refresh Google-hosted images with new API key",
                                "listeo-data-scraper",
                            ); ?>
                        </div>
                        <p style="margin: 0; color: #6c757d; font-size: 13px; line-height: 1.4;">
                            Regenerate Google photo URLs using the current API key. Use this when your API key has expired or changed.
                        </p>
                    </div>
                </div>
            </label>

            <label style="
                display: block;
                margin-bottom: 15px;
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                border: 2px solid #e9ecef;
                transition: all 0.3s ease;
                cursor: pointer;
            " onmouseover="this.style.borderColor='#667eea'; this.style.backgroundColor='#f4f6ff';"
               onmouseout="this.style.borderColor='#e9ecef'; this.style.backgroundColor='#f8f9fa';">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <input type="radio" name="lds_image_regeneration_method" value="fallback_placeholder" <?php checked(
                        $regeneration_method,
                        "fallback_placeholder",
                    ); ?>
                           style="margin-top: 2px;" />
                    <div>
                        <div style="font-weight: 500; color: #333; margin-bottom: 5px;">
                            <?php _e(
                                "Remove Google images and use theme fallback placeholder",
                                "listeo-data-scraper",
                            ); ?>
                        </div>
                        <p style="margin: 0; color: #6c757d; font-size: 13px; line-height: 1.4;">
                            Remove all Google-hosted images and let the theme display its default placeholder image.
                        </p>
                    </div>
                </div>
            </label>
        </fieldset>

        <div id="lds_regeneration_actions">
            <button type="button" id="lds_run_image_regeneration" class="button button-primary" style="">
                <?php echo lds_get_inline_svg_icon("rocket"); ?>Run Image Regeneration
            </button>
            <span id="lds_regeneration_status" style="
                margin-left: 15px;
                font-weight: 500;
                padding: 6px 12px;
                border-radius: 4px;
                display: inline-block;
            "></span>
            <div id="lds_regeneration_progress" style="display: none; margin-top: 20px;">
                <div style="
                    background: #f8f9fa;
                    border-radius: 6px;
                    padding: 4px;
                    border: 1px solid #e9ecef;
                ">
                    <div id="lds_progress_bar" style="
                        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                        height: 24px;
                        border-radius: 4px;
                        width: 0%;
                        transition: width 0.5s ease;
                        position: relative;
                        overflow: hidden;
                    ">
                        <div style="
                            position: absolute;
                            top: 0;
                            left: -100%;
                            width: 100%;
                            height: 100%;
                            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                            animation: shimmer 2s infinite;
                        "></div>
                    </div>
                </div>
                <p id="lds_progress_text" style="
                    margin: 10px 0 0 0;
                    font-size: 13px;
                    color: #6c757d;
                    font-weight: 500;
                ">Preparing...</p>
            </div>
        </div>

        </div> <!-- Close content div -->

        <?php // JavaScript for the image regeneration functionality
        ?>
        <style>
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        </style>
        <script>
        jQuery(document).ready(function($) {
            // Toggle functionality with improved animations
            $('#lds_image_regen_toggle').on('click', function(e) {
                e.preventDefault();
                const content = $('#lds_image_regeneration_content');
                const arrow = $('#lds_toggle_arrow');
                const icon = $('#lds_toggle_icon');

                if (content.is(':visible')) {
                    content.slideUp(400, 'swing');
                    arrow.css('transform', 'rotate(0deg)');
                    icon.css('transform', 'scale(1)');
                } else {
                    content.slideDown(400, 'swing');
                    arrow.css('transform', 'rotate(180deg)');
                    icon.css('transform', 'scale(1.1)');
                }
            });

            // Handle regeneration button click
            $('#lds_run_image_regeneration').on('click', function() {
                const selectedMethod = $('input[name="lds_image_regeneration_method"]:checked').val();
                const button = $(this);
                const statusSpan = $('#lds_regeneration_status');
                const progressDiv = $('#lds_regeneration_progress');
                const progressBar = $('#lds_progress_bar');
                const progressText = $('#lds_progress_text');

                // Confirm action
                let confirmMessage = 'Are you sure you want to run image regeneration?\n\n';
                if (selectedMethod === 'refresh_api') {
                    confirmMessage += 'This will refresh all Google-hosted image URLs with the current API key. This may take some time and will use Google API quota.';
                } else if (selectedMethod === 'fallback_placeholder') {
                    confirmMessage += 'This will remove all Google-hosted images from your listings. They will show theme fallback placeholders instead. This action cannot be easily undone.';
                }

                if (!confirm(confirmMessage)) {
                    return;
                }

                // Start the process
                button.prop('disabled', true).text('Processing...');
                statusSpan.html('<span style="color: #0d6efd;">Initializing regeneration...</span>');
                progressDiv.show();
                progressBar.css('width', '0%');
                progressText.text('Starting image regeneration process...');

                // Run the regeneration
                runImageRegeneration(selectedMethod, button, statusSpan, progressBar, progressText);
            });

            function runImageRegeneration(method, button, statusSpan, progressBar, progressText) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_run_image_regeneration',
                        method: method,
                        nonce: '<?php echo wp_create_nonce(
                            "lds_image_regeneration_nonce",
                        ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Start batch processing
                            processBatch(response.data.batch_id, method, button, statusSpan, progressBar, progressText);
                        } else {
                            statusSpan.html('<span style="color: #d63384;"><?php echo lds_get_inline_svg_icon('x'); ?> ' + response.data.message + '</span>');
                            button.prop('disabled', false).text('Run Image Regeneration');
                        }
                    },
                    error: function() {
                        statusSpan.html('<span style="color: #d63384;"><?php echo lds_get_inline_svg_icon('x'); ?> Network error occurred</span>');
                        button.prop('disabled', false).text('Run Image Regeneration');
                    }
                });
            }

            function processBatch(batchId, method, button, statusSpan, progressBar, progressText) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_process_image_regeneration_batch',
                        batch_id: batchId,
                        method: method,
                        nonce: '<?php echo wp_create_nonce(
                            "lds_image_regeneration_nonce",
                        ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;

                            // Update progress
                            const progressPercent = (data.processed / data.total) * 100;
                            progressBar.css('width', progressPercent + '%');
                            progressText.text(`Processed ${data.processed} of ${data.total} listings...`);

                            if (data.completed) {
                                // Process completed
                                progressBar.css('width', '100%');
                                progressText.text(`Completed! Processed ${data.total} listings.`);
                                statusSpan.html('<span style="color: #198754;"><?php echo lds_get_inline_svg_icon('check'); ?> Image regeneration completed successfully</span>');
                                button.prop('disabled', false).text('Run Image Regeneration');

                                // Show summary
                                if (data.summary) {
                                    let summaryHtml = '<div class="lds-callout lds-callout--success" style="margin-top: 12px;"><span class="lds-callout__icon"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"></path></svg></span><div class="lds-callout__content">';
                                    summaryHtml += '<strong>Regeneration Summary:</strong><br>';
                                    if (data.summary.updated) summaryHtml += '• Updated: ' + data.summary.updated + ' listings<br>';
                                    if (data.summary.skipped) summaryHtml += '• Skipped: ' + data.summary.skipped + ' listings<br>';
                                    if (data.summary.errors) summaryHtml += '• Errors: ' + data.summary.errors + ' listings<br>';
                                    summaryHtml += '</div></div>';

                                    progressText.html(summaryHtml);
                                }
                            } else {
                                // Continue processing
                                setTimeout(() => processBatch(batchId, method, button, statusSpan, progressBar, progressText), 1000);
                            }
                        } else {
                            statusSpan.html('<span style="color: #d63384;"><?php echo lds_get_inline_svg_icon('x'); ?> ' + response.data.message + '</span>');
                            button.prop('disabled', false).text('Run Image Regeneration');
                        }
                    },
                    error: function() {
                        statusSpan.html('<span style="color: #d63384;"><?php echo lds_get_inline_svg_icon('x'); ?> Network error occurred during processing</span>');
                        button.prop('disabled', false).text('Run Image Regeneration');
                    }
                });
            }
        });
        </script>
        <?php
    }

    // Render map mode toggle checkbox
    public function render_map_mode_toggle_field()
    {
        $value = get_option("lds_enable_map_mode", 1); // Default: enabled
        $checked = checked(1, $value, false);

        echo "<label>";
        echo "<input type='checkbox' name='lds_enable_map_mode' id='lds_enable_map_mode' value='1' {$checked} /> ";
        echo "Enable interactive map mode for location selection";
        echo "</label>";
        echo "<p class='description'>When enabled, users can toggle between text and map input modes for location selection.</p>";
    }

    // Render map settings section
    public function render_map_settings_section()
    {
        $default_radius = get_option("lds_default_radius", 2.0);
        $default_lat = get_option("lds_default_map_center_lat", "51.5074");
        $default_lng = get_option("lds_default_map_center_lng", "-0.1278");
        $zoom_level = get_option("lds_map_zoom_level", 12);

        echo "<div style='margin-top: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;'>";

        // Default radius
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='lds_default_radius' style='display: block; margin-bottom: 5px;'><strong>Default Search Radius:</strong></label>";
        echo "<div style='display: flex; align-items: center; gap: 15px;'>";
        echo "<input type='range' name='lds_default_radius' id='lds_default_radius' min='0.5' max='50' step='0.5' value='" .
            esc_attr($default_radius) .
            "' style='flex: 1;' />";
        echo "<span id='lds_default_radius_display' style='min-width: 60px; text-align: center; background: #f8f9fa; padding: 6px 12px; border-radius: 4px; border: 1px solid #e9ecef; font-weight: 600;'>" .
            esc_html($default_radius) .
            " km</span>";
        echo "</div>";
        echo "</div>";

        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            // Radius slider functionality
            const radiusSlider = document.getElementById('lds_default_radius');
            const radiusDisplay = document.getElementById('lds_default_radius_display');

            if (radiusSlider && radiusDisplay) {
                radiusSlider.addEventListener('input', function() {
                    radiusDisplay.textContent = this.value + ' km';
                });
            }

            // Geolocate functionality for settings page
            const geolocateBtn = document.getElementById('lds-settings-geolocate-btn');
            if (geolocateBtn) {
                geolocateBtn.addEventListener('click', function() {
                    const button = this;
                    const originalText = button.textContent;

                    button.textContent = 'Loading...';
                    button.disabled = true;
                    button.style.color = '#999';

                    console.log('Settings: Geolocate button clicked');

                    // Use the same IP geolocation as the main import page
                    getIPGeolocationForSettings()
                        .then(function(location) {
                            console.log('Settings: Got location:', location);
                            const latField = document.getElementById('lds_default_map_center_lat');
                            const lngField = document.getElementById('lds_default_map_center_lng');

                            if (latField && lngField) {
                                latField.value = location.lat.toFixed(4);
                                lngField.value = location.lng.toFixed(4);

                                // Highlight the updated fields briefly
                                latField.style.background = '#d4edda';
                                lngField.style.background = '#d4edda';
                                setTimeout(function() {
                                    latField.style.background = '';
                                    lngField.style.background = '';
                                }, 2000);
                            }

                            button.textContent = originalText;
                            button.disabled = false;
                            button.style.color = '#0073aa';
                        })
                        .catch(function(error) {
                            console.log('Settings geolocation failed, trying GPS fallback:', error.message);

                            // Fallback to GPS geolocation
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    function(position) {
                                        console.log('Settings: GPS success:', position.coords);
                                        const latField = document.getElementById('lds_default_map_center_lat');
                                        const lngField = document.getElementById('lds_default_map_center_lng');

                                        if (latField && lngField) {
                                            latField.value = position.coords.latitude.toFixed(4);
                                            lngField.value = position.coords.longitude.toFixed(4);

                                            // Highlight the updated fields briefly
                                            latField.style.background = '#d4edda';
                                            lngField.style.background = '#d4edda';
                                            setTimeout(function() {
                                                latField.style.background = '';
                                                lngField.style.background = '';
                                            }, 2000);
                                        }

                                        button.textContent = originalText;
                                        button.disabled = false;
                                        button.style.color = '#0073aa';
                                    },
                                    function(gpsError) {
                                        console.error('Settings: GPS failed:', gpsError);
                                        alert('Unable to detect location: ' + gpsError.message);
                                        button.textContent = originalText;
                                        button.disabled = false;
                                        button.style.color = '#0073aa';
                                    }
                                );
                            } else {
                                alert('Geolocation is not supported by this browser');
                                button.textContent = originalText;
                                button.disabled = false;
                                button.style.color = '#0073aa';
                            }
                        });
                });
            } else {
                console.error('Settings: Geolocate button not found');
            }

            // Reload saved location functionality
            const reloadBtn = document.getElementById('lds-refresh-location-btn');
            if (reloadBtn) {
                reloadBtn.addEventListener('click', function() {
                    const button = this;
                    const originalText = button.textContent;

                    button.textContent = 'Reloading...';
                    button.disabled = true;

                    // Make AJAX request to get current saved values
                    fetch(lds_admin_vars.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=lds_get_current_location_settings&nonce=' + lds_admin_vars.nonce
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const latField = document.getElementById('lds_default_map_center_lat');
                            const lngField = document.getElementById('lds_default_map_center_lng');

                            if (latField && lngField) {
                                latField.value = data.data.lat;
                                lngField.value = data.data.lng;

                                // Highlight the updated fields briefly
                                latField.style.background = '#fff3cd';
                                lngField.style.background = '#fff3cd';
                                setTimeout(function() {
                                    latField.style.background = '';
                                    lngField.style.background = '';
                                }, 2000);
                            }
                        } else {
                            alert('Failed to reload location: ' + (data.data?.message || 'Unknown error'));
                        }

                        button.textContent = originalText;
                        button.disabled = false;
                    })
                    .catch(error => {
                        console.error('Settings: Reload failed:', error);
                        alert('Failed to reload location settings');
                        button.textContent = originalText;
                        button.disabled = false;
                    });
                });
            }
        });

        // IP Geolocation function for settings page
        async function getIPGeolocationForSettings() {
            try {
                console.log('Settings: Attempting IP geolocation with ipapi.co...');

                const response1 = await fetch('https://ipapi.co/json/', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });

                if (response1.ok) {
                    const data = await response1.json();
                    console.log('Settings: ipapi.co response:', data);

                    if (data.latitude && data.longitude && data.latitude !== 0 && data.longitude !== 0) {
                        return {
                            lat: parseFloat(data.latitude),
                            lng: parseFloat(data.longitude)
                        };
                    }
                }
            } catch (error) {
                console.log('Settings: ipapi.co failed:', error.message);
            }

            try {
                console.log('Settings: Fallback to ipinfo.io...');

                const ipResponse = await fetch('https://api.ipify.org?format=json');
                if (ipResponse.ok) {
                    const ipData = await ipResponse.json();

                    const locationResponse = await fetch('https://ipinfo.io/' + ipData.ip + '/geo');
                    if (locationResponse.ok) {
                        const locationData = await locationResponse.json();

                        if (locationData.loc) {
                            const [lat, lng] = locationData.loc.split(',');
                            if (lat && lng) {
                                return {
                                    lat: parseFloat(lat),
                                    lng: parseFloat(lng)
                                };
                            }
                        }
                    }
                }
            } catch (error) {
                console.log('Settings: Fallback service failed:', error.message);
            }

            throw new Error('All IP geolocation services failed');
        }
        </script>";

        // Default map center
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label style='display: block; margin-bottom: 5px;'><strong>Default Map Center:</strong></label>";
        echo "<p style='font-size: 12px; color: #666; margin: 0 0 8px 0;'>This is the location where the map will center when users first load the import page. You can set this from the main import page using 'Save as Default Location' or manually enter coordinates here.</p>";
        echo "<div style='display: flex; gap: 10px; align-items: end;'>";
        echo "<div>";
        echo "<label for='lds_default_map_center_lat' style='font-size: 12px;'><strong>Latitude:</strong></label>";
        echo "<input type='text' name='lds_default_map_center_lat' id='lds_default_map_center_lat' value='" .
            esc_attr($default_lat) .
            "' style='width: 100px;' />";
        echo "</div>";
        echo "<div>";
        echo "<label for='lds_default_map_center_lng' style='font-size: 12px;'><strong>Longitude:</strong></label>";
        echo "<input type='text' name='lds_default_map_center_lng' id='lds_default_map_center_lng' value='" .
            esc_attr($default_lng) .
            "' style='width: 100px;' />";
        echo "</div>";
        echo "<div>";
        echo "<button type='button' id='lds-settings-geolocate-btn' style='background: none; border: none; color: #0073aa; text-decoration: underline; cursor: pointer; font-size: 14px; padding: 0; margin-top: 16px;'>";
        echo "Geolocate me";
        echo "</button>";
        echo "</div>";
        echo "<div>";
        echo "<button type='button' id='lds-refresh-location-btn' style='background: #f0f0f1; border: 1px solid #c3c4c7; color: #2c3338; cursor: pointer; font-size: 12px; padding: 4px 8px; margin-top: 16px; border-radius: 3px;'>";
        echo "Reload Saved";
        echo "</button>";
        echo "</div>";
        echo "</div>";
        echo "</div>";

        // Default zoom level
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='lds_map_zoom_level' style='display: block; margin-bottom: 5px;'><strong>Default Zoom Level:</strong></label>";
        echo "<select name='lds_map_zoom_level' id='lds_map_zoom_level'>";
        for ($zoom = 8; $zoom <= 16; $zoom++) {
            echo "<option value='{$zoom}'" .
                selected($zoom_level, $zoom, false) .
                ">{$zoom}</option>";
        }
        echo "</select>";
        echo "<p class='description'>Map zoom level (<strong>8 = city level</strong>, <strong>12 = neighborhood level</strong>, <strong>16 = street level</strong>)</p>";
        echo "</div>";

        echo "</div>";
    }

    /**
     * Get the server's outbound IP address
     *
     * @return string Server IP address or error message
     */
    private function get_server_ip()
    {
        // Check if we have a cached IP (valid for 24 hours)
        $cached_ip = get_transient("lds_server_ip");
        if ($cached_ip !== false) {
            return $cached_ip;
        }

        // Try multiple methods to get the server's outbound IP
        $ip = "";

        // Method 1: Use external service to detect outbound IP
        $services = [
            "https://api.ipify.org",
            "https://ipinfo.io/ip",
            "https://icanhazip.com",
            "https://ident.me",
        ];

        foreach ($services as $service) {
            $response = wp_remote_get($service, [
                "timeout" => 10,
                "headers" => [
                    "User-Agent" =>
                        "WordPress/" .
                        get_bloginfo("version") .
                        "; " .
                        home_url(),
                ],
            ]);

            if (
                !is_wp_error($response) &&
                wp_remote_retrieve_response_code($response) === 200
            ) {
                $ip = trim(wp_remote_retrieve_body($response));
                if (
                    filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6,
                    )
                ) {
                    // Cache the IP for 24 hours
                    set_transient("lds_server_ip", $ip, 24 * HOUR_IN_SECONDS);
                    return $ip;
                }
            }
        }

        // Method 2: Check server variables (may not be outbound IP)
        $server_vars = [
            "SERVER_ADDR",
            "LOCAL_ADDR",
            "HTTP_X_FORWARDED_FOR",
            "HTTP_X_REAL_IP",
        ];
        foreach ($server_vars as $var) {
            if (!empty($_SERVER[$var])) {
                $potential_ip = $_SERVER[$var];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($potential_ip, ",") !== false) {
                    $potential_ip = trim(explode(",", $potential_ip)[0]);
                }
                if (
                    filter_var(
                        $potential_ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6,
                    )
                ) {
                    $result = $potential_ip . " (detected from server)";
                    // Cache for shorter time since this might be internal IP
                    set_transient(
                        "lds_server_ip",
                        $result,
                        6 * HOUR_IN_SECONDS,
                    );
                    return $result;
                }
            }
        }

        $error_msg =
            "Unable to detect - Please check with your hosting provider";
        // Cache error for 1 hour to avoid repeated failed requests
        set_transient("lds_server_ip", $error_msg, HOUR_IN_SECONDS);
        return $error_msg;
    }

    /**
     * Render photo import tool section
     */
    public function render_photo_regeneration_section()
    {
        // Check if feature is locked (PRO only)
        $is_locked = LDS_Pro_Manager::is_feature_locked("photo_regeneration");

        // Check if Outscraper API is configured
        $outscraper_api_key = get_option("lds_outscraper_api_key");
        $is_configured = !empty($outscraper_api_key);
        ?>
        <div id="lds_photo_import_content">
            <!-- Info box -->
            <div class="lds-photo-import-info-box">
                <p class="lds-photo-import-info-desc">
                    <?php _e(
                        'This tool <strong style="color: #444;">uses Outscraper\'s</strong> to fetch fresh photos for your existing listings (only <strong>1 photo</strong> per listing). Same copyright principles apply as to Google Photos',
                        "listeo-data-scraper",
                    ); ?>
                </p>
            </div>

            <?php if (!$is_configured): ?>
                <!-- Warning if Outscraper is not configured -->
                <div class="lds-photo-import-warning">
                    <p>
                        <strong><?php _e(
                            "Outscraper API Key Required",
                            "listeo-data-scraper",
                        ); ?></strong><br>
                        <?php _e(
                            "Please configure your Outscraper API key in the settings above before using this tool.",
                            "listeo-data-scraper",
                        ); ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Action button -->
                <div class="lds-photo-import-actions">
                    <button type="button" id="lds_open_photo_regen_modal" class="button button-primary" <?php echo $is_locked
                        ? "disabled"
                        : ""; ?>>
                        <span class="dashicons dashicons-camera lds-button-icon"></span>
                        <span class="lds-button-text"><?php _e(
                            "Select Listings",
                            "listeo-data-scraper",
                        ); ?></span>
                        <span class="lds-button-loader"></span>
                    </button>
                </div>

                <!-- Status message -->
                <div id="lds_photo_regen_status"></div>

                <!-- Progress bar -->
                <div id="lds_photo_regen_progress" class="lds-photo-import-progress">
                    <div class="lds-photo-progress-container">
                        <div id="lds_photo_progress_bar" class="lds-photo-progress-bar">
                            <div class="lds-photo-progress-shimmer"></div>
                        </div>
                    </div>
                    <p id="lds_photo_progress_text" class="lds-photo-progress-text">
                        <?php _e("Preparing...", "listeo-data-scraper"); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Open modal button
            $('#lds_open_photo_regen_modal').on('click', function() {
                openPhotoRegenModal();
            });

            // Global state for photo modal
            let photoModalState = {
                currentPage: 1,
                hasMore: false,
                totalListings: 0,
                searchTerm: '',
                isLoading: false
            };

            function openPhotoRegenModal() {
                const $btn = $('#lds_open_photo_regen_modal');

                // Reset state
                photoModalState = {
                    currentPage: 1,
                    hasMore: false,
                    totalListings: 0,
                    searchTerm: '',
                    isLoading: false
                };

                // Show loading state in button
                $btn.addClass('lds-btn-loading');

                // Fetch first page of listings
                fetchPhotoListings(1, '', function(data) {
                    $btn.removeClass('lds-btn-loading').prop('disabled', false);
                    showListingsModal(data);
                });
            }

            function fetchPhotoListings(page, search, callback) {
                if (photoModalState.isLoading) return;

                photoModalState.isLoading = true;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_get_listings_for_photo_regen',
                        nonce: '<?php echo wp_create_nonce(
                            "lds_import_nonce",
                        ); ?>',
                        page: page,
                        search: search
                    },
                    success: function(response) {
                        photoModalState.isLoading = false;

                        if (response.success) {
                            photoModalState.currentPage = response.data.page;
                            photoModalState.hasMore = response.data.has_more;
                            photoModalState.totalListings = response.data.total;

                            if (callback) callback(response.data);
                        } else {
                            alert(response.data.message || '<?php _e(
                                "Failed to load listings.",
                                "listeo-data-scraper",
                            ); ?>');
                        }
                    },
                    error: function() {
                        photoModalState.isLoading = false;
                        alert('<?php _e(
                            "AJAX error. Please try again.",
                            "listeo-data-scraper",
                        ); ?>');
                    }
                });
            }

            function showListingsModal(data) {
                const { listings, total } = data;

                // Create modal HTML
                let modalHtml = `
                    <div id="lds_photo_regen_modal">
                        <div class="lds-modal-content">
                            <div class="lds-modal-header">
                                <h2><?php _e(
                                    "Select Listings to Update",
                                    "listeo-data-scraper",
                                ); ?></h2>
                                <span class="lds-modal-close">&times;</span>
                            </div>
                            <div class="lds-modal-body">
                                <!-- <div class="lds-modal-settings">
                                    <label for="lds_photo_regen_limit"><?php _e(
                                        "Photos per Listing:",
                                        "listeo-data-scraper",
                                    ); ?></label>
                                    <input type="number" id="lds_photo_regen_limit" min="1" max="100" value="10">
                                    <span class="description"><?php _e(
                                        "(1-100)",
                                        "listeo-data-scraper",
                                    ); ?></span>
                                </div> -->
                                <div class="lds-modal-search">
                                    <input type="text" id="lds_photo_search_input" placeholder="<?php _e(
                                        "Search listings...",
                                        "listeo-data-scraper",
                                    ); ?>" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div class="lds-select-all-wrapper">
                                    <input type="checkbox" id="lds_select_all_listings">
                                    <label for="lds_select_all_listings"><?php _e(
                                        "Select All on Page",
                                        "listeo-data-scraper",
                                    ); ?> (${listings.length})</label>
                                    <span class="lds-total-count" style="margin-left: 10px; color: #666;"><?php _e(
                                        "Total:",
                                        "listeo-data-scraper",
                                    ); ?> ${total}</span>
                                </div>
                                <div id="lds_listings_container">
                `;

                listings.forEach(function(listing) {
                    modalHtml += `
                        <div class="lds-listing-item" data-listing-id="${listing.id}" style="cursor: pointer;">
                            <input type="checkbox" class="lds-listing-checkbox" data-id="${listing.id}" data-place-id="${listing.place_id}">
                            <div class="lds-listing-info" style="pointer-events: none;">
                                <div class="lds-listing-title">${listing.title}</div>
                                <div class="lds-listing-meta">
                                    ${listing.address ? listing.address + ' | ' : ''}
                                    ${listing.photo_count} <?php _e(
                                        "photos",
                                        "listeo-data-scraper",
                                    ); ?> |
                                    <?php _e(
                                        "Place ID:",
                                        "listeo-data-scraper",
                                    ); ?> ${listing.place_id}
                                </div>
                            </div>
                        </div>
                    `;
                });

                modalHtml += `
                                </div>
                                <div id="lds_load_more_container" style="text-align: center; margin-top: 15px; ${photoModalState.hasMore ? '' : 'display: none;'}">
                                    <button type="button" class="button" id="lds_photo_load_more"><?php _e(
                                        "Load More",
                                        "listeo-data-scraper",
                                    ); ?></button>
                                </div>
                            </div>
                            <div class="lds-modal-footer">
                                <div>
                                    <span id="lds_selected_count">0</span> <?php _e(
                                        "listings selected",
                                        "listeo-data-scraper",
                                    ); ?>
                                </div>
                                <div>
                                    <button type="button" class="button" id="lds_modal_cancel"><?php _e(
                                        "Cancel",
                                        "listeo-data-scraper",
                                    ); ?></button>
                                    <button type="button" class="button button-primary" id="lds_modal_start" disabled><?php _e(
                                        "Import Photos",
                                        "listeo-data-scraper",
                                    ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal and append new one
                $('#lds_photo_regen_modal').remove();
                $('body').append(modalHtml);

                // Show modal
                $('#lds_photo_regen_modal').fadeIn(300);

                // Modal event handlers
                $('.lds-modal-close, #lds_modal_cancel').on('click', function() {
                    $('#lds_photo_regen_modal').fadeOut(300, function() {
                        $(this).remove();
                    });
                });

                // Select all functionality
                $('#lds_select_all_listings').on('change', function() {
                    $('.lds-listing-checkbox').prop('checked', $(this).is(':checked'));
                    updateSelectedCount();
                });

                // Individual checkbox change
                $(document).on('change', '.lds-listing-checkbox', function() {
                    updateSelectedCount();
                });

                // Make listing item clickable (except when clicking checkbox directly)
                $(document).on('click', '#lds_listings_container .lds-listing-item', function(e) {
                    // Don't toggle if clicking the checkbox itself
                    if ($(e.target).is('.lds-listing-checkbox')) {
                        return;
                    }

                    const $checkbox = $(this).find('.lds-listing-checkbox');
                    $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
                });

                // Search functionality with debouncing
                let searchTimeout;
                $('#lds_photo_search_input').on('input', function() {
                    const searchTerm = $(this).val().trim();

                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        photoModalState.searchTerm = searchTerm;
                        photoModalState.currentPage = 1;

                        // Show loading state
                        $('#lds_listings_container').html('<div style="text-align: center; padding: 20px;"><?php _e(
                            "Searching...",
                            "listeo-data-scraper",
                        ); ?></div>');

                        fetchPhotoListings(1, searchTerm, function(data) {
                            // Replace listings
                            let listingsHtml = '';
                            data.listings.forEach(function(listing) {
                                listingsHtml += `
                                    <div class="lds-listing-item" data-listing-id="${listing.id}" style="cursor: pointer;">
                                        <input type="checkbox" class="lds-listing-checkbox" data-id="${listing.id}" data-place-id="${listing.place_id}">
                                        <div class="lds-listing-info" style="pointer-events: none;">
                                            <div class="lds-listing-title">${listing.title}</div>
                                            <div class="lds-listing-meta">
                                                ${listing.address ? listing.address + ' | ' : ''}
                                                ${listing.photo_count} <?php _e(
                                                    "photos",
                                                    "listeo-data-scraper",
                                                ); ?> |
                                                <?php _e(
                                                    "Place ID:",
                                                    "listeo-data-scraper",
                                                ); ?> ${listing.place_id}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });

                            $('#lds_listings_container').html(listingsHtml);
                            $('#lds_load_more_container').toggle(photoModalState.hasMore);
                            $('.lds-total-count').html('<?php _e(
                                "Total:",
                                "listeo-data-scraper",
                            ); ?> ' + data.total);
                            $('label[for="lds_select_all_listings"]').html('<?php _e(
                                "Select All on Page",
                                "listeo-data-scraper",
                            ); ?> (' + data.listings.length + ')');
                        });
                    }, 500); // 500ms debounce
                });

                // Load more button
                $('#lds_photo_load_more').on('click', function() {
                    const $btn = $(this);
                    $btn.prop('disabled', true).text('<?php _e(
                        "Loading...",
                        "listeo-data-scraper",
                    ); ?>');

                    fetchPhotoListings(photoModalState.currentPage + 1, photoModalState.searchTerm, function(data) {
                        // Append new listings
                        let listingsHtml = '';
                        data.listings.forEach(function(listing) {
                            listingsHtml += `
                                <div class="lds-listing-item" data-listing-id="${listing.id}" style="cursor: pointer;">
                                    <input type="checkbox" class="lds-listing-checkbox" data-id="${listing.id}" data-place-id="${listing.place_id}">
                                    <div class="lds-listing-info" style="pointer-events: none;">
                                        <div class="lds-listing-title">${listing.title}</div>
                                        <div class="lds-listing-meta">
                                            ${listing.address ? listing.address + ' | ' : ''}
                                            ${listing.photo_count} <?php _e(
                                                "photos",
                                                "listeo-data-scraper",
                                            ); ?> |
                                            <?php _e(
                                                "Place ID:",
                                                "listeo-data-scraper",
                                            ); ?> ${listing.place_id}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        $('#lds_listings_container').append(listingsHtml);
                        $('#lds_load_more_container').toggle(photoModalState.hasMore);
                        $btn.prop('disabled', false).text('<?php _e(
                            "Load More",
                            "listeo-data-scraper",
                        ); ?>');

                        // Update page count
                        const totalLoaded = $('#lds_listings_container .lds-listing-item').length;
                        $('label[for="lds_select_all_listings"]').html('<?php _e(
                            "Select All on Page",
                            "listeo-data-scraper",
                        ); ?> (' + totalLoaded + ')');
                    });
                });

                // Start import button
                $('#lds_modal_start').on('click', function() {
                    startPhotoImport();
                });

                function updateSelectedCount() {
                    const selectedCount = $('.lds-listing-checkbox:checked').length;
                    $('#lds_selected_count').text(selectedCount);
                    $('#lds_modal_start').prop('disabled', selectedCount === 0);
                }
            }

            function startPhotoImport() {
                const selectedListings = [];
                $('.lds-listing-checkbox:checked').each(function() {
                    selectedListings.push({
                        id: $(this).data('id'),
                        place_id: $(this).data('place-id')
                    });
                });

                if (selectedListings.length === 0) {
                    alert('<?php _e(
                        "Please select at least one listing.",
                        "listeo-data-scraper",
                    ); ?>');
                    return;
                }

                const photosLimit = parseInt($('#lds_photo_regen_limit').val()) || 10;

                // Close modal
                $('#lds_photo_regen_modal').fadeOut(300, function() {
                    $(this).remove();
                });

                // Show progress
                $('#lds_photo_regen_progress').show();
                $('#lds_photo_progress_bar').css('width', '0%');
                $('#lds_photo_progress_text').text('<?php _e(
                    "Starting...",
                    "listeo-data-scraper",
                ); ?>');

                // Process listings one by one
                let processed = 0;
                let successful = 0;
                let failed = 0;

                function processNext() {
                    if (processed >= selectedListings.length) {
                        // All done
                        $('#lds_photo_progress_bar').css('width', '100%');
                        $('#lds_photo_progress_text').html(
                            `<strong><?php _e(
                                "Completed!",
                                "listeo-data-scraper",
                            ); ?></strong> ` +
                            `${successful} <?php _e(
                                "successful",
                                "listeo-data-scraper",
                            ); ?>, ${failed} <?php _e(
    "failed",
    "listeo-data-scraper",
); ?>`
                        );
                        $('#lds_photo_regen_status').html('<?php echo lds_get_inline_svg_icon("check"); ?> <?php _e(
                            "Photo import completed",
                            "listeo-data-scraper",
                        ); ?>').addClass('lds-status-success');
                        return;
                    }

                    const listing = selectedListings[processed];
                    const progress = ((processed / selectedListings.length) * 100).toFixed(0);

                    $('#lds_photo_progress_bar').css('width', progress + '%');
                    $('#lds_photo_progress_text').text(
                        `<?php _e(
                            "Processing",
                            "listeo-data-scraper",
                        ); ?> ${processed + 1} <?php _e(
     "of",
     "listeo-data-scraper",
 ); ?> ${selectedListings.length}...`
                    );

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        timeout: 30000, // 30 seconds - allows for API call + processing
                        data: {
                            action: 'lds_process_photo_regeneration',
                            nonce: '<?php echo wp_create_nonce(
                                "lds_import_nonce",
                            ); ?>',
                            listing_id: listing.id,
                            photos_limit: photosLimit
                        },
                        success: function(response) {
                            if (response.success) {
                                successful++;
                            } else {
                                failed++;
                                console.error('Failed for listing ' + listing.id + ':', response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            failed++;
                            console.error('Error for listing ' + listing.id + ':', status, error);
                        },
                        complete: function() {
                            processed++;
                            processNext();
                        }
                    });
                }

                processNext();
            }
        });
        </script>
        <?php
    }

    /**
     * Render AI description regeneration tool section
     */
    public function render_ai_description_regeneration_section()
    {
        // Check if feature is locked (PRO only)
        $is_locked = LDS_Pro_Manager::is_feature_locked(
            "ai_description_regeneration",
        );

        // Check if AI descriptions are enabled and selected provider API is configured
        $ai_enabled = get_option("lds_enable_ai_descriptions", 0);
        $ai_provider = LDS_AI_Provider::get_selected_provider();
        $api_key = LDS_AI_Provider::get_api_key($ai_provider);
        $is_configured = $ai_enabled && !empty($api_key);
        ?>
        <div id="lds_ai_description_regen_content">
            <!-- Info box -->
            <div class="lds-photo-import-info-box">
                <p class="lds-photo-import-info-desc">
                    <?php _e(
                        "This tool regenerates AI-powered descriptions for your existing listings using the current AI settings and prompts. Useful for updating descriptions after changing your AI prompt or language settings.",
                        "listeo-data-scraper",
                    ); ?>
                </p>
            </div>

            <?php if (!$is_configured): ?>
                <!-- Warning if AI is not configured -->
                <div class="lds-photo-import-warning">
                    <p>
                        <strong><?php _e(
                            "AI Descriptions Not Configured",
                            "listeo-data-scraper",
                        ); ?></strong><br>
                        <?php _e(
                            "Please enable AI descriptions and configure your selected AI provider API key in the settings above before using this tool.",
                            "listeo-data-scraper",
                        ); ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Action button -->
                <div class="lds-photo-import-actions">
                    <button type="button" id="lds_open_ai_regen_modal" class="button button-primary" <?php echo $is_locked
                        ? "disabled"
                        : ""; ?>>
                        <span class="dashicons dashicons-edit lds-button-icon"></span>
                        <span class="lds-button-text"><?php _e(
                            "Select Listings",
                            "listeo-data-scraper",
                        ); ?></span>
                        <span class="lds-button-loader"></span>
                    </button>
                </div>

                <!-- Status message -->
                <div id="lds_ai_regen_status"></div>

                <!-- Progress bar -->
                <div id="lds_ai_regen_progress" class="lds-photo-import-progress" style="display: none;">
                    <div class="lds-photo-progress-container">
                        <div id="lds_ai_progress_bar" class="lds-photo-progress-bar">
                            <div class="lds-photo-progress-shimmer"></div>
                        </div>
                    </div>
                    <p id="lds_ai_progress_text" class="lds-photo-progress-text">
                        <?php _e("Preparing...", "listeo-data-scraper"); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Open modal button
            $('#lds_open_ai_regen_modal').on('click', function(e) {
                e.preventDefault();
                console.log('AI Regen button clicked');
                openAIRegenModal();
            });

            // Global state for AI modal
            let aiModalState = {
                currentPage: 1,
                hasMore: false,
                totalListings: 0,
                searchTerm: '',
                isLoading: false
            };

            function escapeAIHtml(value) {
                return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
            }

            function escapeAIAttr(value) {
                return escapeAIHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function renderAIListingItem(listing) {
                const listingId = parseInt(listing.id, 10) || 0;
                const placeId = escapeAIHtml(listing.place_id || '');
                const placeIdAttr = escapeAIAttr(listing.place_id || '');
                const title = escapeAIHtml(listing.title || '');
                const address = escapeAIHtml(listing.address || '');
                const wordCount = parseInt(listing.word_count, 10) || 0;
                const addressPart = address ? address + ' | ' : '';

                return `
                    <div class="lds-listing-item" data-listing-id="${listingId}" style="cursor: pointer;">
                        <input type="checkbox" class="lds-ai-listing-checkbox" data-id="${listingId}" data-place-id="${placeIdAttr}">
                        <div class="lds-listing-info" style="pointer-events: none;">
                            <div class="lds-listing-title">${title}</div>
                            <div class="lds-listing-meta">
                                ${addressPart}
                                ${wordCount} <?php _e(
                                    "words",
                                    "listeo-data-scraper",
                                ); ?> |
                                <?php _e(
                                    "Place ID:",
                                    "listeo-data-scraper",
                                ); ?> ${placeId}
                            </div>
                        </div>
                    </div>
                `;
            }

            function openAIRegenModal() {
                const $btn = $('#lds_open_ai_regen_modal');

                console.log('Opening AI regen modal...');

                // Reset state
                aiModalState = {
                    currentPage: 1,
                    hasMore: false,
                    totalListings: 0,
                    searchTerm: '',
                    isLoading: false
                };

                // Show loading state in button
                $btn.addClass('lds-btn-loading');

                // Fetch first page of listings
                fetchAIListings(1, '', function(data) {
                    $btn.removeClass('lds-btn-loading').prop('disabled', false);
                    showAIListingsModal(data);
                });
            }

            function fetchAIListings(page, search, callback) {
                if (aiModalState.isLoading) return;

                aiModalState.isLoading = true;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_get_listings_for_ai_regen',
                        nonce: '<?php echo wp_create_nonce(
                            "lds_import_nonce",
                        ); ?>',
                        page: page,
                        search: search
                    },
                    success: function(response) {
                        console.log('AJAX response:', response);
                        aiModalState.isLoading = false;

                        if (response.success) {
                            aiModalState.currentPage = response.data.page;
                            aiModalState.hasMore = response.data.has_more;
                            aiModalState.totalListings = response.data.total;

                            if (callback) callback(response.data);
                        } else {
                            alert(response.data.message || '<?php _e(
                                "Failed to load listings.",
                                "listeo-data-scraper",
                            ); ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        aiModalState.isLoading = false;
                        alert('<?php _e(
                            "AJAX error. Please try again.",
                            "listeo-data-scraper",
                        ); ?>');
                    }
                });
            }

            function showAIListingsModal(data) {
                const { listings, total } = data;
                console.log('showAIListingsModal called with', listings.length, 'listings, total:', total);

                // Create modal HTML
                let modalHtml = `
                    <div id="lds_ai_regen_modal">
                        <div class="lds-modal-content">
                            <div class="lds-modal-header">
                                <h2><?php _e(
                                    "Select Listings to Regenerate AI Descriptions",
                                    "listeo-data-scraper",
                                ); ?></h2>
                                <span class="lds-modal-close">&times;</span>
                            </div>
                            <div class="lds-modal-body">
                                <?php echo lds_render_notice(
                                    "<strong>" .
                                        esc_html__(
                                            "Note:",
                                            "listeo-data-scraper",
                                        ) .
                                        "</strong> " .
                                        esc_html__(
                                            "This will replace the current description with a new AI-generated one using your current AI settings.",
                                            "listeo-data-scraper",
                                        ),
                                    "warning",
                                    "lightbulb",
                                    "lds-modal-info",
                                ); ?>
                                <div class="lds-modal-search">
                                    <input type="text" id="lds_ai_search_input" placeholder="<?php _e(
                                        "Search listings...",
                                        "listeo-data-scraper",
                                    ); ?>" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div class="lds-select-all-wrapper">
                                    <input type="checkbox" id="lds_select_all_ai_listings">
                                    <label for="lds_select_all_ai_listings"><?php _e(
                                        "Select All on Page",
                                        "listeo-data-scraper",
                                    ); ?> (${listings.length})</label>
                                    <span class="lds-ai-total-count" style="margin-left: 10px; color: #666;"><?php _e(
                                        "Total:",
                                        "listeo-data-scraper",
                                    ); ?> ${total}</span>
                                </div>
                                <div id="lds_ai_listings_container">
                `;

                listings.forEach(function(listing) {
                    modalHtml += renderAIListingItem(listing);
                });

                modalHtml += `
                                </div>
                                <div id="lds_ai_load_more_container" style="text-align: center; margin-top: 15px; ${aiModalState.hasMore ? '' : 'display: none;'}">
                                    <button type="button" class="button" id="lds_ai_load_more"><?php _e(
                                        "Load More",
                                        "listeo-data-scraper",
                                    ); ?></button>
                                </div>
                            </div>
                            <div class="lds-modal-footer">
                                <div>
                                    <span id="lds_ai_selected_count">0</span> <?php _e(
                                        "listings selected",
                                        "listeo-data-scraper",
                                    ); ?>
                                </div>
                                <div>
                                    <button type="button" class="button" id="lds_ai_modal_cancel"><?php _e(
                                        "Cancel",
                                        "listeo-data-scraper",
                                    ); ?></button>
                                    <button type="button" class="button button-primary" id="lds_ai_modal_start" disabled><?php _e(
                                        "Regenerate Descriptions",
                                        "listeo-data-scraper",
                                    ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal and append new one
                $('#lds_ai_regen_modal').remove();
                $('body').append(modalHtml);

                // Show modal
                $('#lds_ai_regen_modal').fadeIn(300);

                // Modal event handlers
                $('.lds-modal-close, #lds_ai_modal_cancel').on('click', function() {
                    $('#lds_ai_regen_modal').fadeOut(300, function() {
                        $(this).remove();
                    });
                });

                // Select all functionality
                $('#lds_select_all_ai_listings').on('change', function() {
                    $('.lds-ai-listing-checkbox').prop('checked', $(this).is(':checked'));
                    updateAISelectedCount();
                });

                // Individual checkbox change
                $(document).off('change.ldsAiRegen', '.lds-ai-listing-checkbox');
                $(document).on('change.ldsAiRegen', '.lds-ai-listing-checkbox', function() {
                    updateAISelectedCount();
                });

                // Make listing item clickable (except when clicking checkbox directly)
                $(document).off('click.ldsAiRegen', '#lds_ai_listings_container .lds-listing-item');
                $(document).on('click.ldsAiRegen', '#lds_ai_listings_container .lds-listing-item', function(e) {
                    // Don't toggle if clicking the checkbox itself
                    if ($(e.target).is('.lds-ai-listing-checkbox')) {
                        return;
                    }

                    const $checkbox = $(this).find('.lds-ai-listing-checkbox');
                    $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
                });

                // Search functionality with debouncing
                let aiSearchTimeout;
                $('#lds_ai_search_input').on('input', function() {
                    const searchTerm = $(this).val().trim();

                    clearTimeout(aiSearchTimeout);
                    aiSearchTimeout = setTimeout(function() {
                        aiModalState.searchTerm = searchTerm;
                        aiModalState.currentPage = 1;

                        // Show loading state
                        $('#lds_ai_listings_container').html('<div style="text-align: center; padding: 20px;"><?php _e(
                            "Searching...",
                            "listeo-data-scraper",
                        ); ?></div>');

                        fetchAIListings(1, searchTerm, function(data) {
                            // Replace listings
                            let listingsHtml = '';
                            data.listings.forEach(function(listing) {
                                listingsHtml += renderAIListingItem(listing);
                            });

                            $('#lds_ai_listings_container').html(listingsHtml);
                            $('#lds_ai_load_more_container').toggle(aiModalState.hasMore);
                            $('.lds-ai-total-count').html('<?php _e(
                                "Total:",
                                "listeo-data-scraper",
                            ); ?> ' + data.total);
                            $('label[for="lds_select_all_ai_listings"]').html('<?php _e(
                                "Select All on Page",
                                "listeo-data-scraper",
                            ); ?> (' + data.listings.length + ')');
                        });
                    }, 500); // 500ms debounce
                });

                // Load more button
                $('#lds_ai_load_more').on('click', function() {
                    const $btn = $(this);
                    $btn.prop('disabled', true).text('<?php _e(
                        "Loading...",
                        "listeo-data-scraper",
                    ); ?>');

                    fetchAIListings(aiModalState.currentPage + 1, aiModalState.searchTerm, function(data) {
                        // Append new listings
                        let listingsHtml = '';
                        data.listings.forEach(function(listing) {
                            listingsHtml += renderAIListingItem(listing);
                        });

                        $('#lds_ai_listings_container').append(listingsHtml);
                        $('#lds_ai_load_more_container').toggle(aiModalState.hasMore);
                        $btn.prop('disabled', false).text('<?php _e(
                            "Load More",
                            "listeo-data-scraper",
                        ); ?>');

                        // Update page count
                        const totalLoaded = $('#lds_ai_listings_container .lds-listing-item').length;
                        $('label[for="lds_select_all_ai_listings"]').html('<?php _e(
                            "Select All on Page",
                            "listeo-data-scraper",
                        ); ?> (' + totalLoaded + ')');
                    });
                });

                // Start regeneration button
                $('#lds_ai_modal_start').on('click', function() {
                    startAIRegeneration();
                });

                function updateAISelectedCount() {
                    const selectedCount = $('.lds-ai-listing-checkbox:checked').length;
                    $('#lds_ai_selected_count').text(selectedCount);
                    $('#lds_ai_modal_start').prop('disabled', selectedCount === 0);
                }
            }

            function startAIRegeneration() {
                const selectedListings = [];
                $('.lds-ai-listing-checkbox:checked').each(function() {
                    selectedListings.push({
                        id: $(this).data('id'),
                        place_id: $(this).data('place-id')
                    });
                });

                if (selectedListings.length === 0) {
                    alert('<?php _e(
                        "Please select at least one listing.",
                        "listeo-data-scraper",
                    ); ?>');
                    return;
                }

                // Close modal
                $('#lds_ai_regen_modal').fadeOut(300, function() {
                    $(this).remove();
                });

                // Show progress
                $('#lds_ai_regen_progress').show();
                $('#lds_ai_progress_bar').css('width', '0%');
                $('#lds_ai_progress_text').text('<?php _e(
                    "Starting...",
                    "listeo-data-scraper",
                ); ?>');

                // Process listings one by one
                let processed = 0;
                let successful = 0;
                let failed = 0;

                function processNext() {
                    if (processed >= selectedListings.length) {
                        // All done
                        $('#lds_ai_progress_bar').css('width', '100%');
                        $('#lds_ai_progress_text').html(
                            `<strong><?php _e(
                                "Completed!",
                                "listeo-data-scraper",
                            ); ?></strong> ` +
                            `${successful} <?php _e(
                                "successful",
                                "listeo-data-scraper",
                            ); ?>, ${failed} <?php _e(
    "failed",
    "listeo-data-scraper",
); ?>`
                        );
                        $('#lds_ai_regen_status').html('<?php echo lds_get_inline_svg_icon("check"); ?> <?php _e(
                            "AI description regeneration completed",
                            "listeo-data-scraper",
                        ); ?>').addClass('lds-status-success');
                        return;
                    }

                    const listing = selectedListings[processed];
                    const progress = ((processed / selectedListings.length) * 100).toFixed(0);

                    $('#lds_ai_progress_bar').css('width', progress + '%');
                    $('#lds_ai_progress_text').text(
                        `<?php _e(
                            "Processing",
                            "listeo-data-scraper",
                        ); ?> ${processed + 1} <?php _e(
     "of",
     "listeo-data-scraper",
 ); ?> ${selectedListings.length}...`
                    );

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        timeout: 90000, // 90 seconds - AI generation can take longer
                        data: {
                            action: 'lds_process_ai_description_regeneration',
                            nonce: '<?php echo wp_create_nonce(
                                "lds_import_nonce",
                            ); ?>',
                            listing_id: listing.id
                        },
                        success: function(response) {
                            if (response.success) {
                                successful++;
                            } else {
                                failed++;
                                console.error('Failed for listing ' + listing.id + ':', response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            failed++;
                            console.error('Error for listing ' + listing.id + ':', status, error);
                        },
                        complete: function() {
                            processed++;
                            processNext();
                        }
                    });
                }

                processNext();
            }
        });
        </script>
        <?php
    }
}
