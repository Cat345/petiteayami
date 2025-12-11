<?php

namespace YOOtheme\Theme\Styler;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Storage;

class LibraryController
{
    public static function index(Request $request, Response $response, Storage $storage)
    {
        return $response->withJson((object) $storage('styler.library'));
    }

    public static function save(Request $request, Response $response, Storage $storage)
    {
        $id = $request->getParam('id');
        $style = $request->getParam('style');

        if ($id && $style) {
            $storage->set("styler.library.{$id}", $style);
        }

        return $response->withJson(['message' => 'success']);
    }

    public static function delete(Request $request, Response $response, Storage $storage)
    {
        $id = $request->getQueryParam('id');

        if ($id) {
            $storage->del("styler.library.{$id}");
        }

        return $response->withJson(['message' => 'success']);
    }
}
