<?php

namespace YOOtheme\Builder;

class UpdateTransform
{
    protected string $version;

    /**
     * @var array<string, array<string, list<callable>>>
     */
    protected array $updates = [];

    /**
     * @var list<array<string, callable>>
     */
    protected array $globals = [];

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    /**
     * Transform callback.
     *
     * @param array<string, mixed>  $params
     */
    public function __invoke(object $node, array &$params): void
    {
        if (isset($node->version)) {
            $params['version'] = $node->version;
        } elseif (empty($params['version'])) {
            $params['version'] = '1.0.0';
        }

        if (empty($params['parent'])) {
            $node->version = $this->version;
        } else {
            unset($node->version);
        }

        $version = $params['version'];

        // check node version
        if (version_compare($version, $this->version, '>=')) {
            return;
        }

        $params['updateContext'] ??= new \ArrayObject();

        // apply update callbacks
        foreach ($this->resolveUpdates($params['type'], $version) as $update) {
            $update($node, $params);
        }
    }

    /**
     * Adds global updates for any type.
     *
     * @param array<string, callable> $globals
     *
     * @return $this
     */
    public function addGlobals(array $globals)
    {
        $this->globals[] = $globals;

        return $this;
    }

    /**
     * Resolves updates for a type and current version.
     *
     * @return list<callable>
     */
    protected function resolveUpdates(object $type, string $version)
    {
        if (isset($this->updates[$type->name][$version])) {
            return $this->updates[$type->name][$version];
        }

        $updates = $this->globals;

        if (isset($type->updates)) {
            if (is_array($type->updates)) {
                $updates[] = $type->updates;
            } elseif (is_string($type->updates) && is_file($type->updates)) {
                $updates[] = require $type->updates;
            }
        }

        $resolved = [];

        foreach ($updates as $update) {
            foreach ($update as $ver => $func) {
                if (version_compare($ver, $version, '>') && is_callable($func)) {
                    $resolved[$ver][] = $func;
                }
            }
        }

        uksort($resolved, 'version_compare');

        return $this->updates[$type->name][$version] = $resolved
            ? array_merge(...array_values($resolved))
            : [];
    }
}
