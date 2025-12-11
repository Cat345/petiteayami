<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Config;
use YOOtheme\File;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Path;
use YOOtheme\Url;

class FinderController
{
    public static function index(Request $request, Response $response, Config $config)
    {
        $root = $config('app.uploadDir');
        $path = Path::join($root, $request->getQueryParam('folder'));

        if (!str_starts_with($path, $root)) {
            $path = $root;
        }

        $files = [];

        foreach (File::listDir($path, true) ?: [] as $file) {
            $filename = basename($file);

            // ignore hidden files
            if (str_starts_with($filename, '.')) {
                continue;
            }

            $files[] = [
                'name' => $filename,
                'path' => Path::relative($root, $file),
                'url' => Url::relative(Url::to($file)),
                'type' => File::isDir($file) ? 'folder' : 'file',
                'size' => \size_format(File::getSize($file)),
            ];
        }

        return $response->withJson($files);
    }
}
