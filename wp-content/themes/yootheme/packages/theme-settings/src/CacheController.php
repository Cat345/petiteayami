<?php

namespace YOOtheme\Theme;

use AppendIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Traversable;
use YOOtheme\Config;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Path;

class CacheController
{
    public static function index(Request $request, Response $response, Config $config): Response
    {
        return $response->withJson(['files' => iterator_count(static::getFiles($config))]);
    }

    public static function clear(Request $request, Response $response, Config $config): Response
    {
        foreach (static::getFiles($config) as $file) {
            if ($file->isFile()) {
                unlink($file->getRealPath());
            } elseif ($file->isDir()) {
                rmdir($file->getRealPath());
            }
        }

        return $response->withJson(['message' => 'success']);
    }

    /**
     * @return Traversable<SplFileInfo>
     */
    protected static function getFiles(Config $config): Traversable
    {
        $roots = [Path::get('~theme/cache'), $config('image.cacheDir')];

        $append = new AppendIterator();

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);

            $append->append(
                new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST),
            );
        }

        return $append;
    }
}
