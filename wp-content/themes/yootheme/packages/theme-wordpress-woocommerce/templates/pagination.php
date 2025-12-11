<nav class="uk-margin-large" aria-label="Pagination">
    <ul class="uk-pagination uk-margin-remove-bottom uk-flex-center">
        <?php foreach ($links as $link): ?>
        <li<?= strpos($link, 'current') ? ' class="uk-active" aria-current="page"' : '' ?>><?= $link ?></li>
        <?php endforeach ?>
    </ul>
</nav>
