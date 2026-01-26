<?php

namespace YOOtheme\Builder\Source\Filesystem;

use YOOtheme\File;
use YOOtheme\Path;

class FileHelper
{
    /**
     * @var list<string>
     */
    protected array $rootDirs;

    /**
     * @param string|list<string> $rootDirs
     */
    public function __construct($rootDirs)
    {
        $this->rootDirs = (array) $rootDirs;
    }

    /**
     * Query files.
     *
     * @param array<string, mixed> $args
     *
     * @return array<string>
     */
    public function query(array $args = []): array
    {
        $args += ['offset' => 0, 'limit' => 10, 'order' => '', 'order_direction' => 'ASC'];

        if (empty($args['pattern'])) {
            return [];
        }

        $pattern = $args['pattern'];
        $pattern = str_starts_with($pattern, '~') ? $pattern : Path::join('~', $pattern);

        $files = File::glob($pattern, GLOB_NOSORT);

        // filter out any dir
        $files = array_filter(
            $files,
            fn($file) => array_any($this->rootDirs, fn($dir) => str_starts_with($file, $dir)) &&
                is_file($file),
        );

        // order
        if ($args['order'] === 'rand') {
            shuffle($files);
        } else {
            if ($args['order'] === 'name') {
                natcasesort($files);
            }

            // direction
            if ($args['order_direction'] === 'DESC') {
                $files = array_reverse($files);
            }
        }

        // offset/limit
        if ($args['offset'] || $args['limit']) {
            $files = array_slice($files, (int) $args['offset'], (int) $args['limit'] ?: null);
        }

        return $files;
    }
}
