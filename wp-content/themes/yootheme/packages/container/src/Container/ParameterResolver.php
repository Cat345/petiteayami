<?php

namespace YOOtheme\Container;

use YOOtheme\Container;
use YOOtheme\Reflection;

class ParameterResolver
{
    protected Container $container;

    /**
     * @var array<string, int>
     */
    protected static array $dependencies = [];

    /**
     * Constructor.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves parameters for given function.
     *
     * @param \ReflectionFunction|\ReflectionMethod $function
     * @param array<mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function resolve(\ReflectionFunctionAbstract $function, array $parameters = []): array
    {
        if ($dependencies = $this->resolveDependencies($function, $parameters)) {
            $parameters = array_merge($dependencies, $parameters);
        }

        if ($function->getNumberOfRequiredParameters() > ($count = count($parameters))) {
            $parameter = $function->getParameters()[$count];
            $declaring = $parameter->getDeclaringFunction();

            throw new RuntimeException(
                "Can't resolve {$parameter} for " . Reflection::toString($declaring),
            );
        }

        if ($function instanceof \ReflectionMethod) {
            static::$dependencies["{$function->class}:{$function->name}"] = count($dependencies);
        }

        return $parameters;
    }

    /**
     * Checks if given callable needs resolving.
     */
    public static function needsResolving(callable $callback): bool
    {
        if (!is_array($callback)) {
            return true;
        }

        [$class, $method] = $callback;

        if (is_object($class)) {
            $class = get_class($class);
        }

        return (bool) (static::$dependencies["{$class}:{$method}"] ?? true);
    }

    /**
     * Resolves dependencies for given function.
     *
     * @param array<mixed> $parameters
     *
     * @return list<mixed>
     */
    protected function resolveDependencies(
        \ReflectionFunctionAbstract $function,
        array &$parameters = []
    ): array {
        $dependencies = [];

        foreach ($function->getParameters() as $parameter) {
            if (array_key_exists($name = "\${$parameter->name}", $parameters)) {
                $dependencies[] =
                    $parameters[$name] instanceof \Closure
                        ? $parameters[$name]()
                        : $parameters[$name];

                unset($parameters[$name]);
            } elseif (
                ($classname = $this->resolveClassname($parameter)) &&
                array_key_exists($classname, $parameters)
            ) {
                $dependencies[] = is_string($parameters[$classname])
                    ? $this->container->get($parameters[$classname])
                    : $parameters[$classname];

                unset($parameters[$classname]);
            } elseif (
                $classname &&
                ($this->container->has($classname) || class_exists($classname))
            ) {
                $dependencies[] = $this->container->get($classname);
            } else {
                break;
            }
        }

        return $dependencies;
    }

    /**
     * Resolves classname from parameter type.
     */
    protected function resolveClassname(\ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType && !$type->isBuiltin()
            ? $type->getName()
            : null;
    }
}
