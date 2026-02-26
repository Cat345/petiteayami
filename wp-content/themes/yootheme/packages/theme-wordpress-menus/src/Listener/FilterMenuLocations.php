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
        // use menu locations from theme mod
        if (!$this->config->get('app.isCustomizer') || is_admin()) {
            return $locations;
        }

        $previous = $locations;
        $locations = [];
        $positions = $this->config->get('~theme.menu.positions', []);

        // get menu locations from theme config
        foreach ($positions as $name => $position) {
            if (!empty($position['menu'])) {
                // in some installations there is corrupted data where the term's name
                // is stored in the position instead of its id
                if (!is_int($position['menu'])) {
                    return $previous;
                }

                $locations[$name] = $position['menu'];
            }
        }

        return $locations;
    }
}
