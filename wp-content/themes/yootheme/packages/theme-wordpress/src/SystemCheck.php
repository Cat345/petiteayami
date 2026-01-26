<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Theme\SystemCheck as BaseSystemCheck;
use function YOOtheme\trans;

class SystemCheck extends BaseSystemCheck
{
    /**
     * Constructor.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function getRecommendations(): array
    {
        $res = [];

        if (Path::basename('~theme') !== 'yootheme') {
            $res[] = trans(
                'The YOOtheme Pro theme folder was renamed breaking essential functionality. Rename the theme folder back to <code>yootheme</code>.',
            );
        }

        return array_merge($res, parent::getRecommendations());
    }

    protected function hasApiKey(): bool
    {
        return (bool) $this->config->get('~theme.yootheme_apikey');
    }
}
