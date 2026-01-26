<?php

namespace YOOtheme\Image\Driver;

trait DriverHelper
{
    /**
     * @var array<string, int>
     */
    public static array $colors = [
        'aqua' => 0x00ffff,
        'black' => 0x000000,
        'blue' => 0x0000ff,
        'fuchsia' => 0xff00ff,
        'gray' => 0x808080,
        'green' => 0x008000,
        'lime' => 0x00ff00,
        'maroon' => 0x800000,
        'navy' => 0x000080,
        'olive' => 0x808000,
        'orange' => 0xffa500,
        'purple' => 0x800080,
        'red' => 0xff0000,
        'silver' => 0xc0c0c0,
        'teal' => 0x008080,
        'white' => 0xffffff,
        'yellow' => 0xffff00,
        'transparent' => 0x7fffffff,
    ];

    /**
     * Parses a color to decimal value.
     */
    public static function parseColor(string $color): int
    {
        $color = strtolower(trim($color));

        if (isset(static::$colors[$color])) {
            return static::$colors[$color];
        }

        if (preg_match('/^(#|0x|)([0-9a-f]{3,6})/i', $color, $matches)) {
            $col = $matches[2];

            if (strlen($col) == 6) {
                return hexdec($col);
            }

            if (strlen($col) == 3) {
                $r = '';

                for ($i = 0; $i < 3; ++$i) {
                    $r .= $col[$i] . $col[$i];
                }

                return hexdec($r);
            }
        }

        if (preg_match('/^rgb\(([0-9]+),([0-9]+),([0-9]+)\)/i', $color, $matches)) {
            [$r, $g, $b] = array_map('intval', array_slice($matches, 1));

            if ($r >= 0 && $r <= 0xff && $g >= 0 && $g <= 0xff && $b >= 0 && $b <= 0xff) {
                return ($r << 16) | ($g << 8) | $b;
            }
        }

        throw new \InvalidArgumentException("Invalid color: {$color}");
    }

    /**
     * Parses a color to rgba string.
     */
    public static function parseColorRgba(string $color): string
    {
        $value = static::parseColor($color);

        $a = ($value >> 24) & 0xff;
        $r = ($value >> 16) & 0xff;
        $g = ($value >> 8) & 0xff;
        $b = $value & 0xff;
        $a = round((127 - $a) / 127, 2);

        return sprintf('rgba(%d, %d, %d, %.2F)', $r, $g, $b, $a);
    }

    /**
     * Embeds an image IPTC data.
     *
     * @param array<string, array<string|bool>>  $iptc
     */
    public static function embedIptc(array $iptc, string $file): string
    {
        $iptcdata = '';

        foreach ($iptc as $tag => $value) {
            $tag = explode('#', $tag, 2);
            $value = join(' ', (array) $value);
            $length = strlen($value);
            $iptcdata .= chr(0x1c) . chr((int) $tag[0]) . chr((int) $tag[1]);

            if ($length < 0x8000) {
                $iptcdata .= chr($length >> 8) . chr($length & 0xff);
            } else {
                $iptcdata .=
                    chr(0x80) .
                    chr(0x04) .
                    chr(($length >> 24) & 0xff) .
                    chr(($length >> 16) & 0xff) .
                    chr(($length >> 8) & 0xff) .
                    chr($length & 0xff);
            }

            $iptcdata .= $value;
        }

        return iptcembed($iptcdata, $file);
    }
}
