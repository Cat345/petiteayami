<?php

namespace YOOtheme\Image;

use YOOtheme\Image;
use YOOtheme\Path;

class ImageSvg extends Image
{
    public ?string $type = 'svg';

    /**
     * @var ?array{?int, ?int}
     */
    protected ?array $dimensions = null;

    /**
     * @inheritdoc
     */
    public function getWidth(int $precision = 0): ?float
    {
        [$width] = $this->getDimensions();
        return isset($width) ? round($width, $precision) : null;
    }

    /**
     * @inheritdoc
     */
    public function getHeight(int $precision = 0): ?float
    {
        [, $height] = $this->getDimensions();
        return isset($height) ? round($height, $precision) : null;
    }

    /**
     * @param array{width: int|string|null, height: int|string|null} $attrs
     *
     * @return array{width: ?float, height: ?float}
     */
    public function ratio(array $attrs): array
    {
        [$width, $height] = static::calculateRatio($this->getDimensions(), $attrs);

        return [
            'width' => isset($width) ? round($width) : null,
            'height' => isset($height) ? round($height) : null,
        ];
    }

    /**
     * @return array{?float, ?float}
     */
    protected function getDimensions(): array
    {
        return $this->dimensions ??= static::readDimensions(static::getSvgTag($this->file));
    }

    protected static function getSvgTag(string $file): string
    {
        $result = '';

        $file = Path::resolve('~', $file);

        if (is_readable($file) && ($resource = @fopen($file, 'r'))) {
            while (($line = fgets($resource, 4096)) !== false) {
                if ($result) {
                    $result .= $line;
                } elseif (str_contains($line, '<svg')) {
                    $result = $line;
                }

                if ($result && str_contains($line, '>')) {
                    $result = substr($result, 0, strpos($line, '>') - (strlen($line) - 1));
                    break;
                }
            }
            fclose($resource);
        }

        return $result;
    }

    /**
     * @see https://www.w3.org/TR/SVG11/struct.html#UseElementWidthAttribute
     * @see https://www.w3.org/TR/SVG11/types.html#DataTypeLength
     * @see https://www.w3.org/TR/SVG11/coords.html#ViewBoxAttribute
     *
     * @return array{?float, ?float}
     */
    protected static function readDimensions(string $tag): array
    {
        $attrs = static::parseAttributes($tag) + ['width' => null, 'height' => null];

        foreach (['width', 'height'] as $prop) {
            $value = $attrs[$prop] ?? '';

            if (str_ends_with($value, 'px')) {
                $value = substr($value, 0, -2);
            }

            $attrs[$prop] = is_numeric($value) ? (float) $value : null;
        }

        if (!empty($attrs['viewbox']) && (empty($attrs['width']) || empty($attrs['height']))) {
            $dim = static::parseViewBox($attrs['viewbox']);

            if ($dim) {
                [$attrs['width'], $attrs['height']] = static::calculateRatio($dim, $attrs);
            }
        }

        return [$attrs['width'], $attrs['height']];
    }

    /**
     * @return array<string, string>
     */
    protected static function parseAttributes(string $tag): array
    {
        $regex = '/(?<prop>width|height|viewBox)\s*=\s*(["\'])\s*(?<value>.+?)?\s*\2/i';
        preg_match_all($regex, $tag, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            $attrs[strtolower($match['prop'])] = $match['value'];
        }
        return $attrs;
    }

    /**
     * @param array{?float, ?float} $dim
     * @param array{width: float|int|string|null, height: float|int|string|null} $attrs
     *
     * @return array{?int, ?int}
     */
    protected static function calculateRatio(array $dim, array $attrs): array
    {
        $attrs = array_map('floatval', $attrs);
        $props = ['width', 'height'];
        $width = $height = null;

        foreach ($props as $i => $prop) {
            $aprop = $props[1 - $i];
            if (
                empty($attrs[$prop]) &&
                !empty($attrs[$aprop]) &&
                !empty($dim[$i]) &&
                !empty($dim[1 - $i])
            ) {
                $$prop = $dim[$i] * ($attrs[$aprop] / $dim[1 - $i]);
            } else {
                $$prop = empty($attrs[$prop]) ? $dim[$i] : $attrs[$prop];
            }
        }

        return [$width, $height];
    }

    /**
     * @return ?array{float, float}
     */
    protected static function parseViewBox(string $viewBox): ?array
    {
        $numbers = array_filter(preg_split('/[ ,]+/', $viewBox), 'is_numeric');

        return count($numbers) === 4 ? array_map('floatval', array_slice($numbers, 2)) : null;
    }
}
