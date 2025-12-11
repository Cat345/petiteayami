<?php

namespace YOOtheme\Builder\Templates;

use YOOtheme\Storage;

class TemplateHelper
{
    /**
     * @var array
     */
    public $templates;

    /**
     * Constructor.
     */
    public function __construct(Storage $storage)
    {
        $this->templates = $storage('templates', []);
    }

    public function match(array $template)
    {
        foreach ($this->templates as $id => $templ) {
            if (($templ['status'] ?? '') === 'disabled') {
                continue;
            }

            if (empty($templ['type']) || $templ['type'] !== $template['type']) {
                continue;
            }

            if (isset($template['query'])) {
                if (is_callable($template['query']) && !$template['query']($templ, $template)) {
                    continue;
                }

                if (
                    is_array($template['query']) &&
                    !static::matchQuery($templ, $template['query'])
                ) {
                    continue;
                }
            }

            return ['id' => $id] + $templ;
        }
    }

    protected static function matchQuery(array $templ, array $query)
    {
        foreach ($query as $key => $value) {
            if (empty($templ['query'][$key])) {
                continue;
            }

            if (is_callable($value)) {
                if (!$value($templ['query'][$key], $templ['query'])) {
                    return false;
                }
                continue;
            }

            if (!array_intersect((array) $value, (array) $templ['query'][$key])) {
                return false;
            }
        }

        return true;
    }
}
