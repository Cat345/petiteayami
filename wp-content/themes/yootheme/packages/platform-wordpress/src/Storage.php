<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Storage as AbstractStorage;

class Storage extends AbstractStorage
{
    /**
     * Constructor.
     */
    public function __construct(string $name = 'yootheme')
    {
        $this->addJson(get_option($name));

        add_action('shutdown', function () use ($name) {
            if ($this->isModified()) {
                // JSON is not saved with JSON_UNESCAPED_UNICODE
                // to ensure compatibility with database collations
                // that don't support the full Unicode range.
                $data = json_encode($this, JSON_UNESCAPED_SLASHES);

                if ($data !== false) {
                    update_option($name, $data, false);
                }
            }
        });
    }
}
