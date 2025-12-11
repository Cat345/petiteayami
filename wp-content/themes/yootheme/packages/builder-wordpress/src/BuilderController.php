<?php

namespace YOOtheme\Builder\Wordpress;

use YOOtheme\Config;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Http\Uri;
use YOOtheme\Url;

class BuilderController
{
    public static function loadImage(Request $request, Response $response, Config $config)
    {
        $src = $request->getParam('src');
        $md5 = $request->getParam('md5');

        $uri = new Uri($src);
        $file = basename($uri->getPath());

        if ($uri->getHost() === 'images.unsplash.com') {
            $file .= ".{$uri->getQueryParam('fm', 'jpg')}";
        }

        $upload = $config('app.uploadDir');

        // file exists already?
        while ($iterate = @md5_file("{$upload}/{$file}")) {
            if ($iterate === $md5 || is_null($md5)) {
                return $response->withJson(Url::relative(Url::to("{$upload}/{$file}")));
            }

            $file = preg_replace_callback(
                '/(?:-(\d{2}))?(\.[^.]+)?$/',
                fn($match) => sprintf('-%02d%s', intval($match[1]) + 1, $match[2] ?? ''),
                $file,
                1,
            );
        }

        // set upload dir to base
        add_filter('upload_dir', function ($upload) {
            // Subdirectory if uploads use year/month folders option is on.
            if ($upload['subdir']) {
                $upload['url'] = $upload['baseurl'];
                $upload['path'] = $upload['basedir'];
            }

            return $upload;
        });

        // download file
        $tmp = download_url($src);

        if (is_wp_error($tmp)) {
            $request->abort(500, "{$file}: {$tmp->get_error_message()}");
        }

        // import file to uploads dir
        $id = media_handle_sideload([
            'name' => $file,
            'tmp_name' => $tmp,
        ]);

        if (is_wp_error($id)) {
            $request->abort(500, "{$file}: {$id->get_error_message()}");
        }

        $url = set_url_scheme(wp_get_attachment_url($id), 'relative');

        return $response->withJson($url ? Url::relative($url) : $url);
    }
}
