<?php

namespace YOOtheme\Builder\Templates;

use YOOtheme\Arr;
use YOOtheme\Builder;
use YOOtheme\Event;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Storage;

class TemplateController
{
    public static function index(
        Request $request,
        Response $response,
        Storage $storage,
        Builder $builder
    ) {
        $templates = [];

        foreach (array_filter($storage('templates', [])) as $id => $template) {
            if (isset($template['query']) && !$template['query']) {
                $template['query'] = [];
            }

            if (isset($template['layout'])) {
                $template['layout'] = $builder->load(json_encode($template['layout']));
            }

            $template = Event::emit('builder.template.load|filter', ['id' => $id] + $template);

            // Ensures query to be an object, when json encoded
            if (empty($template['query'])) {
                $template['query'] = new \ArrayObject();
            }

            $templates[] = $template;
        }

        return $response->withJson($templates);
    }

    public static function saveTemplate(
        Request $request,
        Response $response,
        Storage $storage,
        Builder $builder
    ) {
        // Can't name 'tpl' request param 'template' because of conflict when PECL extension "json_post" is enabled
        $request->abortIf(!($template = $request->getParam('tpl')) || empty($template['id']), 400);

        if (isset($template['layout'])) {
            $template['layout'] = $builder
                ->withParams(['context' => 'save'])
                ->load(json_encode($template['layout']));
        }

        $storage->set("templates.{$template['id']}", Arr::omit($template, ['id', 'url']));

        return $response->withJson(['message' => 'success']);
    }

    public static function deleteTemplate(Request $request, Response $response, Storage $storage)
    {
        $request->abortIf(!($id = $request->getParam('id')), 400);

        $storage->del("templates.{$id}");

        return $response->withJson(['message' => 'success']);
    }

    public static function reorderTemplates(Request $request, Response $response, Storage $storage)
    {
        $request->abortIf(!($sorting = $request->getParam('templates')), 400);
        $templates = $storage->get('templates');

        $storage->set(
            'templates',
            array_merge(array_intersect_key(array_flip($sorting), $templates), $templates),
        );

        return $response->withJson(['message' => 'success']);
    }
}
