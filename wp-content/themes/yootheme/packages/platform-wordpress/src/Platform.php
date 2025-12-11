<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Application;
use YOOtheme\Http\Exception;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Metadata;
use YOOtheme\Url;

class Platform
{
    /**
     * Handle application routes.
     *
     * @param Application $app
     */
    public static function handleRoute(Application $app)
    {
        // remove plugin generated unexpected output
        if (ob_get_length() > 0) {
            ob_clean();
        }

        $app->run();

        exit();
    }

    /**
     * Handle application errors.
     *
     * @param Request    $request
     * @param Response   $response
     * @param \Exception $exception
     *
     * @throws \Exception
     *
     * @return Response
     */
    public static function handleError(Request $request, $response, $exception)
    {
        if ($exception instanceof Exception) {
            if (str_starts_with($request->getHeaderLine('Content-Type'), 'application/json')) {
                return $response->withJson($exception->getMessage());
            }

            return $response
                ->write($exception->getMessage())
                ->withHeader('Content-Type', 'text/plain');
        }

        throw $exception;
    }

    /**
     * Prints style tags.
     *
     * @param Metadata $metadata
     */
    public static function printStyles(Metadata $metadata)
    {
        foreach ($metadata->all('style:*') as $name => $style) {
            if ($style->defer) {
                continue;
            }

            $metadata->del($name);

            if ($style->href) {
                $style = $style->withAttribute(
                    'href',
                    Url::to($style->href, ['ver' => $style->version], is_ssl()),
                );
            }

            echo "{$style->withAttribute('version', '')}\n";
        }
    }

    /**
     * Prints script tags.
     *
     * @param Metadata $metadata
     */
    public static function printScripts(Metadata $metadata)
    {
        foreach ($metadata->all('script:*') as $name => $script) {
            $metadata->del($name);

            if ($script->src) {
                $script = $script->withAttribute(
                    'src',
                    Url::to($script->src, ['ver' => $script->version], is_ssl()),
                );
            }

            echo "{$script->withAttribute('version', '')}\n";
        }
    }

    /**
     * Callback to register scripts in footer.
     *
     * @param Metadata $metadata
     */
    public static function printFooterScripts(Metadata $metadata)
    {
        foreach ($metadata->all('style:*') as $style) {
            if ($style->href) {
                echo "<style>@import '" .
                    Url::to(
                        $style->href,
                        $style->version ? ['ver' => $style->version] : [],
                        is_ssl(),
                    ) .
                    "';</style>\n";
            } elseif ($style->getValue()) {
                echo "{$style->withAttribute('version', '')}\n";
            }
        }

        foreach ($metadata->all('script:*') as $script) {
            if ($script->src) {
                $script = $script->withAttribute(
                    'src',
                    Url::to($script->src, ['ver' => $script->version], is_ssl()),
                );
            }

            echo "{$script->withAttribute('version', '')}\n";
        }
    }
}
