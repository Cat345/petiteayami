<?php

declare (strict_types=1);
namespace FSVendor\Octolize\Brand\UpgradeBox;

use InvalidArgumentException;
use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use FSVendor\WPDesk\ShowDecision\ShouldShowStrategy;
/**
 * Displays an upgrade offer in a settings sidebar.
 */
final class SettingsSidebarBox implements Hookable
{
    private const BENEFIT_ICONS = ['box', 'percent', 'calendar', 'pin'];
    private string $action;
    private ShouldShowStrategy $should_show_strategy;
    private array $offer;
    private array $placement;
    /**
     * @param array $offer Localized offer data with eyebrow, headline, description,
     *                     current and upgrade rows (label and badge), benefits
     *                     (each with an icon and text),
     *                     social_proof, guarantee, cta_label and cta_url.
     * @param array $placement Optional absolute sidebar placement with min_width,
     *                         position_right and align_top_to_element.
     */
    public function __construct(string $action, ShouldShowStrategy $should_show_strategy, array $offer, array $placement = [])
    {
        foreach (['eyebrow', 'headline', 'description', 'social_proof', 'guarantee', 'cta_label', 'cta_url'] as $key) {
            if (!isset($offer[$key]) || !is_string($offer[$key])) {
                throw new InvalidArgumentException("Upgrade offer requires a string value for {$key}.");
            }
        }
        foreach (['current', 'upgrade'] as $variant) {
            if (!isset($offer[$variant]) || !is_array($offer[$variant]) || !isset($offer[$variant]['label'], $offer[$variant]['badge']) || !is_string($offer[$variant]['label']) || !is_string($offer[$variant]['badge'])) {
                throw new InvalidArgumentException("Upgrade offer requires a label and badge for {$variant}.");
            }
        }
        if (!isset($offer['benefits']) || !is_array($offer['benefits'])) {
            throw new InvalidArgumentException('Upgrade offer requires an array of benefits.');
        }
        foreach ($offer['benefits'] as $benefit) {
            if (!is_array($benefit) || !isset($benefit['icon'], $benefit['text']) || !in_array($benefit['icon'], self::BENEFIT_ICONS, \true) || !is_string($benefit['text'])) {
                throw new InvalidArgumentException('Each benefit requires a supported icon and text.');
            }
        }
        $this->action = $action;
        $this->should_show_strategy = $should_show_strategy;
        $this->offer = $offer;
        $this->placement = $placement;
    }
    public function hooks(): void
    {
        add_action($this->action, [$this, 'maybe_display_settings_sidebar']);
    }
    public function maybe_display_settings_sidebar(): void
    {
        if (!$this->should_show_strategy->shouldDisplay()) {
            return;
        }
        $offer = $this->offer;
        $placement = $this->placement;
        include __DIR__ . '/view/settings-sidebar-box.php';
    }
}
