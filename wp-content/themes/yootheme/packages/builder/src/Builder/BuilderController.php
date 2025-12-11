<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Storage;

class BuilderController
{
    public function index(Request $request, Response $response, Storage $storage, Builder $builder)
    {
        $library = $storage('library') ?: [];
        $library = array_map('json_encode', $library);
        $library = array_map([$builder, 'load'], $library);

        return $response->withJson($library);
    }

    public function encodeLayout(Request $request, Response $response, Builder $builder)
    {
        $layout = $request->getParam('layout');

        if (!$layout) {
            $request->abort(400, 'Missing request parameters.');
        }

        return $response->withJson(
            $builder->withParams(['context' => 'save'])->load(json_encode($layout)),
        );
    }

    public function addElement(
        Request $request,
        Response $response,
        Storage $storage,
        Builder $builder
    ) {
        $id = $request->getParam('id');
        $element = $request->getParam('element');

        if (!$id || !$element) {
            $request->abort(400, 'Missing request parameters.');
        }

        $element = $builder->withParams(['context' => 'save'])->load(json_encode($element));

        $storage->set("library.{$id}", $element);

        return $response->withJson($element);
    }

    public function removeElement(Request $request, Response $response, Storage $storage)
    {
        $id = $request->getQueryParam('id');

        if ($id) {
            $storage->del("library.{$id}");
        }

        return $response->withJson(['message' => 'success']);
    }
}
