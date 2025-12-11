<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\ConfigObject;

class BuilderConfig extends ConfigObject
{
    /**
     * Constructor.
     */
    public function __construct(Builder $builder)
    {
        parent::__construct(['elements' => $builder->types]);
    }
}
