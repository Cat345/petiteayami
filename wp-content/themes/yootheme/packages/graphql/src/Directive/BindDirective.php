<?php

namespace YOOtheme\GraphQL\Directive;

use YOOtheme\Container;
use YOOtheme\GraphQL\Type\Definition\Directive;
use YOOtheme\GraphQL\Type\Definition\Type;

class BindDirective extends Directive
{
    protected Container $container;

    /**
     * Constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct([
            'name' => 'bind',
            'args' => [
                [
                    'name' => 'id',
                    'type' => Type::string(),
                ],
                [
                    'name' => 'class',
                    'type' => Type::string(),
                ],
                [
                    'name' => 'args',
                    'type' => Type::string(),
                ],
            ],
            'locations' => ['OBJECT', 'ENUM_VALUE', 'FIELD_DEFINITION'],
        ]);

        $this->container = $container;
    }

    /**
     * Register service on container.
     *
     * @param array{id: string, class?: string, args?: string} $params
     */
    public function __invoke(array $params): void
    {
        if (!$this->container->has($params['id'])) {
            $service = $this->container->add($params['id']);

            if (isset($params['class'])) {
                $service->setClass($params['class']);
            }

            if (isset($params['args'])) {
                $service->setArguments(json_decode($params['args'], true));
            }
        }
    }
}
