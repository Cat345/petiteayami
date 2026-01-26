<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.2' => function ($node) {
        $hasProp = fn($child, $prop) => !empty($child->props->$prop) || !empty($child->source->props->$prop);
        if (array_all($node->children ?? [], fn($child) => $hasProp($child, 'icon') && !$hasProp($child, 'image'))) {
            if ($node->props['icon_width'] ?? '') {
                $node->props['image_width'] = $node->props['icon_width'];
            }
            if ($node->props['icon_color'] ?? '') {
                $node->props['image_svg_color'] = $node->props['icon_color'];
            }
        }
        unset($node->props['icon_width'], $node->props['icon_color']);
    },

    // moved from 4.0.0-beta.9 to 4.3.9 (previously missing @import)
    // moved from 4.3.9 to 4.5.0-beta.0.4 (ensure to unset prop)
    '4.5.0-beta.0.4' => function ($node) {
        if (($node->props['nav_element'] ?? '') === 'nav') {
            $node->props['html_element'] = 'nav';
        }
        unset($node->props['nav_element']);
    },
];
