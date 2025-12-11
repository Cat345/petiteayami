<?php

namespace YOOtheme\Theme\Styler\Listener;

use YOOtheme\Config;
use YOOtheme\File;
use YOOtheme\Path;
use YOOtheme\Theme\Styler\Styler;
use YOOtheme\Url;

class LoadStylerImports
{
    public Config $config;
    public Styler $styler;

    public function __construct(Config $config, Styler $styler)
    {
        $this->config = $config;
        $this->styler = $styler;
    }

    public function handle($imports, $themeId): array
    {
        // add general imports
        foreach ($this->config->get('theme.styles.imports', []) as $path) {
            foreach (File::glob($path) as $file) {
                $imports += $this->styler->resolveImports($file);
            }
        }

        // add theme imports
        if (!($theme = $this->styler->getTheme($themeId))) {
            return $imports;
        }

        // add svg images
        foreach (
            File::glob("~theme/vendor/assets/uikit-themes/master-{$themeId}/images/*.svg")
            as $file
        ) {
            $imports += $this->styler->resolveImports($file);
        }

        $file = $theme['file'];

        $imports += $this->styler->resolveImports($file);

        // add theme style imports
        if (isset($theme['styles'])) {
            foreach (array_keys($theme['styles']) as $style) {
                $imports += $this->styler->resolveImports(
                    $file,
                    [
                        '@internal-style' => $style,
                    ] + $this->config->get('theme.styles.vars', []),
                );
            }
        }

        // add custom components
        foreach ($this->config->get('theme.styles.components', []) as $path) {
            foreach (File::glob($path) as $component) {
                $imports += $this->styler->resolveImports($component);
                $imports[Url::to($file)] .= sprintf(
                    "\n@import \"%s\";",
                    Path::relative(dirname($file), $component),
                );
            }
        }

        return $imports;
    }
}
