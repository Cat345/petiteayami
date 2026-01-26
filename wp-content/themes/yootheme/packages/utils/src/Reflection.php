<?php

namespace YOOtheme;

use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;

/**
 * A static class which provides utilities for working with class reflections.
 *
 * @phpstan-type Annotation object{name: string, value: string|null}
 */
abstract class Reflection
{
    public const REGEX_ANNOTATION = '/@(?<name>[\w\\\\]+)(?:\s*(?:\(\s*)?(?<value>.*?)(?:\s*\))?)??\s*(?:\n|\*\/)/';

    /**
     * @var array<string, list<Annotation>>
     */
    public static array $annotations = [];

    /**
     * Gets reflector string representation.
     */
    public static function toString(Reflector $reflector): string
    {
        $string = method_exists($reflector, 'getName') ? $reflector->getName() : '';

        if ($reflector instanceof ReflectionMethod) {
            $string = "{$reflector->getDeclaringClass()->getName()}::{$string}()";
        }

        if (
            ini_get('display_errors') &&
            method_exists($reflector, 'getFileName') &&
            method_exists($reflector, 'getStartLine') &&
            method_exists($reflector, 'getEndLine')
        ) {
            $string .= " in {$reflector->getFileName()}:{$reflector->getStartLine()}-{$reflector->getEndLine()}";
        }

        return $string;
    }

    /**
     * Gets caller info using backtrace.
     *
     * @return array{function: string, line: int, file: string, class: string, object: object, type: string}
     */
    public static function getCaller(int $index = 1): array
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $index + 1)[$index];
    }

    /**
     * Gets reflection class for given classname.
     *
     * @param ReflectionClass|class-string<object>|object $class
     *
     * @return ReflectionClass<object>
     *
     * @example
     * Reflection::getClass('ClassName');
     */
    public static function getClass($class): ReflectionClass
    {
        return $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
    }

    /**
     * Gets the parent reflection classes for a given class.
     *
     * @param ReflectionClass|class-string<object>|object $class
     *
     * @return list<ReflectionClass<object>>
     *
     * @example
     * Reflection::getParentClasses('ClassName');
     */
    public static function getParentClasses($class): array
    {
        $class = static::getClass($class);

        do {
            $classes[] = $class;
        } while ($class = $class->getParentClass());

        return $classes;
    }

    /**
     * Gets the reflection properties for given class.
     *
     * @param ReflectionClass|class-string<object>|object $class
     *
     * @return array<string, ReflectionProperty>
     *
     * @example
     * Reflection::getProperties('ClassName');
     */
    public static function getProperties($class): array
    {
        $properties = [];

        foreach (static::getClass($class)->getProperties() as $property) {
            $property->setAccessible(true);
            $properties[$property->name] = $property;
        }

        return $properties;
    }

    /**
     * Gets the reflection function for given callback.
     *
     * @param callable|string $callback
     * @return ReflectionFunction|ReflectionMethod
     *
     * @example
     * Reflection::getFunction('ClassName::methodName');
     */
    public static function getFunction($callback): ReflectionFunctionAbstract
    {
        if (is_string($callback) && strpos($callback, '::')) {
            $callback = explode('::', $callback);
        }

        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        if (is_object($callback) && !$callback instanceof \Closure) {
            return (new \ReflectionObject($callback))->getMethod('__invoke');
        }

        return new \ReflectionFunction($callback);
    }

    /**
     * Gets the reflection parameters for given callback.
     *
     * @param callable|string $callback
     *
     * @return list<ReflectionParameter>
     *
     * @example
     * Reflection::getParameters('ClassName::methodName');
     */
    public static function getParameters($callback): array
    {
        return static::getFunction($callback)->getParameters();
    }

    /**
     * Gets an annotation by name for given reflector.
     *
     * @example
     * $reflector = Reflection::getAnnotation('ClassName');
     * Reflection::getAnnotation($reflector, 'tag');
     */
    public static function getAnnotation(Reflector $reflector, string $name): ?object
    {
        return static::getAnnotations($reflector, $name)[0] ?? null;
    }

    /**
     * Gets all annotations for given reflector.
     *
     * @return list<Annotation>
     *
     * @example
     * $reflector = Reflection::getClass('ClassName');
     * Reflection::getAnnotations($reflector);
     */
    public static function getAnnotations(Reflector $reflector, ?string $name = null): array
    {
        if ($reflector instanceof ReflectionClass) {
            $key = $reflector->name;
        } elseif ($reflector instanceof ReflectionProperty) {
            $key = "{$reflector->class}.{$reflector->name}";
        } elseif ($reflector instanceof ReflectionMethod) {
            $key = "{$reflector->class}:{$reflector->name}";
        } else {
            $key = null;
        }

        if (!isset(static::$annotations[$key])) {
            $comment = method_exists($reflector, 'getDocComment')
                ? ($reflector->getDocComment() ?:
                '')
                : '';

            if (!$name || strpos($comment, "@{$name}")) {
                static::$annotations[$key] = static::parseAnnotations($comment);
            } elseif (!$comment || !strpos($comment, '@')) {
                return static::$annotations[$key] = [];
            } else {
                return [];
            }
        }

        return $name
            ? static::filterAnnotations(static::$annotations[$key], $name)
            : static::$annotations[$key];
    }

    /**
     * Parses all annotations from given string.
     *
     * @return list<Annotation>
     */
    protected static function parseAnnotations(string $string): array
    {
        $annotations = [];

        if (preg_match_all(static::REGEX_ANNOTATION, $string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $annotations[] = (object) [
                    'name' => $match['name'],
                    'value' => $match['value'] ?? null,
                ];
            }
        }

        return $annotations;
    }

    /**
     * Filters annotations by given name.
     *
     * @param list<Annotation> $annotations
     *
     * @return list<Annotation>
     */
    protected static function filterAnnotations(array $annotations, string $name): array
    {
        $results = [];

        foreach ($annotations as $annotation) {
            if ($annotation->name === $name) {
                $results[] = $annotation;
            }
        }

        return $results;
    }
}
