<?php

namespace YOOtheme\Configuration;

use YOOtheme\Config;
use YOOtheme\Path;

/**
 * A configuration with cache and value resolving.
 *
 * @example
 * ```json
 * {
 *    // config.json
 *   "yoo": "yoo"
 * }
 * ```
 *
 * ```php
 * use YOOtheme\Configuration;
 *
 * $config = new Configuration('/cache/folder');
 * $config->add('app', ['foo' => 'bar', 'woo' => ['baz' => 'baaz']]);
 * $config->get('app.foo');
 * $config->get('app.woo.baz');
 * \\=> baaz
 *
 * $config->add('app', '/config.json');
 * $config->get('app.yoo');
 * \\=> yoo
 * ```
 */
class Configuration extends Repository implements Config
{
    public const REGEX_PATH = '/^(\.\.?)\/.*/S';

    public const REGEX_STRING = '/\${((?:\w+:)+)?\s*([^}]+)}/S';

    protected Filter $filter;

    protected Resolver $resolver;

    /**
     * @var array<string>
     */
    protected array $cache = [];

    /**
     * Constructor.
     */
    public function __construct(?string $cache = null)
    {
        $values = [
            'env' => $_ENV,
            'server' => $_SERVER,
            'globals' => $GLOBALS,
        ];

        $filter = [
            'path' => [$this, 'resolvePath'],
            'glob' => [$this, 'resolveGlob'],
            'load' => [$this, 'resolveLoad'],
        ];

        $params = [
            'config' => $this,
            'filter' => ($this->filter = new Filter($filter)),
        ];

        $this->values = $values;
        $this->resolver = new Resolver($cache, $params, [
            [$this, 'matchPath'],
            [$this, 'matchString'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function addFilter(string $name, callable $filter)
    {
        $this->filter->add($name, $filter);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addFile(string $index, string $file, bool $replace = true)
    {
        return $this->add($index, $this->loadFile($file), $replace);
    }

    /**
     * @inheritdoc
     */
    public function loadFile(string $file): array
    {
        // load file config
        $config = $this->resolver->loadFile($file);
        $config = $this->resolveExtend($config);

        return $this->resolveImport($config);
    }

    /**
     * Matches paths ./some/path, ~alias/path.
     *
     * @param mixed $value
     *
     * @return mixed|Node
     */
    public function matchPath($value)
    {
        if (!is_string($value) || !preg_match(static::REGEX_PATH, $value, $matches)) {
            return $value;
        }

        if (isset($this->cache[$value])) {
            return $this->cache[$value];
        }

        return $this->cache[$value] = new FilterNode($this->matchString($matches[0]), 'path');
    }

    /**
     * Matches string interpolations ${...}.
     *
     * @param mixed $value
     *
     * @return mixed|Node
     */
    public function matchString($value)
    {
        if (
            !is_string($value) ||
            !preg_match_all(static::REGEX_STRING, $value, $matches, PREG_SET_ORDER)
        ) {
            return $value;
        }

        if (isset($this->cache[$value])) {
            return $this->cache[$value];
        }

        $replace = $arguments = [];

        foreach ($matches as $match) {
            [$search, $filter, $val] = $match;

            $replace[$search] = '%s';
            $arguments[] = $filter
                ? new FilterNode($val, rtrim($filter, ':'))
                : new VariableNode($val);
        }

        $format = strtr($value, $replace + ['%' => '%%']);

        return $this->cache[$value] =
            $format !== '%s' ? new StringNode($format, $arguments) : $arguments[0];
    }

    /**
     * Resolves and evaluates values.
     *
     * @param mixed $value
     * @param array<string, mixed> $params
     *
     * @return mixed
     */
    public function resolve($value, array $params = [])
    {
        return $this->resolver->resolve($value, $params);
    }

    /**
     * Resolves "path: dir/myfile.php" filter.
     */
    public function resolvePath(string $value, string $file): string
    {
        return Path::resolve(dirname($file), $value);
    }

    /**
     * Resolves "glob: dir/file*.php" filter.
     *
     * @return string[]
     */
    public function resolveGlob(string $value, string $file): array
    {
        return glob($this->resolvePath($value, $file)) ?: [];
    }

    /**
     * Resolves "load: dir/file.php" filter.
     *
     * @return array<string, mixed>
     */
    public function resolveLoad(string $value, string $file): array
    {
        return $this->loadFile($this->resolvePath($value, $file));
    }

    /**
     * Resolves "@extend" in config array.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function resolveExtend(array $config): array
    {
        $extends = $config['@extend'] ?? [];

        foreach ((array) $extends as $extend) {
            $config = array_replace_recursive($this->loadFile($extend), $config);
        }

        unset($config['@extend']);

        return $config;
    }

    /**
     * Resolves "@import" in config array.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function resolveImport(array $config): array
    {
        $imports = $config['@import'] ?? [];

        foreach ((array) $imports as $import) {
            $config = array_replace_recursive($config, $this->loadFile($import));
        }

        unset($config['@import']);

        return $config;
    }
}
