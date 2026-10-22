<?php

namespace YOOtheme\Theme;

use YOOtheme\Application;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class SettingsController
{
    public static function import(
        Request $request,
        Response $response,
        Updater $updater,
        Application $app
    ): Response
    {
        $config = $request->getParam('config');
        $config = $updater->update($config, ['app' => $app, 'config' => $config]);

        return $response->withJson($config);
    }
}
