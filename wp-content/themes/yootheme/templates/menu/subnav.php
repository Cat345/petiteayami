<?php

use YOOtheme\Arr;

foreach ($items as $item) {

    // Config
    $navbar = '~theme.navbar';
    $menuposition = '~menu';
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

    // Subtitle
    if ($title && $subtitle = $config("$menuitem.subtitle")) {
        $title = "{$title}<div class=\"tm-subtitle\">{$subtitle}</div>";
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

    // Header
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

        $title = "<a{$this->attrs($link)}>{$title}</a>";
    }

    if ($children) {

        $attrs['class'][] = 'uk-parent';

        if ($level == 1) {

            $attrs_children = [
                'class' => ['uk-dropdown'],
                // Use `hover` instead of `hover, click` so dropdown can't be closed on click if in hover mode
                'mode' => $item->type === 'heading' ? ($config("$navbar.dropdown_click") ? 'click' : 'hover') : false,
                'pos' => $config("$menuitem.dropdown.align") ? "bottom-{$config("$menuitem.dropdown.align")}" : false,
            ];

            $attrs_children['style'] = $config("$menuitem.dropdown.width") ? "width: {$config("$menuitem.dropdown.width")}px;" : '';

            $attrs_children['class'][] = $config("$menuitem.dropdown.size") ? 'uk-dropdown-large' : '';

            if (isset($item->builder)) {

                if (!$config("$menuitem.dropdown.width")) {
                    $attrs_children['style'] = 'width: 400px';
                }

                if ($config("$menuitem.dropdown.padding_remove_horizontal")) {
                    $attrs_children['class'][] = 'uk-padding-remove-horizontal';
                }
                if ($config("$menuitem.dropdown.padding_remove_vertical")) {
                    $attrs_children['class'][] = 'uk-padding-remove-vertical';
                }

                $children = "{$indention}<div{$this->attrs($attrs_children)}>{$item->builder}</div>";

            } else {
                $columns = Arr::columns($item->children, $config("$menuitem.dropdown.columns", 1));
                $columnsCount = count($columns);

                $wrapper = [
                    'class' => [
                        'uk-drop-grid',
                        "uk-child-width-1-{$columnsCount}",
                    ],
                    'uk-grid' => true,
                ];

                $nav_style = $config("$menuitem.dropdown.nav_style") == 'secondary' ? 'uk-nav-secondary' : 'uk-dropdown-nav';
                $columnsStr = '';
                foreach ($columns as $column) {
                    $columnsStr .= "<div><ul class=\"uk-nav {$nav_style}\">\n{$this->self(['items' => $column, 'level' => $level + 1])}</ul></div>";
                }

                $children = "{$indention}<div{$this->attrs($attrs_children)}><div{$this->attrs($wrapper)}>{$columnsStr}</div></div>";
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
