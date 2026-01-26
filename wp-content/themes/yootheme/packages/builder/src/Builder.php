<?php

namespace YOOtheme;

use YOOtheme\Builder\ElementType;

/**
 * @phpstan-type Node object{type: string, props: array<string, mixed>, children: list<self>}
 */
class Builder
{
    /**
     * @var callable
     */
    protected $renderer;

    /**
     * @var callable
     */
    protected $loader;

    /**
     * @var array<string, object>
     */
    protected array $params;

    /**
     * @var array<string, string|array{name: string}|ElementType>
     */
    protected array $types = [];

    /**
     * @var array<string, list<callable>>
     */
    protected array $resolved = [];

    /**
     * @var array<string, list<callable>>
     */
    protected array $transforms = [];

    /**
     * Constructor.
     *
     * @param callable $loader
     * @param callable $renderer
     * @param array<string, object> $params
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
     * @param array<string, mixed> $params
     *
     * @return static
     */
    public function withParams(array $params = []): self
    {
        $clone = clone $this;
        $clone->params = array_merge($clone->params, $params);

        return $clone;
    }

    /**
     * Loads nodes from data.
     *
     * @param array<string, mixed> $params
     */
    public function load(string $data, array $params = []): ?object
    {
        $params += $this->params + ['context' => null];

        $node = json_decode($data);

        if (!is_object($node)) {
            return null;
        }

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

    /**
     * Renders a node.
     *
     * @param string|array<string, mixed>|object $node
     * @param array<string, mixed> $params
     */
    public function render($node, array $params = []): ?string
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

        if (
            ($type = $this->getType($node->type ?? '')) &&
            ($template = $type->templates[$params['context']] ?? '')
        ) {
            $params = array_merge($params, (array) $node, compact('node'));

            return ($this->renderer)($template, $params);
        }

        return null;
    }

    /**
     * Finds a parent node in path.
     *
     * @param list<Node> $path
     *
     * @return mixed
     */
    public function parent(array $path, string $type, ?string $prop = null)
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

        return null;
    }

    /**
     * Gets a node type.
     */
    public function getType(string $name): ?ElementType
    {
        $type = $this->types[$name] ?? null;

        if (is_string($type)) {
            $type = ($this->loader)($type);
        }

        if (is_array($type)) {
            $type = $this->types[$name] = new ElementType($type);
        }

        return $type;
    }

    /**
     * Gets all node types.
     *
     * @return array<string, ElementType>
     */
    public function getTypes(): array
    {
        return array_combine(
            $keys = array_keys($this->types),
            array_map([$this, 'getType'], $keys),
        );
    }

    /**
     * Adds a node type.
     *
     * @param string|array{name: string}|mixed $name
     * @param ?(string|array{name: string})    $type
     *
     * @return $this
     */
    public function addType($name, $type = null): self
    {
        if (is_string($name) && isset($type)) {
            $this->types[$name] = $type;
        } elseif (isset($name['name'])) {
            $this->types[$name['name']] = $name;
        }

        return $this;
    }

    /**
     * Adds node types from path.
     *
     * @param string|string[] $paths
     *
     * @return $this
     */
    public function addTypePath($paths, ?string $basePath = null): self
    {
        foreach ((array) $paths as $path) {
            $files = glob(Path::resolve($basePath ?? '', $path));
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
     * @return $this
     */
    public function addTransform(string $context, callable $transform, ?int $offset = null): self
    {
        $this->transforms[$context] ??= [];

        Arr::splice($this->transforms[$context], $offset, 0, [$transform]);

        $this->resolved = [];

        return $this;
    }

    /**
     * Applies node transforms.
     *
     * @param array<string, mixed> $params
     */
    protected function applyTransforms(string $context, object $node, array $params): ?object
    {
        $node->props = (array) ($node->props ?? []);
        $params['type'] = $this->getType($node->type ?? '');

        if (!$params['type']) {
            return $node;
        }

        $params['path'] ??= [];
        $params['parent'] ??= null;

        foreach ($this->resolveTransforms($params['type'], "pre{$context}") as $transform) {
            if ($transform($node, $params) === false) {
                return null;
            }
        }

        if (!empty($node->children)) {
            $childParams = $params;

            array_unshift($childParams['path'], $childParams['parent'] = $node);

            // use for-loop to allow adding nodes in transform
            for ($i = 0; $i < count($node->children); $i++) {
                if (
                    !$this->applyTransforms(
                        $context,
                        $node->children[$i],
                        ['i' => $i] + $childParams,
                    )
                ) {
                    array_splice($node->children, $i--, 1);
                }
            }
        }

        foreach ($this->resolveTransforms($params['type'], $context) as $transform) {
            if ($transform($node, $params) === false) {
                return null;
            }
        }

        return $node;
    }

    /**
     * Resolves transforms for a type and context.
     *
     * @return callable[]
     */
    protected function resolveTransforms(ElementType $type, string $context): array
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
