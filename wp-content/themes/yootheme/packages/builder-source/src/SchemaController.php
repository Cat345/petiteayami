<?php

namespace YOOtheme\Builder;

use YOOtheme\GraphQL\Error\DebugFlag;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class SchemaController
{
    public static function index(Request $request, Response $response, Source $source): Response
    {
        $result = $source->queryIntrospection()->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        return $response->withJson($result['data']['__schema'] ?? $result);
    }
}
