<?php

declare (strict_types=1);
namespace FSVendor\Octolize\Docs\Chat;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use FSVendor\WPDesk\ShowDecision\ShouldShowStrategy;
/**
 * Coordinates the reusable docs chat integration.
 */
final class DocsChat implements Hookable
{
    private string $plugin_slug;
    private string $plugin_url;
    private string $plugin_version;
    private ShouldShowStrategy $show_strategy;
    private ChatSettingsProvider $settings_provider;
    public function __construct(string $plugin_slug, string $plugin_url, string $plugin_version, ShouldShowStrategy $show_strategy, ChatSettingsProvider $settings_provider)
    {
        $this->plugin_slug = $plugin_slug;
        $this->plugin_url = $plugin_url;
        $this->plugin_version = $plugin_version;
        $this->show_strategy = $show_strategy;
        $this->settings_provider = $settings_provider;
    }
    public function hooks(): void
    {
        add_action('admin_init', [$this, 'initialize_chat']);
    }
    /**
     * @internal
     */
    public function initialize_chat(): void
    {
        add_filter('octolize_docs_chat_settings_' . $this->plugin_slug, [$this, 'prepare_chat_settings'], 10, 2);
        (new HookableChatObjects($this->plugin_slug, $this->plugin_url, $this->plugin_version, $this->show_strategy))->hooks();
    }
    /**
     * @internal
     *
     * @param array<string, mixed> $settings     Current chat settings.
     * @param array<string, mixed> $request_data Request data.
     *
     * @return array<string, mixed>
     */
    public function prepare_chat_settings(array $settings, array $request_data): array
    {
        return $this->settings_provider->prepare_settings($settings, $request_data);
    }
}
