<?php

namespace YOOtheme\Theme;

use YOOtheme\Event;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class SystemCheckController
{
    public static function index(
        Request $request,
        Response $response,
        SystemCheck $systemCheck
    ): Response {
        return $response->withJson([
            'requirements' => $systemCheck->getRequirements(),
            'recommendations' => $systemCheck->getRecommendations(),
            'extra' => Event::emit('systemcheck.extra|filter', []),
        ]);
    }
}
