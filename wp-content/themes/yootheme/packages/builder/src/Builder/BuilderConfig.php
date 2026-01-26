<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\ConfigObject;

class BuilderConfig extends ConfigObject
{
    public function __construct(Builder $builder)
    {
        parent::__construct(['elements' => $builder->getTypes()]);
    }
}
