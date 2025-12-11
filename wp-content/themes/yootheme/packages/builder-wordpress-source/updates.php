<?php

namespace YOOtheme;

return [
    '4.5.0-beta.0.4' => function ($node, $params) {
        if (
            in_array($params['template'] ?? '', ['search', '_search']) &&
            str_contains($node->source->query->name ?? '', '.archive')
        ) {
            $node->source->query->name = str_replace(
                '.archive',
                '.search',
                $node->source->query->name,
            );
        }
    },
    '2.4.0-beta.5' => function ($node) {
        if (($node->source->query->arguments->order ?? '') === 'none') {
            $node->source->query->arguments->order = 'date';
        }
    },
];
