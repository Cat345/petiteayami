<?php

namespace YOOtheme\Theme\Widgets;

use YOOtheme\Config;
use YOOtheme\ConfigObject;

/**
 * @property array<string, object> $types
 * @property list<array<string, mixed>> $widgets
 * @property list<string> $positions
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

    /**
     * @return array<string, object>
     */
    protected function getTypes(): array
    {
        global $wp_widget_factory;

        $widgets = [];

        foreach ($wp_widget_factory->widgets as $widget) {
            $widgets[$widget->id_base] = $widget->name;
        }

        return $widgets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getWidgets(): array
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

    /**
     * @return list<string>
     */
    protected function getPositions(): array
    {
        return array_keys($this->config->get('theme.positions', []));
    }

    /**
     * @return ?array<string, mixed>
     */
    protected function getInstance(string $id): ?array
    {
        $parts = explode('-', $id);
        $index = array_pop($parts);
        $id_base = implode('-', $parts);
        $instances = get_option("widget_{$id_base}");

        return $instances[$index] ?? null;
    }
}
