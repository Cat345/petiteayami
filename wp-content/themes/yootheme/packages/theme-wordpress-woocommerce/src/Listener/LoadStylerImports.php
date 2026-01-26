<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Theme\Styler\Styler;

class LoadStylerImports
{
    public Styler $styler;

    public function __construct(Styler $styler)
    {
        $this->styler = $styler;
    }

    /**
     * @param array<string, string> $imports
     *
     * @return array<string, string>
     */
    public function handle(array $imports): array
    {
        // ignore files from being compiled into theme.css
        if (!class_exists('WooCommerce', false)) {
            $woocommerce = __DIR__ . '/../../assets/less/woocommerce.less';

            foreach ($this->styler->resolveImports($woocommerce) as $file => $data) {
                unset($imports[$file]);
            }
        }

        return $imports;
    }
}
