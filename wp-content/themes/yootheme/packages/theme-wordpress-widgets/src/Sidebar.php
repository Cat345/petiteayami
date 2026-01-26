<?php

namespace YOOtheme\Theme\Widgets;

class Sidebar
{
    public static function register(string $id, string $name): void
    {
        register_sidebar([
            'id' => $id,
            'name' => $name,
            'before_widget' => '<content>',
            'after_widget' => '</content>',
            'before_title' => '<title>',
            'after_title' => '</title>',
        ]);
    }
}
