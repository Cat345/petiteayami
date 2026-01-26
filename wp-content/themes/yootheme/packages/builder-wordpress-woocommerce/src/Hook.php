<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use WP_Hook;

class Hook
{
    /**
     * @param array<string, mixed> $options
     */
    public static function get(string $name, array $options = []): ?WP_Hook
    {
        global $wp_filter;

        if (empty($wp_filter[$name])) {
            return null;
        }

        $clone = clone $wp_filter[$name];
        $clone->remove_all_filters();

        foreach ($wp_filter[$name]->callbacks as $priority => $callbacks) {
            if (isset($options['start']) && $priority < $options['start']) {
                continue;
            }

            if (isset($options['end']) && $priority > $options['end']) {
                continue;
            }

            foreach ($callbacks as ['function' => $callback, 'accepted_args' => $accepted_args]) {
                if (in_array(static::getFunction($callback), $options['skip'] ?? [], true)) {
                    continue;
                }

                $clone->add_filter($name, $callback, $priority, $accepted_args);
            }
        }

        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $args
     */
    public static function doAction(string $name, array $options = [], array $args = []): void
    {
        static::get($name, $options)->do_action($args);
    }

    /**
     * @return callable|string
     */
    protected static function getFunction(callable $function)
    {
        if (is_array($function)) {
            [$class, $method] = $function;

            if (is_object($class)) {
                $class = get_class($class);
            }

            return "{$class}::{$method}";
        }

        return $function;
    }
}
