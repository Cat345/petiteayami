<?php

namespace YOOtheme\Theme\Widgets;

use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Theme\Wordpress\FilterHelper;
use YOOtheme\View;
use function YOOtheme\app;

class WidgetsListener
{
    public ?string $sidebar = null;
    public ?array $position = null;
    public array $widgets = [];

    protected ?string $style = null;
    protected array $logos = [];

    public function isActiveSidebar($active, $sidebar): bool
    {
        // Ensure sidebar is registered (Yoast SEO plugin prevents registering sidebars on /wp-sitemap.xml)
        if (
            !is_registered_sidebar($sidebar) &&
            (!preg_match('/-(push|split)$/', $sidebar, $matches) ||
                !is_registered_sidebar(substr($sidebar, 0, -(strlen($matches[1]) + 1))))
        ) {
            return $active;
        }

        return $active ||
            has_nav_menu($sidebar) ||
            ($sidebar == 'navbar-split' &&
                (has_nav_menu('navbar') || $this->getMenuWidgets('navbar'))) ||
            (in_array($sidebar, [
                'header-split',
                'dialog-push',
                'dialog-mobile-push',
                'header-push',
                'navbar-push',
            ]) &&
                !empty($this->widgets[$sidebar])) ||
            $this->hasHeaderSearchOrSocial($sidebar) ||
            $this->getLogo($sidebar, true) ||
            $this->hasToggle($sidebar);
    }

    public function beforeSidebar(Config $config, $sidebar)
    {
        $this->sidebar = $sidebar;
        if (
            !in_array($sidebar, [
                'header-split',
                'navbar-push',
                'dialog-push',
                'dialog-mobile-push',
            ])
        ) {
            $this->widgets[$sidebar] = [];
        }

        // Reset section transparent header
        if ($sidebar === 'top') {
            $config->set(
                'header.section.prevTransparent',
                $config->get('header.section.transparent'),
            );
            $config->del('header.section.transparent');
        }
    }

    public function afterSidebar(Config $config, View $view, $sidebar)
    {
        global $wp_registered_widgets;

        if ($sidebar === 'top' && null === $config->get('header.section.transparent')) {
            $config->set(
                'header.section.transparent',
                $config->get('header.section.prevTransparent'),
            );
            $config->del('header.section.prevTransparent');
        }

        $items = $this->widgets[$sidebar];

        // Menu (Location) Widget
        if (has_nav_menu($sidebar)) {
            array_unshift(
                $items,
                $this->createMenuWidget($sidebar, $sidebar, get_nav_menu_locations()[$sidebar]),
            );
        }

        if ($sidebar === 'navbar-split') {
            if (
                has_nav_menu('navbar') &&
                in_array($config('~theme.menu.positions.navbar.type'), ['', 'nav'])
            ) {
                array_unshift(
                    $items,
                    $this->createMenuWidget($sidebar, 'navbar', get_nav_menu_locations()['navbar']),
                );
            }

            foreach ($this->getMenuWidgets('navbar') as $id) {
                $widget = $wp_registered_widgets[$id];
                $settings = $widget['callback'][0]->get_settings();
                $params = Arr::get($settings, Arr::get($widget, 'params.0.number', 0), []);
                $menu = Arr::get($params, 'nav_menu');
                $type = Arr::get(
                    json_decode(Arr::get($params, '_theme', '{}'), true) ?: [],
                    'menu_type',
                );

                if ($menu && in_array($type, ['', 'nav'])) {
                    array_unshift($items, $this->createMenuWidget($sidebar, 'navbar', $menu));
                }
            }
        }

        // Logo Widget
        if ($content = $this->getLogo($sidebar)) {
            $widget = $this->createWidget([
                'id' => 'logo',
                'type' => 'logo',
                'content' => $content,
            ]);
            array_unshift($items, $widget);
        }

        // Search Widget
        foreach (['~theme.header.search', '~theme.mobile.header.search'] as $key) {
            $position = explode(':', $config($key, ''), 2);
            if ($sidebar == $position[0]) {
                $widget = $this->getWidget($sidebar, 'WP_Widget_Search');
                $position[1] == 'start'
                    ? array_unshift($items, $widget)
                    : array_push($items, $widget);
            }
        }

        // Social Widget
        foreach (['~theme.header.social', '~theme.mobile.header.social'] as $key) {
            $position = explode(':', $config($key, ''), 2);
            if (
                $sidebar == $position[0] &&
                ($content = trim($view('~theme/templates/socials', ['position' => $sidebar])))
            ) {
                $widget = $this->createWidget([
                    'id' => 'social',
                    'type' => 'social',
                    'content' => $content,
                ]);

                $position[1] == 'start'
                    ? array_unshift($items, $widget)
                    : array_push($items, $widget);
            }
        }

        // Dialog Toggle Widget
        foreach (['~theme.dialog.toggle', '~theme.mobile.dialog.toggle'] as $key) {
            $position = explode(':', $config($key, ''), 2);
            if (
                $sidebar == $position[0] &&
                ($content = trim($view('~theme/templates/header-dialog', ['position' => $sidebar])))
            ) {
                $widget = $this->createWidget([
                    'id' => 'dialog-toggle',
                    'type' => 'dialog-toggle',
                    'content' => $content,
                ]);

                $position[1] == 'start'
                    ? array_unshift($items, $widget)
                    : array_push($items, $widget);
            }
        }

        // Split Header Area
        if ($sidebar == 'header' && $config('~theme.header.layout') == 'stacked-center-c') {
            // Split Auto
            $index = $config('~theme.header.split_index') ?: ceil(count($items) / 2);

            if (!is_registered_sidebar('header-split')) {
                Sidebar::register('header-split', 'Header Split');
                $this->widgets['header-split'] = array_slice($items, $index);
            }
            $items = array_slice($items, 0, $index);
        }

        // Push Navbar Area
        if (
            $sidebar == 'navbar' &&
            $config('~theme.header.layout') == 'stacked-left' &&
            ($index = $config('~theme.header.push_index'))
        ) {
            if (!is_registered_sidebar('navbar-push')) {
                Sidebar::register('navbar-push', 'Navbar Push');
                $this->widgets['navbar-push'] = array_slice($items, $index);
            }
            $items = array_slice($items, 0, $index);
        }

        // Push Dialog Areas
        foreach (
            [
                'dialog' => '~theme.dialog.push_index',
                'dialog-mobile' => '~theme.mobile.dialog.push_index',
            ]
            as $key => $value
        ) {
            if ($sidebar == $key && ($index = $config($value))) {
                if (!is_registered_sidebar("{$key}-push")) {
                    Sidebar::register("{$key}-push", ucfirst($key) . ' Push');
                    $this->widgets["{$key}-push"] = array_slice($items, $index);
                }
                $items = array_slice($items, 0, $index);
            }
        }

        echo $view('~theme/templates/position', [
            'name' => $sidebar,
            'items' => $items,
            'style' => $this->style,
            'position' => $this->position,
        ]);

        $this->style = null;
        $this->sidebar = null;
        $this->position = null;
    }

    public function parseSidebarStyle($title, $raw)
    {
        if (strpos($raw, ':')) {
            [$name, $style] = explode(':', $raw, 2);

            if (is_registered_sidebar($name)) {
                $this->style = $style;
                return $name;
            }
        }

        return $title;
    }

    /**
     * @param Config      $config
     * @param array|false $instance
     * @param \WP_Widget  $widget
     * @param array       $args
     *
     * @return array|false
     */
    public function displayWidget(Config $config, $instance, $widget, $args)
    {
        // store sidebar in case another sidebar is rendered within this widget
        $sidebar = $this->sidebar;

        if ($instance === false || ($sidebar === null && empty($args['yoo_element']))) {
            return $instance;
        }

        $type = strtr(str_replace('nav_menu', 'menu', $widget->id_base), '_', '-');

        // Replace widget settings with temporary customizer widget settings
        if (($temp = $config->get('req.customizer.widget')) && $temp['id'] === $widget->id) {
            $instance = array_replace($instance, Arr::omit($temp, ['id']));
        }

        // Prepare widget theme settings
        $instance['_theme'] =
            ($args['_theme'] ?? []) +
            json_decode($instance['_theme'] ?? '{}', true) +
            $this->getThemeDefaults();

        // Set settings in config for rendering chrome (templates/position.php and templates/module.php)
        $config->update(
            "~theme.modules.{$widget->id}",
            fn($values) => ['is_list' => $this->isListWidget($type)] +
                $instance['_theme'] +
                ($values ?: []),
        );

        // Ignore wpautop filter for text-widgets in header position
        if (
            in_array($sidebar, [
                'navbar',
                'navbar-split',
                'navbar-push',
                'navbar-mobile',
                'header',
                'header-split',
                'header-mobile',
                'toolbar-left',
                'toolbar-right',
                'logo',
                'logo-mobile',
            ])
        ) {
            $restore = FilterHelper::remove('widget_text_content', 'wpautop');
        }

        ob_start();
        $widget->widget($args, $instance);
        $output = ob_get_clean();

        if (isset($restore)) {
            $restore();
        }

        preg_match(
            '/' .
                preg_quote($args['before_widget'], '/') .
                '(.*)' .
                preg_quote($args['after_widget'], '/') .
                '/s',
            $output,
            $content,
        );
        preg_match(
            '/' .
                preg_quote($args['before_title'], '/') .
                '(.*?)' .
                preg_quote($args['after_title'], '/') .
                '/s',
            $output,
            $title,
        );

        $content = $content ? $content[1] : $output;

        if ($title) {
            $content = str_replace($title[0], '', $content);
        }

        // add 'uk-panel' to text widget content div class
        if ($type === 'text') {
            $content = substr_replace(
                $content,
                'uk-panel ',
                strpos($content, 'class="textwidget"') + strlen('class="'),
                0,
            );
        }

        if (
            $sidebar === 'top' &&
            $type !== 'builderwidget' &&
            null === $config->get('header.section.transparent')
        ) {
            $config->set(
                'header.section.transparent',
                (bool) $config('~theme.top.header_transparent'),
            );
        }

        $this->widgets[$sidebar][] = $this->createWidget([
            'id' => $widget->id,
            'type' => $type,
            'title' => $title ? $title[1] : '',
            'content' => $content,
            'instance' => $instance,
            'attrs' => [
                'id' => $widget->id,
                'class' => [trim('widget ' . ($widget->widget_options['classname'] ?? ''))],
            ],
        ]);

        $this->sidebar = $sidebar;

        return false;
    }

    protected function createWidget($widget): object
    {
        static $id = 0;

        return (object) array_merge(
            [
                'id' => 'tm-' . ++$id,
                'title' => '',
                'position' => $this->sidebar,
                'attrs' => ['class' => []],
            ],
            (array) $widget,
        );
    }

    protected function isListWidget($type): bool
    {
        return in_array($type, [
            'recent-posts',
            'pages',
            'recent-comments',
            'archives',
            'categories',
            'meta',
        ]);
    }

    protected function getLogo($sidebar, $check = false): string
    {
        if (
            !in_array($sidebar, ['logo', 'logo-mobile', 'dialog', 'dialog-mobile']) ||
            ($check && str_starts_with($sidebar, 'dialog'))
        ) {
            return '';
        }

        if (!isset($this->logos[$sidebar])) {
            $this->logos[$sidebar] = trim(
                app(View::class)('~theme/templates/header-logo', ['position' => $sidebar]),
            );
        }

        return $this->logos[$sidebar];
    }

    protected function hasToggle($sidebar): bool
    {
        $config = app(Config::class);

        foreach (
            ['~theme.dialog.toggle' => '', '~theme.mobile.dialog.toggle' => '-mobile']
            as $key => $suffix
        ) {
            $position = explode(':', $config($key, ''), 2);
            if ($position[0] === $sidebar && is_active_sidebar("dialog{$suffix}")) {
                return true;
            }
        }
        return false;
    }

    protected function hasHeaderSearchOrSocial($sidebar): bool
    {
        $config = app(Config::class);

        return array_any(
            ['header.search', 'header.social', 'mobile.header.search', 'mobile.header.social'],
            fn($key) => str_starts_with($config("~theme.{$key}", ''), "{$sidebar}:"),
        );
    }

    protected function getMenuWidgets($sidebar): array
    {
        return array_filter(wp_get_sidebars_widgets()[$sidebar] ?? [], function ($widget) {
            global $wp_registered_widgets;
            return Arr::get($wp_registered_widgets, "{$widget}.classname") === 'widget_nav_menu';
        });
    }

    protected function createMenuWidget($sidebar, $location, $menu)
    {
        $config = app(Config::class)("~theme.menu.positions.{$location}", []);

        return $this->getWidget(
            $sidebar,
            'WP_Nav_Menu_Widget',
            [
                'theme_location' => $location,
                'nav_menu' => $menu,
                '_theme' => json_encode(
                    array_combine(
                        array_map(fn($key) => "menu_{$key}", array_keys($config)),
                        $config,
                    ),
                ),
            ],
            $location,
        );
    }

    protected function getWidget($sidebar, $type, $instance = [], $sidebarSettings = null)
    {
        global $wp_widget_factory, $wp_registered_widgets;

        static $i = 1;

        $widget = $wp_widget_factory->widgets[$type];

        // workaround for Elementor overwriting $widget->number in plugins/elementor/includes/widgets/wordpress.php:184
        $number = is_numeric($widget->number) ? $widget->number : count($wp_registered_widgets);

        $widget->_set($number + $i++);

        $this->displayWidget(
            app(Config::class),
            $instance,
            $widget,
            wp_get_sidebar($sidebarSettings ?? $sidebar),
        );

        return end($this->widgets[$sidebar]);
    }

    protected function getThemeDefaults(): array
    {
        static $defaults;

        if (is_null($defaults)) {
            $config = app(Config::class)->loadFile(Path::get('../config/widgets.json', __DIR__));
            $defaults = array_map(fn($field) => $field['default'] ?? '', $config['fields'] ?? []);
        }

        return $defaults;
    }
}
