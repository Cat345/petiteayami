<?php

declare (strict_types=1);
namespace FSVendor\Octolize\Docs\Chat;

/**
 * Configures the consent dialog displayed before the chat starts.
 */
final class ConsentSettings
{
    private const TITLE = 'title';
    private const MESSAGE = 'message';
    private const ACCEPT = 'accept';
    private const DECLINE = 'decline';
    private const PRIVACY_POLICY_URL = 'privacy_policy_url';
    private const PRIVACY_LINK_LABEL = 'privacy_link_label';
    private const SUPPORT_URL = 'support_url';
    private const SUPPORT_TEXT = 'support_text';
    private const SUPPORT_LINK_LABEL = 'support_link_label';
    /** @var array<string, mixed> */
    private array $settings = [];
    /**
     * Creates consent settings from the legacy array configuration.
     *
     * @param array<string, mixed> $settings Consent settings.
     */
    public static function from_array(array $settings): self
    {
        $consent_settings = new self();
        $consent_settings->settings = $settings;
        return $consent_settings;
    }
    public function set_title(string $title): void
    {
        $this->settings[self::TITLE] = $title;
    }
    public function set_message(string $message): void
    {
        $this->settings[self::MESSAGE] = $message;
    }
    public function set_accept(string $accept): void
    {
        $this->settings[self::ACCEPT] = $accept;
    }
    public function set_decline(string $decline): void
    {
        $this->settings[self::DECLINE] = $decline;
    }
    public function set_privacy_policy_url(string $privacy_policy_url): void
    {
        $this->settings[self::PRIVACY_POLICY_URL] = $privacy_policy_url;
    }
    public function set_privacy_link_label(string $privacy_link_label): void
    {
        $this->settings[self::PRIVACY_LINK_LABEL] = $privacy_link_label;
    }
    public function set_support_url(string $support_url): void
    {
        $this->settings[self::SUPPORT_URL] = $support_url;
    }
    public function set_support_text(string $support_text): void
    {
        $this->settings[self::SUPPORT_TEXT] = $support_text;
    }
    public function set_support_link_label(string $support_link_label): void
    {
        $this->settings[self::SUPPORT_LINK_LABEL] = $support_link_label;
    }
    /** @return array<string, mixed> */
    public function to_array(): array
    {
        return array_merge($this->default_settings(), $this->settings);
    }
    /** @return array<string, string> */
    private function default_settings(): array
    {
        return [self::TITLE => __('Do you consent to sending data to the chat?', 'flexible-shipping'), self::MESSAGE => __('To start the chat, we need to send your inputs and technical data to the chat service. Read more in our Privacy Policy.', 'flexible-shipping'), self::ACCEPT => __('Accept', 'flexible-shipping'), self::DECLINE => __('Decline', 'flexible-shipping'), self::PRIVACY_POLICY_URL => 'https://octolize.com/terms-of-service/privacy-policy/', self::PRIVACY_LINK_LABEL => __('Privacy Policy', 'flexible-shipping'), self::SUPPORT_URL => 'https://octolize.com/support/', self::SUPPORT_TEXT => __('If you don’t want to give consent, you can contact our support using this form:', 'flexible-shipping'), self::SUPPORT_LINK_LABEL => __('Support form', 'flexible-shipping')];
    }
}
