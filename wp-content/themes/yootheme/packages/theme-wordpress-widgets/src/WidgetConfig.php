<?php

namespace YOOtheme\Theme\Widgets;

use YOOtheme\Config;
use YOOtheme\ConfigObject;

/**
 * @property array $types
 * @property array $widgets
 * @property array $positions
 * @property bool  $canCreate
 */
class WidgetConfig extends ConfigObject
{
    public Config $config;

    /**
     * Constructor.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        parent::__construct([
            'types' => $this->getTypes(),
            'widgets' => $this->getWidgets(),
            'positions' => $this->getPositions(),
        ]);
    }

    protected function getTypes()
    {
        global $wp_widget_factory;

        $widgets = [];

        foreach ($wp_widget_factory->widgets as $widget) {
            $widgets[$widget->id_base] = $widget->name;
        }

        return $widgets;
    }

    protected function getWidgets()
    {
        $widgets = [];

        foreach (get_option('sidebars_widgets', []) as $sidebar => $ids) {
            if (in_array($sidebar, ['array_version', 'wp_inactive_widgets'])) {
                continue;
            }

            foreach ($ids as $index => $id) {
                if ($widget = $this->getInstance($id)) {
                    $id_base = _get_widget_id_base($id);
                    $widgets[] = [
                        'id' => $id,
                        'type' => $id_base,
                        'title' => $widget['title'] ?? '',
                        'builder' => $id_base === 'builderwidget',
                        'ordering' => $index + 1,
                        'position' => $sidebar,
                    ];
                }
            }
        }

        return $widgets;
    }

    protected function getPositions()
    {
        return array_keys($this->config->get('theme.positions', []));
    }

    protected function getInstance($id)
    {
        $parts = explode('-', $id);
        $index = array_pop($parts);
        $id_base = implode('-', $parts);
        $instances = get_option("widget_{$id_base}");

        return $instances[$index] ?? null;
    }
}
