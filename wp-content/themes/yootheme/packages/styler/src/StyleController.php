<?php

namespace YOOtheme\Theme\Styler;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Path;
use YOOtheme\Url;

class StyleController
{
    public static function index(Request $request, Response $response, Styler $styler)
    {
        return $response->withJson(
            array_map(function ($theme) {
                unset($theme['file']);
                return $theme;
            }, $styler->getThemes()),
        );
    }

    public static function get(Request $request, Response $response, Config $config, Styler $styler)
    {
        $themeId = explode('::', $request->getQueryParam('id', ''))[0];
        $theme = $styler->getTheme($themeId);

        if (!$theme) {
            $request->abort(404, "Theme {$themeId} not found");
        }

        return $response->withJson([
            'id' => $themeId,
            'filename' => Url::to($theme['file']),
            'imports' => Event::emit('styler.imports|filter', [], $themeId),
            'vars' => $config('theme.styles.vars'),
            'desturl' => Url::to('~theme/css'),
        ]);
    }

    public static function save(
        Request $request,
        Response $response,
        Config $config,
        StyleFontLoader $font
    ) {
        $upload = $request->getUploadedFile('files');

        // validate uploads
        $request
            ->abortIf(!$upload || $upload->getError(), 400, 'Invalid file upload.')
            ->abortIf(
                !($contents = (string) $upload->getStream()),
                400,
                'Unable to read contents file.',
            )
            ->abortIf(!($contents = @base64_decode($contents)), 400, 'Base64 Decode failed.')
            ->abortIf(
                !($files = @json_decode($contents, true)),
                400,
                'Unable to decode JSON from temporary file.',
            );

        foreach ($files as $file => $data) {
            $dir = Path::get('~theme/css');
            $rtl = strpos($file, '.rtl') ? '.rtl' : '';

            try {
                // save fonts for theme style
                if ($matches = $font->parse($data)) {
                    [$import, $url] = $matches;

                    if ($fonts = $font->css($url, $dir)) {
                        $data = str_replace($import, $fonts, $data);
                    }
                }
            } catch (\RuntimeException $e) {
            }

            $head =
                "/* YOOtheme Pro v{$config('theme.version')} compiled on " .
                date(DATE_W3C) .
                " */\n";

            // save css for theme style
            if (
                !file_put_contents(
                    $file = "{$dir}/theme.{$config('theme.id')}{$rtl}.css",
                    $head . $data,
                )
            ) {
                $request->abort(500, sprintf('Unable to write file (%s).', $file));
            }

            // save css for theme as default/fallback
            if (
                $config('theme.default') &&
                !file_put_contents($file = "{$dir}/theme{$rtl}.css", $head . $data)
            ) {
                $request->abort(500, sprintf('Unable to write file (%s).', $file));
            }
        }

        return $response->withJson(['message' => 'success']);
    }
}
