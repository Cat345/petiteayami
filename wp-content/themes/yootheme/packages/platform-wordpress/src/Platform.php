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
     * @return never
     */
    public static function handleRoute(Application $app)
    {
        // remove plugin generated unexpected output
        if (ob_get_length() > 0) {
            ob_clean();
        }

        // use route from query parameter
        $app->run(true, $_GET[Router::ROUTE_NAME] ?? '');

        exit();
    }

    /**
     * Handle application errors.
     *
     * @param Response $response (event parameter, not injected)
     * @param \Exception $exception (event parameter, not injected)
     *
     * @throws \Exception
     */
    public static function handleError(Request $request, $response, $exception): Response
    {
        if (!($exception instanceof Exception)) {
            throw $exception;
        }

        if (str_starts_with($request->getHeaderLine('Content-Type'), 'application/json')) {
            return $response->withJson($exception->getMessage());
        }

        return $response->write($exception->getMessage())->withHeader('Content-Type', 'text/plain');
    }

    /**
     * Prints style tags.
     */
    public static function printStyles(Metadata $metadata): void
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
     */
    public static function printScripts(Metadata $metadata): void
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
     */
    public static function printFooterScripts(Metadata $metadata): void
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
