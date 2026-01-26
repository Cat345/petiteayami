<?php

namespace YOOtheme\Builder\Source\Type;

use YOOtheme\Http\Request;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class RequestType
{
    /**
     * @return ObjectConfig
     */
    public static function config()
    {
        return [
            'fields' => [
                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('URL'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveUrl',
                    ],
                ],
                'method' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Method'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveMethod',
                    ],
                ],
                'scheme' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Scheme'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveScheme',
                    ],
                ],
                'host' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Host'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveHost',
                    ],
                ],
                'port' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Port'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePort',
                    ],
                ],
                'path' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Path'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePath',
                    ],
                ],
                'query' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Query'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveQuery',
                    ],
                ],
            ],

            'metadata' => [
                'type' => true,
            ],
        ];
    }

    /**
     * @return string
     */
    public static function resolveUrl(Request $request)
    {
        return (string) $request->getUri();
    }

    /**
     * @return string
     */
    public static function resolveMethod(Request $request)
    {
        return $request->getMethod();
    }

    /**
     * @return string
     */
    public static function resolveScheme(Request $request)
    {
        return $request->getUri()->getScheme();
    }

    /**
     * @return string
     */
    public static function resolveHost(Request $request)
    {
        return $request->getUri()->getHost();
    }

    /**
     * @return ?int
     */
    public static function resolvePort(Request $request)
    {
        return $request->getUri()->getPort();
    }

    /**
     * @return string
     */
    public static function resolvePath(Request $request)
    {
        return $request->getUri()->getPath();
    }

    /**
     * @return string
     */
    public static function resolveQuery(Request $request)
    {
        return $request->getUri()->getQuery();
    }
}
