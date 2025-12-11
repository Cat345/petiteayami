<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

class Hook
{
    public static function get($name, array $options = [])
    {
        global $wp_filter;

        if (empty($wp_filter[$name])) {
            return;
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

    public static function doAction($name, array $options = [], array $args = [])
    {
        static::get($name, $options)->do_action($args);
    }

    public static function getFunction(callable $function)
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
