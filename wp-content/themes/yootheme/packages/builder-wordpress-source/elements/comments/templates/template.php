<?php

$el = $this->el('div');

echo $el($props, $attrs);
comments_template('/packages/builder-wordpress-source/elements/comments/templates/comments.php');
echo $el->end();
