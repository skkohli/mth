<?php
// /includes/class-lds-ai-provider.php

class LDS_AI_Provider
{
    const OPENAI = "openai";
    const OPENROUTER = "openrouter";

    public static function sanitize_provider($provider)
    {
        $provider = sanitize_text_field($provider);

        return in_array($provider, [self::OPENAI, self::OPENROUTER], true)
            ? $provider
            : self::OPENAI;
    }

    public static function get_selected_provider()
    {
        return self::sanitize_provider(
            get_option("lds_ai_provider", self::OPENAI),
        );
    }

    public static function get_provider_label($provider)
    {
        $provider = self::sanitize_provider($provider);

        return $provider === self::OPENROUTER
            ? __("OpenRouter", "listeo-data-scraper")
            : __("OpenAI", "listeo-data-scraper");
    }

    public static function get_api_key_option($provider)
    {
        return self::sanitize_provider($provider) === self::OPENROUTER
            ? "lds_openrouter_api_key"
            : "lds_openai_api_key";
    }

    public static function get_api_key($provider)
    {
        return get_option(self::get_api_key_option($provider), "");
    }

    public static function get_chat_endpoint($provider)
    {
        return self::sanitize_provider($provider) === self::OPENROUTER
            ? "https://openrouter.ai/api/v1/chat/completions"
            : "https://api.openai.com/v1/chat/completions";
    }

    public static function get_default_model($provider)
    {
        return self::sanitize_provider($provider) === self::OPENROUTER
            ? "google/gemini-3-flash-preview"
            : "gpt-5.4-mini";
    }

    public static function get_openai_models()
    {
        return [
            "gpt-4.1-mini" => __("GPT-4.1-mini (Fastest)", "listeo-data-scraper"),
            "gpt-4.1" => __("GPT-4.1", "listeo-data-scraper"),
            "gpt-5.1" => __("GPT-5.1", "listeo-data-scraper"),
            "gpt-5.2" => __("GPT-5.2", "listeo-data-scraper"),
            "gpt-5.4" => __("GPT-5.4", "listeo-data-scraper"),
            "gpt-5.4-mini" => __("GPT-5.4-mini", "listeo-data-scraper"),
        ];
    }

    public static function get_openrouter_free_models()
    {
        return [
            "nvidia/nemotron-3-ultra-550b-a55b:free" =>
                __("NVIDIA Nemotron 3 Ultra 550B A55B (1M context)", "listeo-data-scraper"),
            "nousresearch/hermes-3-llama-3.1-405b:free" =>
                __("Nous Hermes 3 Llama 3.1 405B (131K context)", "listeo-data-scraper"),
            "moonshotai/kimi-k2.6:free" => __("Kimi K2.6 (262K context)", "listeo-data-scraper"),
            "qwen/qwen3-coder:free" => __("Qwen3 Coder (1M context)", "listeo-data-scraper"),
            "nvidia/nemotron-3-super-120b-a12b:free" =>
                __("NVIDIA Nemotron 3 Super 120B A12B (1M context)", "listeo-data-scraper"),
            "openai/gpt-oss-120b:free" =>
                __("GPT-OSS 120B (131K context)", "listeo-data-scraper"),
            "z-ai/glm-4.5-air:free" => __("GLM 4.5 Air (131K context)", "listeo-data-scraper"),
            "qwen/qwen3-next-80b-a3b-instruct:free" =>
                __("Qwen3 Next 80B A3B Instruct (262K context)", "listeo-data-scraper"),
            "meta-llama/llama-3.3-70b-instruct:free" =>
                __("Llama 3.3 70B Instruct (131K context)", "listeo-data-scraper"),
            "google/gemma-4-31b-it:free" =>
                __("Gemma 4 31B IT (256K context)", "listeo-data-scraper"),
            "google/gemma-4-26b-a4b-it:free" =>
                __("Gemma 4 26B A4B IT (256K context)", "listeo-data-scraper"),
            "openai/gpt-oss-20b:free" => __("GPT-OSS 20B (131K context)", "listeo-data-scraper"),
        ];
    }

    public static function get_openrouter_paid_models()
    {
        return [
            "openai/gpt-5-mini" => __("GPT-5 Mini", "listeo-data-scraper"),
            "openai/gpt-5.1" => __("GPT-5.1", "listeo-data-scraper"),
            "openai/gpt-5.3-chat" => __("GPT-5.3", "listeo-data-scraper"),
            "openai/gpt-5.4" => __("GPT-5.4", "listeo-data-scraper"),
            "openai/gpt-5.4-mini" => __("GPT-5.4 Mini", "listeo-data-scraper"),
            "openai/gpt-5.4-nano" => __("GPT-5.4 Nano", "listeo-data-scraper"),
            "openai/gpt-5.5" => __("GPT-5.5", "listeo-data-scraper"),
            "openai/gpt-4.1" => __("GPT-4.1", "listeo-data-scraper"),
            "openai/gpt-4.1-mini" => __("GPT-4.1 Mini", "listeo-data-scraper"),
            "anthropic/claude-sonnet-4.6" => __("Claude Sonnet 4.6", "listeo-data-scraper"),
            "anthropic/claude-opus-4.6" => __("Claude Opus 4.6", "listeo-data-scraper"),
            "anthropic/claude-haiku-4.5" => __("Claude Haiku 4.5", "listeo-data-scraper"),
            "google/gemini-3.1-pro-preview" => __("Gemini 3.1 Pro", "listeo-data-scraper"),
            "google/gemini-3-flash-preview" => __("Gemini 3 Flash", "listeo-data-scraper"),
            "google/gemini-3.5-flash" => __("Gemini 3.5 Flash", "listeo-data-scraper"),
            "google/gemini-3.1-flash-lite" => __("Gemini 3.1 Flash Lite", "listeo-data-scraper"),
            "google/gemini-2.5-flash" => __("Gemini 2.5 Flash", "listeo-data-scraper"),
            "meta-llama/llama-3.3-70b-instruct" => __("Llama 3.3 70B", "listeo-data-scraper"),
            "mistralai/mistral-large-2512" => __("Mistral Large 3", "listeo-data-scraper"),
            "mistralai/mistral-medium-3.1" => __("Mistral Medium 3.1", "listeo-data-scraper"),
            "deepseek/deepseek-chat-v3" => __("DeepSeek Chat v3", "listeo-data-scraper"),
            "deepseek/deepseek-chat-v3.1" => __("DeepSeek V3.1", "listeo-data-scraper"),
            "deepseek/deepseek-v3.2" => __("DeepSeek V3.2", "listeo-data-scraper"),
            "deepseek/deepseek-v4-pro" => __("DeepSeek V4 Pro", "listeo-data-scraper"),
            "deepseek/deepseek-v4-flash" => __("DeepSeek V4 Flash", "listeo-data-scraper"),
            "z-ai/glm-5.1" => __("GLM 5.1", "listeo-data-scraper"),
            "z-ai/glm-5-turbo" => __("GLM 5 Turbo", "listeo-data-scraper"),
            "moonshotai/kimi-k2.5" => __("Kimi K2.5", "listeo-data-scraper"),
            "qwen/qwen3.5-flash-02-23" => __("Qwen 3.5 Flash", "listeo-data-scraper"),
            "qwen/qwen3.6-plus" => __("Qwen 3.6 Plus", "listeo-data-scraper"),
            "minimax/minimax-m2.7" => __("MiniMax M2.7", "listeo-data-scraper"),
            "x-ai/grok-4" => __("Grok 4", "listeo-data-scraper"),
            "x-ai/grok-4.1-fast" => __("Grok 4.1 Fast", "listeo-data-scraper"),
            "x-ai/grok-4.20" => __("Grok 4.20", "listeo-data-scraper"),
        ];
    }

    public static function get_models($provider)
    {
        if (self::sanitize_provider($provider) === self::OPENROUTER) {
            return array_merge(
                self::get_openrouter_free_models(),
                self::get_openrouter_paid_models(),
            );
        }

        return self::get_openai_models();
    }

    public static function is_valid_model($provider, $model)
    {
        $models = self::get_models($provider);

        return isset($models[$model]);
    }
}
