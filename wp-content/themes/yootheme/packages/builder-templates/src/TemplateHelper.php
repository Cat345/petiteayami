<?php

namespace YOOtheme\Builder\Templates;

use YOOtheme\Storage;

/**
 * @phpstan-type Template array{
 *     query?: array<string, mixed>,
 *     type?: string,
 *     name: string,
 *     layout: array<string, mixed>,
 *     status?: string,
 *     id?: string,
 * }
 * @phpstan-type View array{
 *     type: string,
 *     query?: array<string, mixed>|callable,
 *     params?: array<string, mixed>,
 *     editUrl?: string,
 * }
 */
class TemplateHelper
{
    /**
     * @var array<Template>
     */
    public $templates;

    public function __construct(Storage $storage)
    {
        $this->templates = $storage('templates', []);
    }

    /**
     * @param View $view
     *
     * @return ?Template
     */
    public function match(array $view): ?array
    {
        foreach ($this->templates as $id => $template) {
            if (($template['status'] ?? '') === 'disabled') {
                continue;
            }

            if (empty($template['type']) || $template['type'] !== $view['type']) {
                continue;
            }

            if (isset($view['query'])) {
                if (
                    (is_callable($view['query']) && !$view['query']($template, $view)) ||
                    (is_array($view['query']) && !static::matchQuery($template, $view['query']))
                ) {
                    continue;
                }
            }

            return ['id' => $id] + $template;
        }

        return null;
    }

    /**
     * @param Template $template
     * @param array<string, mixed> $query
     */
    protected static function matchQuery(array $template, array $query): bool
    {
        foreach ($query as $key => $value) {
            if (empty($template['query'][$key])) {
                continue;
            }

            if (is_callable($value)) {
                if (!$value($template['query'][$key], $template['query'])) {
                    return false;
                }
                continue;
            }

            if (!array_intersect((array) $value, (array) $template['query'][$key])) {
                return false;
            }
        }

        return true;
    }
}
