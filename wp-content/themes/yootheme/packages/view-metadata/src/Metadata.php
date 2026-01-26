<?php

namespace YOOtheme;

use YOOtheme\View\MetadataObject;

interface Metadata
{
    /**
     * Gets all metadata tags.
     *
     * @return array<string, MetadataObject>
     */
    public function all(string ...$names): array;

    /**
     * Gets a metadata tag.
     */
    public function get(string $name): ?MetadataObject;

    /**
     * Sets a metadata tag.
     *
     * @param mixed $value
     * @param array<string, mixed> $attributes
     */
    public function set(string $name, $value, array $attributes = []): MetadataObject;

    /**
     * Deletes a metadata tag.
     */
    public function del(string $name): void;

    /**
     * Merges multiple metadata tags.
     *
     * @param array<string, mixed> $metadata
     */
    public function merge(array $metadata): void;

    /**
     * Filters metadata tags using a callback.
     *
     * @return array<string, MetadataObject>
     */
    public function filter(callable $filter): array;

    /**
     * Renders metadata tags.
     */
    public function render(): string;
}
