<?php

namespace YOOtheme\View;

use YOOtheme\Str;
use YOOtheme\View;

class StrHelper extends Str
{
    /**
     * Constructor.
     */
    public function __construct(View $view)
    {
        $functions = [
            // native
            'trim' => 'trim',
            'json' => 'json_encode',
            'nl2br' => 'nl2br',
            'striptags' => 'strip_tags',

            // date
            'date' => [$this, 'date'],

            // string util
            'limit' => [$this, 'limit'],
            'upper' => [$this, 'upper'],
            'lower' => [$this, 'lower'],
            'title' => [$this, 'titleCase'],
        ];

        foreach ($functions as $name => $function) {
            $view->addFunction($name, $function);
        }
    }

    /**
     * Formats a date.
     *
     * @param int|string|\DateTime|null $date
     *
     * @return string|false
     */
    public function date($date, string $format = 'F j, Y H:i')
    {
        if (is_string($date)) {
            $date = strtotime($date);
        } elseif ($date instanceof \DateTime) {
            $date = $date->getTimestamp();
        }

        return date($format, $date);
    }
}
