<?php

namespace YOOtheme\GraphQL\Directive;

use YOOtheme\GraphQL\Type\Definition\Directive;
use YOOtheme\GraphQL\Type\Definition\Type;
use YOOtheme\GraphQL\Utils\Middleware;

class CallDirective extends Directive
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'call',
            'args' => [
                [
                    'name' => 'func',
                    'type' => Type::string(),
                ],
                [
                    'name' => 'args',
                    'type' => Type::string(),
                ],
            ],
            'locations' => ['ENUM_VALUE', 'FIELD_DEFINITION'],
        ]);
    }

    /**
     * Resolve value from function callback.
     *
     * @param array{func: string|callable, args?: string} $params
     */
    public function __invoke(array $params, Middleware $resolver): ?\Closure
    {
        // override default resolver
        $resolver->setHandler($params['func']);

        // merge additional arguments
        if (isset($params['args']) && is_array($arguments = json_decode($params['args'], true))) {
            return static fn($value, $args, $context, $info, $next) => $next(
                $value,
                $args + $arguments,
                $context,
                $info,
            );
        }

        return null;
    }
}
