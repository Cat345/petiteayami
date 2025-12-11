<?php

use YOOtheme\Theme\Widgets\Listener\AddThemeInputField;
use YOOtheme\Theme\Widgets\Listener\CopyThemeSettings;
use YOOtheme\Theme\Widgets\Listener\LoadWidgetData;
use YOOtheme\Theme\Widgets\Listener\RegisterWidgets;
use YOOtheme\Theme\Widgets\WidgetController;
use YOOtheme\Theme\Widgets\WidgetsListener;
use function YOOtheme\app;

/**
 * Require widget.
 */
require __DIR__ . '/src/BuilderWidget.php';

/**
 * Helper functions.
 */
function get_current_sidebar()
{
    return app(WidgetsListener::class)->sidebar;
}

return [
    'routes' => [
        ['get', '/widget', [WidgetController::class, 'getWidget']],
        ['post', '/widget', [WidgetController::class, 'saveWidget']],
    ],

    'events' => [
        'customizer.init' => [LoadWidgetData::class => '@handle'],
    ],

    'actions' =>
        [
            'widgets_init' => [
                RegisterWidgets::class => '@handle',
            ],

            'in_widget_form' => [
                AddThemeInputField::class => ['handle', 10, 3],
            ],
        ] +
        (!is_admin() && !wp_doing_cron()
            ? [
                'dynamic_sidebar_before' => [
                    WidgetsListener::class => '@beforeSidebar',
                ],

                'dynamic_sidebar_after' => [
                    WidgetsListener::class => '@afterSidebar',
                ],
            ]
            : []),

    'filters' =>
        [
            'widget_update_callback' => [
                CopyThemeSettings::class => ['handle', 10, 3],
            ],
        ] +
        (!is_admin() && !wp_doing_cron()
            ? [
                'widget_display_callback' => [
                    WidgetsListener::class => ['@displayWidget', 10, 3],
                ],

                'is_active_sidebar' => [
                    WidgetsListener::class => ['@isActiveSidebar', null, 2],
                ],

                'sanitize_title' => [
                    WidgetsListener::class => ['@parseSidebarStyle', 10, 2],
                ],
            ]
            : []),

    'services' => [
        WidgetsListener::class => '',
    ],
];
