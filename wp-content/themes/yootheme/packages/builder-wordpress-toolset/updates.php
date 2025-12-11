<?php

namespace YOOtheme;

use YOOtheme\Builder\Wordpress\Toolset\Helper;

return [
    '3.0.5.1' => function ($node) {
        if (!Helper::isActive()) {
            return;
        }

        if (!empty($node->source->query->name)) {
            foreach ($node->source->props ?? [] as $prop) {
                if (str_contains($prop->name ?? '', 'toolset.')) {
                    $parts = explode('.', $prop->name);
                    $index = array_search('toolset', $parts);

                    if ($index !== count($parts) - 2) {
                        continue;
                    }

                    if (!($domain = Helper::getDomainFromNode($node))) {
                        continue;
                    }

                    [$slug] = array_slice($parts, $index + 1, 1);

                    $fields = Helper::fields($domain, [$slug, strtr($slug, '_', '-')], false);

                    $field = array_pop($fields);
                    if (Arr::get($field, 'type') === 'image') {
                        $prop->name .= '.url';
                    }

                    if (Arr::get($field, 'type') === 'google_address') {
                        $prop->name .= '.coordinates';
                    }
                }
            }

            if (str_contains($node->source->query->field->name ?? '', 'toolset.')) {
                $parts = explode('.', $node->source->query->field->name);
                $index = array_search('toolset', $parts);

                if ($index === count($parts) - 2 && ($domain = Helper::getDomainFromNode($node))) {
                    [$slug] = array_slice($parts, $index + 1, 1);

                    $fields = Helper::fields($domain, [$slug, strtr($slug, '_', '-')], false);

                    $field = array_pop($fields);
                    if (Arr::get($field, 'type') === 'image') {
                        foreach ($node->source->props ?? [] as $prop) {
                            if ($prop->name === 'value') {
                                $prop->name = 'url';
                            }
                        }
                    }
                }
            }
        }
    },
    '2.6.3' => function ($node) {
        if (str_contains($node->source->query->field->name ?? '', 'toolset.')) {
            $node->source->query->field->name = implode(
                '.',
                array_map(
                    [Str::class, 'snakeCase'],
                    explode('.', $node->source->query->field->name),
                ),
            );
        }

        foreach ($node->source->props ?? [] as $prop) {
            if (str_contains($prop->name ?? '', 'toolset.')) {
                $prop->name = implode(
                    '.',
                    array_map([Str::class, 'snakeCase'], explode('.', $prop->name)),
                );
            }
        }
    },
];
