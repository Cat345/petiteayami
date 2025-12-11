<?php

namespace YOOtheme;

use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;

return [
    '3.1.0-beta.0.3' => function ($node) {
        if (str_contains($node->source->query->name ?? '', '.popular')) {
            foreach (SourceHelper::getPostTypes() as $type) {
                $base = Str::camelCase(SourceHelper::getBase($type), true);
                if (str_ends_with($node->source->query->name, ".popular{$base}")) {
                    $node->source->query->name = str_replace(
                        ".popular{$base}",
                        ".custom{$base}",
                        $node->source->query->name,
                    );
                }
            }
        }
    },
];
