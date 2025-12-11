<?php

namespace YOOtheme\Theme\Widgets;

use YOOtheme\Builder;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class WidgetController
{
    public static function getWidget(Request $request, Response $response, Builder $builder)
    {
        $widget = static::getInstance($request->getQueryParam('id'));
        $widget['content'] = $builder->load($widget['content'] ?? '');

        return $response->withJson($widget);
    }

    public static function saveWidget(Request $request, Response $response, Builder $builder)
    {
        $id = $request->getParam('id');
        $data = $request->getParam('data', []);

        $request->abortIf(!$id, 400);
        $request->abortIf(
            !current_user_can('edit_theme_options'),
            403,
            'Insufficient User Rights.',
        );

        // save builder content
        if (array_key_exists('content', $data)) {
            $data = [
                'content' => json_encode(
                    $builder
                        ->withParams(['context' => 'save'])
                        ->load(json_encode($data['content'])),
                ),
            ];
        }

        return $response->withJson([
            'message' => static::saveInstance($id, $data) ? 'success' : 'fail',
        ]);
    }

    protected static function getInstance(string $id): ?array
    {
        $parts = explode('-', $id);
        $index = array_pop($parts);
        $id_base = implode('-', $parts);
        $instances = get_option("widget_{$id_base}");

        return $instances[$index] ?? null;
    }

    protected static function saveInstance(string $id, array $data): bool
    {
        $parts = explode('-', $id);
        $index = array_pop($parts);
        $id_base = implode('-', $parts);
        $instances = get_option("widget_{$id_base}");

        // no widget instance found?
        if (empty($instances[$index])) {
            return false;
        }

        // update widget instance
        $instances[$index] = array_replace($instances[$index], $data);

        return update_option("widget_{$id_base}", $instances);
    }
}
