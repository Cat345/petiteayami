<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Metadata;
use YOOtheme\Path;

class LoadCustomizerScript
{
    public Metadata $metadata;

    public function __construct(Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        $this->metadata->set('style:customizer', [
            'href' => Path::get('../../assets/css/admin.css', __DIR__),
        ]);

        $this->metadata->set('script:customizer', [
            'src' => Path::get('../../app/customizer.min.js', __DIR__),
            'defer' => true,
        ]);
    }
}
