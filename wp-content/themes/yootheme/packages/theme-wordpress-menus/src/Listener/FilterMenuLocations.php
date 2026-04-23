<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class FilterMenuLocations
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Loads menu locations from theme config in customizer session.
     *
     * @param array<string, mixed>|false $locations
     *
     * @return array<string, mixed>|false
     */
    public function handle($locations)
    {
        if ($this->config->get('app.isCustomizer') && !is_admin()) {
            $locations = $locations ?: [];
            $changes = $this->config->get('req.customizer.config');

            foreach ($changes ?? [] as $change) {
                $index = implode('.', $change['path'] ?? []);
                if (preg_match('/^menu\.positions\.[^.]+\.menu$/', $index)) {
                    $locations[$change['path'][2]] = $change['value'] ?? '';
                }
            }
        }

        return $locations;
    }
}
