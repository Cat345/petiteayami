<?php

$el = $this->el('div');

// Layout
$grid = ($props['show_name'] || $props['button_mode'] == 'button') ? $this->el('div', [

    'class' => [
        'uk-grid-{gap}',
        'uk-child-width-expand@s {@layout: grid} [uk-flex-middle {@button_style: text}]',
        'uk-child-width-1-1 {@layout: stacked(-name)?}',
    ],

    'uk-grid' => true,
]) : null;

// Input
$input = $this->el('input', [

    'class' => [
        'el-input',
        'uk-input',
        'uk-form-{form_size}',
        'uk-form-{form_style}',
    ],

    'type' => 'text',

]);

// Button
$button = $this->el('button', [

    'class' => $props['button_mode'] == 'button' ? [
        'el-button',
        'uk-button uk-button-{button_style}',
        'uk-button-{form_size} {@!button_style: text}',
        'uk-width-1-1 {@button_fullwidth} {@!layout: grid}',
        'uk-margin[-small{@!button_margin: default}]-{0} {@show_name} {@button_margin}' => $props['layout'] == 'grid' ? 'left' : 'top',
    ] : 'el-button uk-form-icon uk-form-icon-flip',

    'uk-icon' => [
        'icon: {button_icon}; {@!button_mode: button}',
    ],

]);

?>

<?= $el($props, $attrs) ?>

    <form class="uk-form uk-panel js-form-newsletter" method="post"<?= $this->attrs($form) ?>>

        <?php if ($grid) : ?>
        <?= $grid($props) ?>
        <?php endif ?>

            <?php if ($props['show_name']) : ?>

                <?php if ($props['layout'] == 'stacked-name') : ?>
                <div>
                    <div class="uk-child-width-1-2@s <?= $props['gap'] ? "uk-grid-{$props['gap']}" : '' ?>" uk-grid>
                <?php endif ?>

                <div><?= $input($props, ['name' => 'first_name', 'placeholder' => ['{label_first_name}'], 'aria-label' => ['{label_first_name}']]) ?></div>
                <div><?= $input($props, ['name' => 'last_name', 'placeholder' => ['{label_last_name}'], 'aria-label' => ['{label_last_name}']]) ?></div>

                <?php if ($props['layout'] == 'stacked-name') : ?>
                    </div>
                </div>
                <?php endif ?>

            <?php endif ?>

            <?php if ($props['button_mode'] == 'button') : ?>

                <div><?= $input($props, ['type' => 'email', 'name' => 'email', 'placeholder' => ['{label_email}'], 'aria-label' => ['{label_email}'], 'required' => true]) ?></div>
                <?= $this
                    ->el('div', ['class' => ['uk-width-auto@s {@layout: grid} [uk-flex {@button_style: text}]']], $button($props, ['type' => 'submit'], $props['label_button'] ?: ''))
                    ->render($props) ?>

            <?php endif ?>

            <?php if ($props['button_mode'] == 'icon') : ?>
            <div class="uk-position-relative">
                <?= $button($props, ['type' => 'submit', 'title' => ['{label_button}']], '') ?>
                <?= $input($props, ['type' => 'email', 'name' => 'email', 'placeholder' => ['{label_email}'], 'aria-label' => ['{label_email}'], 'required' => true]) ?>
            </div>
            <?php endif ?>

        <?php if ($grid) : ?>
        <?= $grid->end() ?>
        <?php endif ?>

        <input type="hidden" name="settings" value="<?= $settings ?>">
        <div class="message uk-margin uk-hidden"></div>

    </form>

<?= $el->end() ?>
