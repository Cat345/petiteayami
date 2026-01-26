<?php

namespace YOOtheme;

/**
 * A static class which provides utilities for working with the file system.
 */
abstract class File
{
    /**
     * Gets an existing file or directory.
     *
     * @example
     * File::get('/path/file.php');
     * // => '/path/file.php'
     */
    public static function get(string $path): ?string
    {
        $path = Path::resolveAlias($path);

        return file_exists($path) ? $path : null;
    }

    /**
     * Checks whether file or directory exists.
     *
     * @example
     * File::exists('/path/resource');
     * // => true
     *
     * File::exists('/path/with/no/resource');
     * // => false
     */
    public static function exists(string $path): bool
    {
        return static::get($path) !== null;
    }

    /**
     * Find file with glob pattern.
     *
     * @example
     * File::find('/path/*.php');
     * // => '/path/file.php'
     */
    public static function find(string $path): ?string
    {
        return ($files = static::glob($path, GLOB_NOSORT)) ? $files[0] : null;
    }

    /**
     * Glob files with braces support.
     *
     * @return list<string>
     *
     * @example
     * File::glob('/path/{*.ext,*.php}');
     * // => ['/path/file.ext', '/path/file.php']
     */
    public static function glob(string $pattern, int $flags = 0): array
    {
        $pattern = Path::resolveAlias($pattern);

        if (defined('GLOB_BRACE') && !str_starts_with($pattern, '{')) {
            return glob($pattern, $flags | GLOB_BRACE) ?: [];
        }

        return static::_glob($pattern, $flags);
    }

    /**
     * Copies file.
     *
     * @example
     * File::copy('/path/file.ext', '/path/dest/file.ext');
     * // => true
     */
    public static function copy(string $from, string $to): bool
    {
        $from = Path::resolveAlias($from);
        $to = Path::resolve(dirname($from), $to);

        return copy($from, $to);
    }

    /**
     * Renames a file or directory.
     *
     * @example
     * File::rename('/path/resource', '/path/renamed');
     * // => true
     */
    public static function rename(string $from, string $to): bool
    {
        $from = Path::resolveAlias($from);
        $to = Path::resolve(dirname($from), $to);

        return rename($from, $to);
    }

    /**
     * Deletes a file.
     *
     * @example
     * File::delete('/path/file.ext');
     * // => true
     */
    public static function delete(string $path): bool
    {
        return unlink(Path::resolveAlias($path));
    }

    /**
     * List files and directories inside the specified path.
     *
     * @param string $path
     * @param bool|string $prefix
     *
     * @return string[]|false
     *
     * @example
     * File::listDir('/path/dir');
     * // => ['Dir1', 'Dir2', 'File.txt']
     *
     * File::listDir('/path/dir', true);
     * // => ['/path/dir/Dir1', '/path/dir/Dir2', '/path/dir/File.txt']
     */
    public static function listDir(string $path, $prefix = false)
    {
        $path = Path::resolveAlias($path);

        if (!static::exists($path)) {
            return false;
        }

        if ($files = scandir($path)) {
            $files = array_values(array_diff($files, ['.', '..']));

            if ($prefix) {
                foreach ($files as &$file) {
                    $file = Path::join($path, $file);
                }
            }
        }

        return $files;
    }

    /**
     * Makes directory.
     *
     * @example
     * File::makeDir('/path/dir/to/make');
     * // => true
     */
    public static function makeDir(string $path, int $mode = 0777, bool $recursive = false): bool
    {
        $path = Path::resolveAlias($path);

        return is_dir($path) || @mkdir($path, $mode, $recursive) || is_dir($path);
    }

    /**
     * Removes directory recursively.
     *
     * @example
     * File::deleteDir('/path/dir/to/delete');
     * // => true
     */
    public static function deleteDir(string $path): bool
    {
        $path = Path::resolveAlias($path);
        $files = static::listDir($path, true);

        if (false === $files) {
            return false;
        }

        foreach ($files as $file) {
            // delete directory recursively
            if (is_dir($file) && !static::deleteDir($file)) {
                return false;
            }

            // delete file
            if (is_file($file) && !unlink($file)) {
                return false;
            }
        }

        return rmdir($path);
    }

    /**
     * Gets the last access time of file.
     *
     * @example
     * File::getATime('/path/file.ext');
     * // => 1551693515
     */
    public static function getATime(string $path): ?int
    {
        $time = fileatime(Path::resolveAlias($path));

        return is_int($time) ? $time : null;
    }

    /**
     * Gets the inode change time of file.
     *
     * @example
     * File::getCTime('/path/file.ext');
     * // => 1551693515
     */
    public static function getCTime(string $path): ?int
    {
        $time = filectime(Path::resolveAlias($path));

        return is_int($time) ? $time : null;
    }

    /**
     * Gets the last modified time of file.
     *
     * @example
     * File::getMTime('/path/file.ext');
     * // => 1551693515
     */
    public static function getMTime(string $path): ?int
    {
        $time = filemtime(Path::resolveAlias($path));

        return is_int($time) ? $time : null;
    }

    /**
     * Gets the file size.
     *
     * @example
     * File::getSize('/path/file.ext');
     * // => 4
     */
    public static function getSize(string $path): ?int
    {
        $size = filesize(Path::resolveAlias($path));

        return is_int($size) ? $size : null;
    }

    /**
     * Gets the file mime content type.
     *
     * @return false|string
     *
     * @example
     * File::getMimetype('/path/file.ext');
     * // => text/plain
     */
    public static function getMimetype(string $path)
    {
        $path = Path::resolveAlias($path);

        return function_exists('finfo_file')
            ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path)
            : mime_content_type($path);
    }

    /**
     * Gets the file extension.
     *
     * @example
     * File::getExtension('/path/file.ext');
     * // => ext
     */
    public static function getExtension(string $path): string
    {
        return pathinfo(Path::resolveAlias($path), PATHINFO_EXTENSION);
    }

    /**
     * Gets the contents from file.
     *
     * @example
     * File::getContents('/path/file.ext');
     * // => filecontent
     */
    public static function getContents(string $path): ?string
    {
        $data = file_get_contents(Path::resolveAlias($path));

        return is_string($data) ? $data : null;
    }

    /**
     * Writes the contents to file.
     *
     * @param mixed  $data
     *
     * @example
     * File::putContents('/path/file.ext', 'content');
     * // => true
     */
    public static function putContents(string $path, $data, int $flags = 0): ?int
    {
        $bytes = file_put_contents(Path::resolveAlias($path), $data, $flags);

        return is_int($bytes) ? $bytes : null;
    }

    /**
     * Checks if is a directory.
     *
     * @example
     * File::isDir('/path/dir');
     * // => true
     */
    public static function isDir(string $path): bool
    {
        return is_dir(Path::resolveAlias($path));
    }

    /**
     * Checks if is a file.
     *
     * @example
     * File::isFile('/path/file.ext');
     * // => true
     */
    public static function isFile(string $path): bool
    {
        return is_file(Path::resolveAlias($path));
    }

    /**
     * Checks if is a link.
     *
     * @example
     * File::isLink('/path/link');
     * // => true
     */
    public static function isLink(string $path): bool
    {
        return is_link(Path::resolveAlias($path));
    }

    /**
     * Glob files with braces support (Polyfill).
     *
     * @return list<string>
     */
    protected static function _glob(string $pattern, int $flags = 0): array
    {
        $files = [];

        foreach (Str::expandBraces($pattern) as $file) {
            $files = array_merge($files, glob($file, $flags | GLOB_NOSORT) ?: []);
        }

        return $files;
    }
}
