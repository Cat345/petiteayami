<?php

namespace YOOtheme\Container;

use YOOtheme\Container;
use YOOtheme\Reflection;

class ParameterResolver
{
    /**
     * @var Container
     */
    protected $container;

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
     * @param \ReflectionFunctionAbstract $function
     * @param array                       $parameters
     *
     * @return array
     */
    public function resolve(\ReflectionFunctionAbstract $function, array $parameters = [])
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

        return $parameters;
    }

    /**
     * Resolves dependencies for given function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param array                       $parameters
     *
     * @return array
     */
    protected function resolveDependencies(
        \ReflectionFunctionAbstract $function,
        array &$parameters = []
    ) {
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
     *
     * @param \ReflectionParameter $parameter
     *
     * @return string|null
     */
    protected function resolveClassname(\ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType && !$type->isBuiltin()
            ? $type->getName()
            : null;
    }
}
