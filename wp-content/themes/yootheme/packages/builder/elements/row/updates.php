<?php

namespace YOOtheme;

return [
    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset(
            $node->props['match'],
            $node->props['vertical_align']
        );
    },

    '4.3.0-beta.0.5' => function ($node, $params) {
        if ($height = Arr::get($node->props, 'height')) {
            $node->props['height'] = 'viewport';

            if (
                ($params['updateContext']['sectionIndex'] ?? 0) < 2 &&
                empty($params['updateContext']['height'])
            ) {
                $node->props['height_offset_top'] = true;
            }

            if ($height === 'percent') {
                $node->props['height_viewport'] = 80;
            }

            $params['updateContext']['height'] = true;
        }
    },

    '4.3.0-beta.0.1' => function ($node) {
        if (Arr::get($node->props, 'match')) {
            $panels = [];
            $columns = [];
            foreach ($node->children as $column) {
                foreach ($column->children ?? [] as $element) {
                    if (
                        $element->type === 'panel' &&
                        str_starts_with($element->props->panel_style ?? '', 'card-')
                    ) {
                        if (!in_array($column, $columns, true)) {
                            $columns[] = $column;
                        }
                        $panels[] = $element;
                    }
                }
            }

            if (count($columns) > 1) {
                foreach ($columns as $column) {
                    unset($column->props->vertical_align);
                }
                foreach ($panels as $panel) {
                    $panel->props->height_expand = true;
                }
            }
        }
        unset($node->props['match']);
    },

    '2.3.0-beta.1.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if ($style == 'fjord') {
            if (Arr::get($node->props, 'width') === 'default') {
                $node->props['width'] = 'large';
            }
        }
    },

    '2.1.0-beta.1.1' => function ($node) {
        if (($node->props['layout'] ?? '') === '1-1') {
            unset($node->props['layout']);
        }
    },

    '2.1.0-beta.0.1' => function ($node) {
        if (empty($node->props['layout'])) {
            $node->props['layout'] = '1-1';
        }

        switch ($node->props['layout']) {
            case ',':
                $node->props['layout'] = '1-2,1-2';
                break;
            case ',,':
                $node->props['layout'] = '1-3,1-3,1-3';
                break;
            case 'fixed,':
                $node->props['layout'] = 'large,expand';
                break;
            case ',fixed':
                $node->props['layout'] = 'expand,large';
                break;
            case ',fixed,':
                $node->props['layout'] = 'expand,large,expand';
                break;
            case 'fixed,,fixed':
                $node->props['layout'] = 'large,expand,large';
                break;
        }

        if (empty($node->props['breakpoint'])) {
            $node->props['breakpoint'] = 'm';
        }

        $breakpoint = $node->props['breakpoint'];
        $breakpoints = array_slice(
            ['xlarge', 'large', 'medium', 'small', 'default'],
            array_search($breakpoint, ['xl', 'l', 'm', 's', '']),
        );

        if (!empty($node->props['layout'])) {
            $layouts = explode('|', $node->props['layout']);

            while (
                count($layouts) + 1 > count($breakpoints) &&
                isset($layouts[count($layouts) - 1])
            ) {
                unset($layouts[count($layouts) - 1]);
            }

            foreach ($layouts as $widths) {
                $breakpoint = array_shift($breakpoints);
                $prop = "width_{$breakpoint}";

                foreach (explode(',', $widths) as $index => $width) {
                    if (!isset($node->children[$index]->props)) {
                        continue;
                    }

                    if (empty($node->props['fixed_width'])) {
                        $node->props['fixed_width'] = 'large';
                    }
                    if ($node->props['fixed_width'] === 'xxlarge') {
                        $node->props['fixed_width'] = '2xlarge';
                    }

                    if (is_array($node->children[$index]->props)) {
                        $node->children[$index]->props =
                            (object) $node->children[$index]->props;
                    }

                    $node->children[$index]->props->$prop =
                        $width === 'large' ? $node->props['fixed_width'] : $width;
                }
            }
        }

        $count = count($node->children);
        if (!empty($node->props['order_last']) && $count > 1) {
            if (is_array($node->children[$count - 1]->props)) {
                $node->children[$count - 1]->props =
                    (object) $node->children[$count - 1]->props;
            }

            $node->children[$count - 1]->props->order_first =
                $node->props['breakpoint'] ?: 'xs';
        }

        unset(
            $node->props['breakpoint'],
            $node->props['fixed_width'],
            $node->props['order_last'],
        );
    },

    '2.0.0-beta.5.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if (!in_array($style, ['jack-baker', 'morgan-consulting', 'vibe'])) {
            if (Arr::get($node->props, 'width') === 'large') {
                $node->props['width'] = 'xlarge';
            }
        }

        if (
            in_array($style, [
                'craft',
                'district',
                'florence',
                'makai',
                'matthew-taylor',
                'pinewood-lake',
                'summit',
                'tomsen-brody',
                'trek',
                'vision',
                'yard',
            ])
        ) {
            if (Arr::get($node->props, 'width') === 'default') {
                $node->props['width'] = 'large';
            }
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'gutter' => fn($value) => ['column_gap' => $value, 'row_gap' => $value],
        ]);

        if (empty($node->props['layout'])) {
            return;
        }

        switch ($node->props['layout']) {
            case '2-3,':
                $node->props['layout'] = '2-3,1-3';
                break;
            case ',2-3':
                $node->props['layout'] = '1-3,2-3';
                break;
            case '3-4,':
                $node->props['layout'] = '3-4,1-4';
                break;
            case ',3-4':
                $node->props['layout'] = '1-4,3-4';
                break;
            case '1-2,,|1-1,1-2,1-2':
                $node->props['layout'] = '1-2,1-4,1-4|1-1,1-2,1-2';
                break;
            case ',,1-2|1-2,1-2,1-1':
                $node->props['layout'] = '1-4,1-4,1-2|1-2,1-2,1-1';
                break;
            case ',1-2,':
            case ',1-2,|1-2,1-1,1-2':
                $node->props['layout'] = '1-4,1-2,1-4';
                break;
            case ',,,|1-2,1-2,1-2,1-2':
                $node->props['layout'] = '1-4,1-4,1-4,1-4|1-2,1-2,1-2,1-2';
                break;
        }
    },
];
