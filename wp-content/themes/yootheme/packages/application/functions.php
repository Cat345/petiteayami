<?php

namespace YOOtheme;

/**
 * Increase xdebug max nesting level.
 */
if ($level = ini_get('xdebug.max_nesting_level')) {
    // String cast can be removed when PHP 8.1 is minimum.
    ini_set('xdebug.max_nesting_level', (string) max((int) $level, 256));
}

/**
 * Gets a service from application.
 *
 * @return mixed
 */
function app(?string $id = null, string ...$ids)
{
    $app = Application::getInstance();

    return $id ? $app($id, ...$ids) : $app;
}
