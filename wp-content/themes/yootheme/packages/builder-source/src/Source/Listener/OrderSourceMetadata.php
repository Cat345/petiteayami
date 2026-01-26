<?php

namespace YOOtheme\Builder\Source\Listener;

class OrderSourceMetadata
{
    /**
     * @param ?array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function handle(?array $metadata): ?array
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
