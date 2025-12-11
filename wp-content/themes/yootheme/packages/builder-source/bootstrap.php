<?php

namespace YOOtheme\Builder\Source;

use YOOtheme\Application;
use YOOtheme\Builder;
use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\UpdateTransform;
use YOOtheme\Event;
use YOOtheme\GraphQL\Directive\SliceDirective;
use YOOtheme\GraphQL\Plugin\ContainerPlugin;
use YOOtheme\GraphQL\Type\ObjectScalarType;

return [
    'events' => [
        'source.init' => [Listener\LoadSourceSchema::class => ['@handle', 50]],
        'source.error' => [Listener\LogSourceError::class => '@handle'],
        'source.type.metadata' => [Listener\OrderSourceMetadata::class => 'handle'],
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => '@handle'],
    ],

    'config' => [
        BuilderConfig::class => __DIR__ . '/config/customizer.json',
    ],

    'extend' => [
        Builder::class => function (Builder $builder, $app) {
            $source = $app(SourceTransform::class);

            $builder->addTransform('preload', [$source, 'preload']);
            $builder->addTransform('prerender', [$source, 'prerender'], 2); // Before Placeholder Transform
            $builder->addTransform('presave', new OptimizeTransform());
        },

        UpdateTransform::class => function (UpdateTransform $update) {
            $update->addGlobals(require __DIR__ . '/updates.php');
        },
    ],

    'services' => [
        Source::class => function (SliceDirective $slice, ObjectScalarType $objectType) {
            $source = new Source([new ContainerPlugin(Application::getInstance())]);
            $source->setType($objectType);
            $source->setDirective($slice);

            Event::emit('source.init', $source);

            return $source;
        },
    ],
];
