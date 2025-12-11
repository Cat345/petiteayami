<?php

namespace YOOtheme\Theme\Wordpress;

class FilterHelper
{
    public static function remove(string $name, callable $hook): \Closure
    {
        $priority = has_filter($name, $hook);

        if ($priority !== false) {
            remove_filter($name, $hook, $priority);
        }

        return function () use ($name, $hook, $priority) {
            if ($priority !== false) {
                add_filter($name, $hook, $priority);
            }
        };
    }
}
