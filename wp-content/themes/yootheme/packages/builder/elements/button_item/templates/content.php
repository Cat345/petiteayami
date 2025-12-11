<?php if ($props['content'] != '') : ?>
<p>
    <?php if ($props['link']) : ?>
    <a href="<?= $props['link'] ?>"><?= $props['content'] ?></a>
    <?php else : ?>
    <?= $props['content'] ?>
    <?php endif ?>
</p>
<?php endif ?>

<?php if ($props['dialog']) : ?>
<div><?= $props['dialog'] ?></div>
<?php endif ?>
