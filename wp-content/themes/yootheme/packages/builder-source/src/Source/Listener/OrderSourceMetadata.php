<?php

namespace YOOtheme\Builder\Source\Listener;

class OrderSourceMetadata
{
    public static function handle($metadata)
    {
        if (!empty($metadata['fields'])) {
            uasort(
                $metadata['fields'],
                fn($fieldA, $fieldB) => ($fieldA['@order'] ?? 0) - ($fieldB['@order'] ?? 0),
            );
        }

        return $metadata;
    }
}
