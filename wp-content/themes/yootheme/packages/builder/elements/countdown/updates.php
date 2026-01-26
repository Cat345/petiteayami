<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.12' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);
        [$style] = explode(':', $config('~theme.style'));

        if ($style === 'quantum-flares') {
            $node->props['countdown_style'] = 'heading-large';
            $node->props['label_color'] = 'emphasis';
            $node->props['label_margin'] = '';

        }
        if ($style === 'summit') {
            $node->props['countdown_style'] = 'heading-small';
            $node->props['countdown_color'] = 'primary';
            $node->props['label_style'] = 'text-small';
            $node->props['label_color'] = 'primary';
            $node->props['label_margin'] = 'remove';
        }
        if ($style === 'vibe') {
            $node->props['countdown_style'] = 'heading-large';
            $node->props['countdown_font_family'] = 'default';
            $node->props['label_style'] = 'text-meta';
            $node->props['label_margin'] = 'xsmall';
        }
        if ($style === 'lilian') {
            $node->props['countdown_style'] = 'heading-small';
            $node->props['label_style'] = 'text-small';
            $node->props['label_color'] = 'primary';
            $node->props['label_margin'] = 'xsmall';
        }
        if (in_array($style, ['quantum-flares', 'vibe', 'lilian'])) {
            $node->props['label_days'] = 'DAYS';
            $node->props['label_hours'] = 'HOURS';
            $node->props['label_minutes'] = 'MINUTES';
            $node->props['label_seconds'] = 'SECONDS';
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'gutter' => fn($value) => ['grid_column_gap' => $value, 'grid_row_gap' => $value],
        ]);
    },
];
