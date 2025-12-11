<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Storage as AbstractStorage;

class Storage extends AbstractStorage
{
    /**
     * Constructor.
     *
     * @param string $name
     */
    public function __construct($name = 'yootheme')
    {
        $this->addJson(get_option($name));

        add_action('shutdown', function () use ($name) {
            if ($this->isModified()) {
                $data = json_encode($this, JSON_UNESCAPED_SLASHES);

                if ($data !== false) {
                    update_option($name, $data, false);
                }
            }
        });
    }
}
