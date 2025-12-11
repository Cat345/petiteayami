<?php

namespace YOOtheme;

return [
    '2.6.3' => function ($node) {
        if (str_contains($node->source->query->field->name ?? '', 'field.')) {
            $node->source->query->field->name = implode(
                '.',
                array_map(
                    [Str::class, 'snakeCase'],
                    explode('.', $node->source->query->field->name),
                ),
            );
        }

        foreach ($node->source->props ?? [] as $prop) {
            if (str_contains($prop->name ?? '', 'field.')) {
                $prop->name = implode(
                    '.',
                    array_map([Str::class, 'snakeCase'], explode('.', $prop->name)),
                );
            }
        }
    },
];
