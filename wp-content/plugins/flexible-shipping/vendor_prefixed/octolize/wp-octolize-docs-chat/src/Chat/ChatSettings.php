<?php

declare (strict_types=1);
namespace FSVendor\Octolize\Docs\Chat;

/**
 * Configures the chat and its diagnostic metadata.
 */
class ChatSettings
{
    private const WEBHOOK_URL = 'webhook_url';
    private const STREAMING = 'streaming';
    private const INITIAL_MESSAGES = 'initial_messages';
    private const TITLE = 'title';
    private const SUBTITLE = 'subtitle';
    private const FOOTER = 'footer';
    private const INPUT_PLACEHOLDER = 'input_placeholder';
    private const GET_STARTED = 'get_started';
    private const METADATA = 'metadata';
    private const CONSENT = 'consent';
    private const CONSENT_DISABLED = 'consent_disabled';
    private string $plugin = 'Octolize';
    private array $plugin_settings = [];
    private array $plugin_settings_masked_fields = ['api_key', 'api_secret', 'client_id', 'client_secret', 'user_id', 'password', 'access_key', 'account_numer'];
    private array $shipping_method_settings = [];
    private string $current_page = 'Plugin settings';
    private array $settings = [];
    private ?ConsentSettings $consent_settings = null;
    public function set_webhook_url(string $url): void
    {
        $this->settings[self::WEBHOOK_URL] = $url;
    }
    public function set_streaming(bool $streaming): void
    {
        $this->settings[self::STREAMING] = $streaming;
    }
    public function set_initial_messages(array $messages): void
    {
        $this->settings[self::INITIAL_MESSAGES] = $messages;
    }
    public function set_title(string $title): void
    {
        $this->settings[self::TITLE] = $title;
    }
    public function set_subtitle(string $subtitle): void
    {
        $this->settings[self::SUBTITLE] = $subtitle;
    }
    public function set_footer(string $footer): void
    {
        $this->settings[self::FOOTER] = $footer;
    }
    public function set_input_placeholder(string $placeholder): void
    {
        $this->settings[self::INPUT_PLACEHOLDER] = $placeholder;
    }
    public function set_metadata(array $metadata): void
    {
        $this->settings[self::METADATA] = $metadata;
    }
    /**
     * @param string $key
     * @param        $value
     *
     * @return void
     */
    public function add_metadata(string $key, $value): void
    {
        $this->settings[self::METADATA][$key] = $value;
    }
    public function set_plugin_settings(array $settings): void
    {
        $this->plugin_settings = $settings;
    }
    public function set_get_started(string $get_started): void
    {
        $this->settings[self::GET_STARTED] = $get_started;
    }
    /**
     * Enable or disable consent popup explicitly.
     * When true, consent popup is disabled and chat can initialize without asking.
     */
    public function set_consent_disabled(bool $disabled): void
    {
        $this->settings[self::CONSENT_DISABLED] = $disabled;
    }
    public function set_consent(array $consent): void
    {
        $this->consent_settings = ConsentSettings::from_array($consent);
    }
    public function set_consent_settings(ConsentSettings $consent_settings): void
    {
        $this->consent_settings = $consent_settings;
    }
    public function set_shipping_method_settings(array $settings): void
    {
        $this->shipping_method_settings = $settings;
    }
    public function set_plugin(string $plugin): void
    {
        $this->plugin = $plugin;
    }
    public function set_current_page(string $page): void
    {
        $this->current_page = $page;
    }
    public function get_settings(): array
    {
        return $this->add_metadata_to_settings($this->prepare_settings());
    }
    private function prepare_settings(): array
    {
        $settings = $this->settings;
        if (empty($settings[self::WEBHOOK_URL])) {
            $settings[self::WEBHOOK_URL] = 'https://n8n.octolize.dev/webhook/5f60a382-151a-49bf-8baa-52b070c11179/chat';
        }
        if (empty($settings[self::STREAMING])) {
            $settings[self::STREAMING] = \false;
        }
        if (empty($settings[self::INITIAL_MESSAGES])) {
            $settings[self::INITIAL_MESSAGES] = [__('Hi there! 👋 I’m here to help you set up and use the plugin.', 'flexible-shipping'), __('You can ask me about:
- settings and configuration,
- common errors and how to fix them,
- integrations with other tools.

Just tell me what you need help with 🙂', 'flexible-shipping')];
        }
        if (empty($settings[self::TITLE])) {
            $settings[self::TITLE] = __('Hi there! 👋', 'flexible-shipping');
        }
        if (empty($settings[self::SUBTITLE])) {
            $settings[self::SUBTITLE] = __('I’m your AI Assistant. I’ll help you quickly configure the plugin and solve any issues.', 'flexible-shipping');
        }
        if (empty($settings[self::INPUT_PLACEHOLDER])) {
            $settings[self::INPUT_PLACEHOLDER] = __('Type your question to get instant answer..', 'flexible-shipping');
        }
        if (empty($settings[self::FOOTER])) {
            $settings[self::FOOTER] = __('AI can make mistakes. Octolize has access to the conversation held in this chat.', 'flexible-shipping');
        }
        if (empty($settings[self::GET_STARTED])) {
            $settings[self::GET_STARTED] = __('New conversation', 'flexible-shipping');
        }
        $settings[self::CONSENT] = ($this->consent_settings ?? new ConsentSettings())->to_array();
        if (!array_key_exists(self::CONSENT_DISABLED, $settings)) {
            $settings[self::CONSENT_DISABLED] = \false;
        }
        return $settings;
    }
    private function add_metadata_to_settings(array $settings): array
    {
        return array_merge($settings, [self::METADATA => $this->get_metadata()]);
    }
    private function get_metadata(): array
    {
        $metadata = $this->settings[self::METADATA] ?? [];
        $metadata['plugin'] = $this->plugin;
        $metadata['plugin_settings'] = $this->mask_settings($this->plugin_settings);
        $metadata['shipping_method_settings'] = $this->shipping_method_settings;
        $metadata['user_id'] = $this->user_id ?? md5(site_url());
        $metadata['current_page'] = $this->current_page;
        $metadata['wc_store_address'] = $this->prepare_store_address();
        $metadata['wc_shipping_options'] = $this->prepare_shipping_options();
        return $metadata;
    }
    private function prepare_store_address(): array
    {
        return ['woocommerce_store_address' => get_option('woocommerce_store_address', ''), 'woocommerce_store_address_2' => get_option('woocommerce_store_address_2', ''), 'woocommerce_store_city' => get_option('woocommerce_store_city', ''), 'woocommerce_store_postcode' => get_option('woocommerce_store_postcode', ''), 'woocommerce_default_country' => get_option('woocommerce_default_country', '')];
    }
    private function prepare_shipping_options(): array
    {
        return ['woocommerce_allowed_countries' => get_option('woocommerce_allowed_countries', ''), 'woocommerce_ship_to_countries' => get_option('woocommerce_ship_to_countries', ''), 'woocommerce_default_customer_address' => get_option('woocommerce_default_customer_address', '')];
    }
    private function mask_settings(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (in_array($key, $this->plugin_settings_masked_fields, \true)) {
                $settings[$key] = '********';
            }
        }
        return $settings;
    }
}
