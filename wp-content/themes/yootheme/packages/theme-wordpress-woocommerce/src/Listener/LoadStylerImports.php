<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Path;
use YOOtheme\Theme\Styler\Styler;

class LoadStylerImports
{
    public Styler $styler;

    public function __construct(Styler $styler)
    {
        $this->styler = $styler;
    }

    public function handle(array $imports): array
    {
        // ignore files from being compiled into theme.css
        if (!class_exists('WooCommerce', false)) {
            $woocommerce = Path::get('../../assets/less/woocommerce.less', __DIR__);

            foreach ($this->styler->resolveImports($woocommerce) as $file => $data) {
                unset($imports[$file]);
            }
        }

        return $imports;
    }
}
