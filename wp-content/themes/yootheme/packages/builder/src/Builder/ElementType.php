<?php

namespace YOOtheme\Builder;

use JsonSerializable;
use YOOtheme\Config;
use YOOtheme\Configuration\Configuration;
use YOOtheme\Event;
use function YOOtheme\app;

#[\AllowDynamicProperties]
class ElementType implements JsonSerializable
{
    public string $name;
    public string $title;
    public string $group = '';
    public string $icon = '';
    public string $iconSmall = '';
    public bool $element = false;
    public bool $container = false;
    public int $width = 0;

    /**
     * @var array<string, mixed>
     */
    public array $defaults = [];

    /**
     * @var array<string, mixed>
     */
    public array $placeholder = [];

    /**
     * @var string|array<string, mixed>
     */
    public $updates = [];

    /**
     * @var array<string, mixed>
     */
    public array $templates = [];

    /**
     * @var array<string, mixed>
     */
    public array $fields = [];

    /**
     * @var array<string, mixed>
     */
    public array $fieldset = [];

    /**
     * @var array<string, mixed>
     */
    public array $transforms = [];

    protected string $file = '';

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Returns data for JSON serialize.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $type = array_diff_key(
            get_object_vars($this),
            array_flip(['templates', 'transforms', 'updates', 'path', 'file', 'placeholder']),
        );

        if (!empty($this->file)) {
            /** @var Configuration $config */
            $config = app(Config::class);
            $type = $config->resolve($type, ['file' => $this->file]);
        }

        if (empty($type['defaults'])) {
            unset($type['defaults']);
        }

        return Event::emit('builder.type|filter', $type);
    }
}
