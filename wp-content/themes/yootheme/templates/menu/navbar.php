<?php

use YOOtheme\Arr;

foreach ($items as $item) {

    // Config
    $menuposition = '~menu';
    $navbar = '~theme.navbar';
    $header = $config("{$menuposition}.position") == 'navbar' ? '~theme.header' : '~theme.mobile.header';
    $menuitem = "~theme.menu.items.{$item->id}";

    // Children
    $children = !empty($item->children) || !empty($item->builder);
    $indention = str_pad("\n", $level + 1, "\t");

    // List item
    $attrs = ['class' => [$item->class ?? '']];

    if ($item->active) {
        $attrs['class'][] = 'uk-active';
    }

    // Title
    $title = $item->title;

    // Parent Icon
    if ($children && $config("$navbar.parent_icon")) {
        $title .= ' <span uk-navbar-parent-icon></span>';
    }

    // Subtitle
    if ($title && $subtitle = $config("$menuitem.subtitle")) {
        $title = "{$title}<div class=\"" . ($level == 1 ? 'uk-navbar-subtitle' : 'uk-nav-subtitle') . "\">{$subtitle}</div>";
    }

    // Image
    $image = $view('~theme/templates/menu/image', ['item' => $item]);

    if ($image && $config("$menuitem.image_only")) {
        $title = '';
    }

    // Title Suffix, e.g. cart quantity
    if ($suffix = $config("$menuitem.title-suffix")) {
        $title .= " {$suffix}";
    }

    // Markup
    if ($title && $subtitle && $image) {
        $title = "<div class=\"uk-grid uk-grid-small" . ($level >= 1 && ($config("$menuposition.image_align") == 'center') ? ' uk-flex-middle' : '') . "\"><div class=\"uk-width-auto\">{$image}</div><div class=\"uk-width-expand\">{$title}</div></div>";
    } elseif ($title && $subtitle) {
        $title = "<div>{$title}</div>";
    } elseif ($image) {
        $title = "{$image} {$title}";
    }

    // Heading
    if ($item->type === 'heading') {

        if (!$children && $level == 1) {
            continue;
        }

        if ($level > 1 && $item->divider && !$children) {
            $title = '';
            $attrs['class'][] = 'uk-nav-divider';
        } elseif ($children) {
            $link = ['role' => 'button'];
            if (!empty($item->anchor_css)) {
                $link['class'][] = $item->anchor_css;
            }
            $title = "<a{$this->attrs($link)}>{$title}</a>";
        } else {
            $attrs['class'][] = 'uk-nav-header';
        }

    // Link
    } else {

        $link = [];

        if (isset($item->url)) {
            $link['href'] = $item->url;

            if ($level > 1 && str_contains((string) $item->url, '#')) {
                $link['uk-scroll'] = true;
            }
        }

        if (!empty($item->target)) {
            $link['target'] = $item->target;
        }

        if (!empty($item->anchor_title)) {
            $link['title'] = $item->anchor_title;
        }

        if (!empty($item->anchor_rel)) {
            $link['rel'] = $item->anchor_rel;
        }

        if (!empty($item->anchor_css)) {
            $link['class'][] = $item->anchor_css;
        }

        if ($image) {
            $link['class'][] = 'uk-preserve-width';
        }

        $title = "<a{$this->attrs($link)}>{$title}</a>";

    }

    if ($children) {

        $attrs['class'][] = 'uk-parent';

        if ($level == 1) {

            $attrs_children = [
                'class' => ['uk-drop uk-navbar-dropdown'],
            ];

            if ($config("$menuitem.dropdown.size")) {
                $attrs_children['class'][] = !$config("$navbar.dropbar") ? 'uk-navbar-dropdown-large' : '';
                $attrs_children['class'][] = $config("$navbar.dropbar") ? 'uk-navbar-dropdown-dropbar-large' : '';
            }
            $attrs_children['class'][] = $config("$navbar.dropbar") && $config("$header.transparent") ? 'uk-dropbar-inset' : '';

            // Use `hover` instead of `hover, click` so dropdown can't be closed on click if in hover mode
            $mode = $item->type === 'heading' ? ($config("$navbar.dropdown_click") ? 'click' : 'hover') : false;

            $stretch = $config("$menuitem.dropdown.stretch");

            if ($mode || $config("$menuitem.dropdown.align") || $stretch || $config("$menuitem.dropdown.size")) {

                $align = $config("$menuitem.dropdown.align") ?: $config("$navbar.dropdown_align");

                $attrs_children += [
                    'mode' => $mode,
                    'pos' => "bottom-{$align}",
                    'stretch' => $stretch ? 'x' : null,
                    'boundary' => $stretch ? (in_array($config("{$menuposition}.position"), ['navbar', 'navbar-split']) ? '.tm-header' : '.tm-header-mobile') . " .uk-{$stretch}" : null,
                ];
            }

            if (!$stretch) {
                $attrs_children['style'][] = $config("$menuitem.dropdown.width") ? "width: {$config("$menuitem.dropdown.width")}px;" : '';
            }

            if (isset($item->builder)) {

                if (!$stretch && !$config("$menuitem.dropdown.width")) {
                    $attrs_children['style'][] = 'width: 400px;';
                }

                if ($config("$menuitem.dropdown.padding_remove_horizontal")) {
                    if ($config("$navbar.dropbar")) {
                        $attrs_children['style'][] = '--uk-position-viewport-offset: 0;';
                    } else {
                        $attrs_children['class'][] = 'uk-padding-remove-horizontal';
                    }
                }

                if ($config("$menuitem.dropdown.padding_remove_vertical")) {
                    $attrs_children['class'][] = 'uk-padding-remove-vertical';
                }

                $children = "{$indention}<div{$this->attrs($attrs_children)}>{$item->builder}</div>";

            } else {

                $columns = Arr::columns($item->children, $config("$menuitem.dropdown.columns", 1));
                $columnsCount = count($columns);

                if ($columnsCount > 1 && !$stretch) {
                    $attrs_children['class'][] = "uk-navbar-dropdown-width-{$columnsCount}";
                }

                $nav_style = $config("$menuitem.dropdown.nav_style") == 'secondary' ? 'uk-nav-secondary' : 'uk-navbar-dropdown-nav';
                $columnsStr = '';
                foreach ($columns as $column) {
                    $columnsStr .= "<div><ul class=\"uk-nav {$nav_style}\">\n{$this->self(['items' => $column, 'level' => $level + 1])}</ul></div>";
                }

                if ($columnsCount > 1) {
                    $wrapper = [
                        'class' => [
                            'uk-drop-grid',
                            "uk-child-width-1-{$columnsCount}",
                        ],
                        'uk-grid' => true,
                    ];
                    $columnsStr = "<div{$this->attrs($wrapper)}>{$columnsStr}</div>";
                }

                $children = "{$indention}<div{$this->attrs($attrs_children)}>{$columnsStr}</div>";
            }

        } else {

            $attrs_children = [];

            if ($level == 2) {
                $attrs_children['class'][] = 'uk-nav-sub';
            }

            $children = "{$indention}<ul{$this->attrs($attrs_children)}>\n{$this->self(['items' => $item->children, 'level' => $level + 1])}</ul>";
        }
    }

    echo "{$indention}<li{$this->attrs($attrs)}>{$title}{$children}</li>";
}
