<?php

namespace YOOtheme\View;

use YOOtheme\Event;
use YOOtheme\Metadata;

/**
 * Manages HTML elements belonging to the metadata content category.
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Content_categories#Metadata_content
 *
 * @implements \IteratorAggregate<string, MetadataObject>
 */
class MetadataManager implements Metadata, \IteratorAggregate
{
    /**
     * @var list<string>
     */
    protected $prefix = ['article', 'fb', 'og', 'twitter'];

    /**
     * @var array<string, MetadataObject>
     */
    protected $metadata = [];

    /**
     * @inheritdoc
     */
    public function all(string ...$names): array
    {
        if (!$names) {
            return $this->metadata;
        }

        $result = [];

        foreach ($names as $name) {
            $prefix = str_ends_with($name, '*') ? substr($name, 0, -1) : false;

            foreach ($this->metadata as $metadata) {
                if (
                    isset($this->metadata[$name]) ||
                    ($prefix && str_starts_with($metadata->name, $prefix))
                ) {
                    $result[$metadata->name] = $metadata;
                }
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function get(string $name): ?MetadataObject
    {
        return $this->metadata[$name] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function set(string $name, $value, array $attributes = []): MetadataObject
    {
        if (is_array($value) && !is_callable($value)) {
            [$value, $attributes] = [null, array_merge($value, $attributes)];
        }

        $metadata = new MetadataObject($name, $value, $attributes);
        $metadata = $this->resolveMetadata($metadata);
        $metadata = Event::emit('metadata.load|filter', $metadata, $this);

        return $this->metadata[$metadata->name] = $metadata;
    }

    /**
     * @inheritdoc
     */
    public function del(string $name): void
    {
        unset($this->metadata[$name]);
    }

    /**
     * @inheritdoc
     */
    public function merge(array $metadata): void
    {
        foreach ($metadata as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function filter(callable $filter): array
    {
        return array_filter($this->metadata, $filter);
    }

    /**
     * @inheritdoc
     */
    public function render(): string
    {
        return join("\n", $this->metadata);
    }

    /**
     * Returns an iterator for metadata tags.
     *
     * @return \ArrayIterator<string, MetadataObject>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->metadata);
    }

    /**
     * Resolves the metadata.
     */
    protected function resolveMetadata(MetadataObject $metadata): MetadataObject
    {
        if (is_string($metadata->value)) {
            $metadata = $this->resolveAttributes($metadata);
        }

        if ($metadata->tag === 'style' && !isset($metadata->value)) {
            return $metadata->withTag('link')->withAttribute('rel', 'stylesheet');
        }

        if (in_array($metadata->tag, $this->prefix)) {
            return $metadata->withTag('meta');
        }

        return $metadata;
    }

    /**
     * Resolve the metadata attributes.
     */
    protected function resolveAttributes(MetadataObject $metadata): MetadataObject
    {
        if ($metadata->tag === 'base') {
            return $metadata->withAttributes([
                'href' => $metadata->value,
            ]);
        }

        if ($metadata->tag === 'link') {
            return $metadata->withAttributes([
                'href' => $metadata->value,
                'rel' => str_replace('link:', '', $metadata->name),
            ]);
        }

        if ($metadata->tag === 'meta') {
            return $metadata->withAttributes([
                'name' => str_replace('meta:', '', $metadata->name),
                'content' => $metadata->value,
            ]);
        }

        if (in_array($metadata->tag, $this->prefix)) {
            return $metadata->withAttributes([
                'property' => $metadata->name,
                'content' => $metadata->value,
            ]);
        }

        return $metadata;
    }
}
