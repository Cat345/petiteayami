<?php

namespace YOOtheme\GraphQL\Plugin;

use YOOtheme\Container;
use YOOtheme\GraphQL\Directive\BindDirective;
use YOOtheme\GraphQL\Directive\CallDirective;
use YOOtheme\GraphQL\SchemaBuilder;
use YOOtheme\GraphQL\Type\Definition\Type;

class ContainerPlugin
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register directives.
     */
    public function onLoad(SchemaBuilder $schema): void
    {
        $schema->setDirective(new BindDirective($this->container));
        $schema->setDirective(new CallDirective($this->container));
    }

    /**
     * Add directives on type.
     */
    public function onLoadType(Type $type): void
    {
        if (
            property_exists($type, 'config') &&
            ($extensions = $type->config['extensions'] ?? []) &&
            ($directives = $this->getDirectives($extensions))
        ) {
            $type->config['directives'] = array_merge(
                $type->config['directives'] ?? [],
                $directives,
            );
        }
    }

    /**
     * Add directives on field.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    public function onLoadField(Type $type, array $field): array
    {
        $extensions = $field['extensions'] ?? [];

        if ($extensions && ($directives = $this->getDirectives($extensions))) {
            $field['directives'] = array_merge($field['directives'] ?? [], $directives);
        }

        return $field;
    }

    /**
     * Get directives.
     *
     * @param array<array<string, mixed>> $extensions
     *
     * @return array<array{name: 'bind', args: array<string, mixed>}|array{name: 'call', args: array<string, mixed>}>
     */
    protected function getDirectives(array $extensions): array
    {
        $directives = [];

        foreach ($extensions['bind'] ?? [] as $id => $params) {
            $directives[] = $this->bindDirective($id, $params);
        }

        if (isset($extensions['call'])) {
            $directives[] = $this->callDirective($extensions['call']);
        }

        return $directives;
    }

    /**
     * Get @bind directive.
     *
     * @param string|array<string, mixed> $params
     *
     * @return array{name: 'bind', args: array<string, mixed>}
     */
    protected function bindDirective(string $id, $params): array
    {
        if (is_string($params)) {
            $params = ['class' => $params];
        }

        if (isset($params['args'])) {
            $params['args'] = json_encode($params['args']);
        }

        return [
            'name' => 'bind',
            'args' => array_filter(['id' => $id] + $params),
        ];
    }

    /**
     * Get @call directive.
     *
     * @param string|array<string, mixed> $params
     *
     * @return array{name: 'call', args: array<string, mixed>}
     */
    protected function callDirective($params): array
    {
        if (is_string($params) || is_callable($params)) {
            $params = ['func' => $params];
        }

        if (isset($params['args'])) {
            $params['args'] = json_encode($params['args']);
        }

        return [
            'name' => 'call',
            'args' => $params,
        ];
    }
}
