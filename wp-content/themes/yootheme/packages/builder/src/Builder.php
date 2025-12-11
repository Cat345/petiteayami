<?php

namespace YOOtheme;

use YOOtheme\Builder\ElementType;

class Builder
{
    /**
     * @var callable
     */
    public $renderer;

    /**
     * @var callable
     */
    public $loader;

    /**
     * @var array
     */
    public $params;

    /**
     * @var array
     */
    public $types = [];

    /**
     * @var array
     */
    public $resolved = [];

    /**
     * @var array
     */
    public $transforms = [];

    /**
     * Constructor.
     *
     * @param callable $loader
     * @param callable $renderer
     * @param array    $params
     */
    public function __construct(callable $loader, callable $renderer, array $params = [])
    {
        $params['builder'] = $this;

        $this->params = $params;
        $this->loader = $loader;
        $this->renderer = $renderer;
    }

    /**
     * Clone callback.
     */
    public function __clone()
    {
        $this->params['builder'] = $this;
    }

    /**
     * Returns an instance with given parameters.
     *
     * @param array $params
     *
     * @return static
     */
    public function withParams(array $params = [])
    {
        $clone = clone $this;
        $clone->params = array_merge($clone->params, $params);

        return $clone;
    }

    /**
     * Loads nodes from data.
     *
     * @param string $data
     * @param array  $params
     *
     * @return object|void
     */
    public function load($data, array $params = [])
    {
        $params += $this->params + ['context' => null];

        if (is_object($node = json_decode($data))) {
            // Workaround for layouts with {type: ""}
            if (empty($node->type)) {
                $node->type = 'layout';
            }

            // Apply (pre)load transforms
            $node = $this->applyTransforms('load', $node, $params);

            // Apply (pre)context transforms
            if ($params['context']) {
                $node = $this->applyTransforms($params['context'], $node, $params);
            }

            return $node;
        }
    }

    /**
     * Renders a node.
     *
     * @param mixed $node
     * @param array $params
     *
     * @return string|void
     */
    public function render($node, array $params = [])
    {
        $params += $this->params + ['context' => 'render'];

        if (is_string($node)) {
            $node = $this->load($node, $params);
        }

        if (is_array($node)) {
            $result = '';

            foreach ($node as $i => $child) {
                $result .= $this->render($child, ['i' => $i] + $params);
            }

            return $result;
        }

        if (isset($node, $this->types[$node->type]->templates[$params['context']])) {
            $templ = $this->types[$node->type]->templates[$params['context']];
            $params = array_merge($params, (array) $node, compact('node'));

            return call_user_func($this->renderer, $templ, $params);
        }
    }

    /**
     * Finds a parent node in path.
     *
     * @param array  $path
     * @param string $type
     * @param string $prop
     *
     * @return mixed|null|void
     */
    public function parent(array $path, $type, $prop = null)
    {
        foreach ($path as $node) {
            if ($node->type !== $type) {
                continue;
            }

            if ($prop) {
                return $node->props[$prop] ?? null;
            }

            return $node;
        }
    }

    /**
     * Adds a node type.
     *
     * @param array $type
     *
     * @return $this
     */
    public function addType(array $type)
    {
        $type = Event::emit('builder.type|filter', $type);

        if (isset($type['name'])) {
            $this->types[$type['name']] = new ElementType($type);
        }

        return $this;
    }

    /**
     * Adds node types from path.
     *
     * @param string|string[] $paths
     * @param string          $basePath
     *
     * @return $this
     */
    public function addTypePath($paths, $basePath = null)
    {
        foreach ((array) $paths as $path) {
            $files = glob(Path::resolve($basePath, $path));
            $types = array_map($this->loader, $files ?: []);

            foreach ($types as $type) {
                $this->addType($type);
            }
        }

        return $this;
    }

    /**
     * Adds a node transform.
     *
     * @param string   $context
     * @param callable $transform
     * @param int|null $offset
     *
     * @return $this
     */
    public function addTransform($context, callable $transform, $offset = null)
    {
        if (!isset($this->transforms[$context])) {
            $this->transforms[$context] = [];
        }

        Arr::splice($this->transforms[$context], $offset, 0, [$transform]);

        $this->resolved = [];

        return $this;
    }

    /**
     * Applies node transforms.
     *
     * @param string $context
     * @param object $node
     * @param array  $params
     *
     * @return object|void
     */
    protected function applyTransforms($context, $node, array $params)
    {
        $node->props = (array) ($node->props ?? []);

        if (isset($node->type, $this->types[$node->type])) {
            $params['type'] = $this->types[$node->type];

            if (empty($params['path'])) {
                $params['path'] = [];
            }

            if (empty($params['parent'])) {
                $params['parent'] = null;
            }

            foreach ($this->resolveTransforms($params['type'], "pre{$context}") as $transform) {
                if ($transform($node, $params) === false) {
                    return;
                }
            }

            if (!empty($node->children)) {
                $children = [];
                $childParams = $params;

                array_unshift($childParams['path'], $childParams['parent'] = $node);

                // use for-loop to allow adding nodes in transform
                for ($i = 0; $i < count($node->children); $i++) {
                    if (
                        $child = $this->applyTransforms(
                            $context,
                            $node->children[$i],
                            ['i' => $i] + $childParams,
                        )
                    ) {
                        $children[] = $child;
                    }
                }

                $node->children = $children;
            }

            foreach ($this->resolveTransforms($params['type'], $context) as $transform) {
                if ($transform($node, $params) === false) {
                    return;
                }
            }
        }

        return $node;
    }

    /**
     * Resolves transforms for a type and context.
     *
     * @param object $type
     * @param string $context
     *
     * @return array
     */
    protected function resolveTransforms($type, $context)
    {
        $key = "{$type->name}:{$context}";

        if (!isset($this->resolved[$key])) {
            $resolved = [];

            if (isset($this->transforms[$context])) {
                $resolved = array_merge($resolved, $this->transforms[$context]);
            }

            if (isset($type->transforms[$context])) {
                $resolved[] = $type->transforms[$context];
            }

            $this->resolved[$key] = $resolved;
        }

        return $this->resolved[$key];
    }
}
