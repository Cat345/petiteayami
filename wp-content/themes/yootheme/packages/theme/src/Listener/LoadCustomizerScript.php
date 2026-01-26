<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Metadata;

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
            'href' => '~assets/admin/css/admin.css',
        ]);

        $this->metadata->set('script:customizer', [
            'src' => '~assets/admin/js/customizer.js',
            'type' => 'module',
        ]);
    }
}
