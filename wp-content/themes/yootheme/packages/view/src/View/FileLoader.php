<?php

namespace YOOtheme\View;

use YOOtheme\File;

class FileLoader
{
    protected array $resolvedPaths = [];

    public function __invoke($name, $parameters, $next)
    {
        if (!str_ends_with(strtolower($name), '.php')) {
            $name .= '.php';
        }

        $this->resolvedPaths[$name] ??= File::find($name);

        return $next($this->resolvedPaths[$name] ?: $name, $parameters);
    }
}
