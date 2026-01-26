<?php

namespace YOOtheme\View;

use YOOtheme\File;

class FileLoader
{
    /**
     * @var array<string, string>
     */
    protected array $resolvedPaths = [];

    /**
     * @param array<string, mixed> $parameters
     */
    public function __invoke(string $name, array $parameters, callable $next): string
    {
        if (!str_ends_with(strtolower($name), '.php')) {
            $name .= '.php';
        }

        $this->resolvedPaths[$name] ??= File::find($name);

        return $next($this->resolvedPaths[$name] ?: $name, $parameters);
    }
}
