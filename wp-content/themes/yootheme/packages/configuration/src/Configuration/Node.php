<?php

namespace YOOtheme\Configuration;

abstract class Node
{
    /**
     * Resolves node to their values.
     *
     * @param array<string, mixed> $params
     *
     * @return mixed
     */
    abstract public function resolve(array $params);

    /**
     * Compiles node as parsable string.
     *
     * @param array<string, mixed> $params
     */
    abstract public function compile(array $params): string;

    /**
     * Resolves arguments to their values.
     *
     * @param list<mixed> $arguments
     * @param array<string, mixed> $params
     *
     * @return list<mixed>
     */
    public function resolveArgs(array $arguments, array $params = []): array
    {
        $args = [];

        foreach ($arguments as $argument) {
            $args[] = $argument instanceof Node ? $argument->resolve($params) : $argument;
        }

        return $args;
    }

    /**
     * Compiles arguments as parsable string.
     *
     * @param list<mixed> $arguments
     * @param array<string, mixed> $params
     */
    public function compileArgs(array $arguments, array $params = []): string
    {
        $args = [];

        foreach ($arguments as $argument) {
            $args[] =
                $argument instanceof Node
                    ? $argument->compile($params)
                    : var_export($argument, true);
        }

        return join(', ', $args);
    }
}
