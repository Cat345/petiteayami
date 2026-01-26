<?php

namespace YOOtheme\Configuration;

use RuntimeException;

class Resolver
{
    protected int $ctime;

    protected string $cache;

    protected ?string $key = null;

    /**
     * @var string[]
     */
    protected array $path = [];

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * @var callable[]
     */
    protected array $callbacks = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed>  $params
     * @param callable[]            $callbacks
     */
    public function __construct(?string $cache = null, array $params = [], array $callbacks = [])
    {
        if ($cache && is_dir($cache)) {
            $this->cache = $cache;
            $this->ctime = filectime(__FILE__);
        }

        $this->params = $params;
        $this->callbacks = $callbacks;
    }

    /**
     * Resets the key and path.
     */
    public function __clone()
    {
        $this->key = null;
        $this->path = [];
    }

    /**
     * Resolves value and evaluates it after applying callbacks.
     *
     * @param mixed $value
     * @param array<string, mixed> $params
     *
     * @return mixed
     */
    public function resolve($value, array $params = [])
    {
        $resolve = fn($value) => $value instanceof Node
            ? $value->resolve($params + $this->params)
            : $value;

        return $this->resolveValue($value, array_merge($this->callbacks, [$resolve]));
    }

    /**
     * Resolves value recursively.
     *
     * @param mixed      $value
     * @param callable[] $callbacks
     *
     * @return mixed
     */
    public function resolveValue($value, array $callbacks)
    {
        // apply callbacks
        foreach ($callbacks as $callback) {
            $value = $callback($value, $this->key, $this->path);
        }

        if (is_array($value) && !empty($value)) {
            $array = [];
            $depth = count($this->path);

            // update path, if key was changed
            if ($this->key !== end($this->path)) {
                array_splice($this->path, -1, 1, $this->key);
            }

            foreach ($value as $key => $val) {
                // update key and path
                $this->key = $key;
                $this->path[$depth] = $key;

                // resolve recursively
                $val = $this->resolveValue($val, $callbacks);
                $array[$this->key] = $val;
            }

            // set key to last path part
            array_pop($this->path);
            $this->key = end($this->path);

            return $array;
        }

        return $value;
    }

    /**
     * Compiles a parsable string of a value after applying callbacks.
     *
     * @param mixed $value
     * @param array<string, mixed> $params
     */
    public function compile($value, array $params = []): string
    {
        $compile = fn($value) => $value instanceof Node
            ? $value->compile($params + $this->params)
            : var_export($value, true);

        return $this->compileValue($this->resolveValue($value, $this->callbacks), $compile);
    }

    /**
     * Compiles a parsable string representation of a value.
     *
     * @param mixed  $value
     */
    public function compileValue($value, ?callable $callback = null, int $indent = 0): string
    {
        if (is_array($value)) {
            $array = [];
            $assoc = !array_is_list($value);
            $indention = str_repeat('  ', $indent);
            $indentlast = $assoc ? "\n" . $indention : '';

            foreach ($value as $key => $val) {
                $array[] =
                    ($assoc ? "\n  " . $indention . var_export($key, true) . ' => ' : '') .
                    $this->compileValue($val, $callback, $indent + 1);
            }

            return '[' . join(',', $array) . $indentlast . ']';
        }

        return $callback ? $callback($value) : var_export($value, true);
    }

    /**
     * Loads a file.
     *
     * @param array<string, mixed>  $params
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function loadFile(string $file, array $params = []): array
    {
        $params = array_merge($this->params, $params, ['file' => $file]);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === 'php') {
            return $this->loadPhpFile($file, $params);
        }

        if ($extension === 'json') {
            return $this->loadJsonFile($file, $params);
        }

        throw new RuntimeException("Unable to load file '{$file}'");
    }

    /**
     * Loads a PHP file.
     *
     * @param array<string, mixed>  $params
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function loadPhpFile(string $file, array $params = []): array
    {
        extract($params, EXTR_SKIP);

        if (!is_array($value = @include $file)) {
            throw new RuntimeException("Unable to load file '{$file}'");
        }

        return $value;
    }

    /**
     * Loads a JSON config file.
     *
     * @param array<string, mixed>  $params
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function loadJsonFile(string $file, array $params = []): array
    {
        extract($params, EXTR_SKIP);

        $cache = sprintf(
            '%s/%s-%s.php',
            $this->cache,
            pathinfo($file, PATHINFO_FILENAME),
            hash('crc32b', $file),
        );

        if (
            $this->cache &&
            is_file($cache) &&
            filectime($cache) > max($this->ctime, filectime($file))
        ) {
            return include $cache;
        }

        if (!($content = @file_get_contents($file))) {
            throw new RuntimeException("Unable to load file '{$file}'");
        }

        if (!is_array($value = @json_decode($content, true))) {
            throw new RuntimeException("Invalid JSON format in '{$file}'");
        }

        if ($this->cache && $this->writeCacheFile($cache, $value, $params)) {
            return include $cache;
        }

        return $this->resolve($value, $params);
    }

    /**
     * Writes a cache file.
     *
     * @param array<string, mixed>  $value
     * @param array<string, mixed>  $params
     */
    protected function writeCacheFile(string $cache, array $value, array $params = []): bool
    {
        $temp = uniqid("{$this->cache}/temp-" . hash('crc32b', $cache));
        $data = "<?php // \$file = {$params['file']}\n\nreturn {$this->compile(
            $value,
            $params,
        )};\n";

        if (@file_put_contents($temp, $data) && @rename($temp, $cache)) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($cache, true);
            }

            return true;
        }

        // remove temp file if rename failed
        if (file_exists($temp)) {
            @unlink($temp);
        }

        return false;
    }
}
