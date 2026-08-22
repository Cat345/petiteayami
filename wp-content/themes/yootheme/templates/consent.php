<?php

namespace YOOtheme;

use YOOtheme\Theme\Consent\ConsentHelper;

$props = $config('~theme.consent');

[$props['banner_type'], $props['banner_position']] = explode('-', $props['banner_layout'], 2);

foreach (['banner_button_accept_title', 'banner_button_reject_title', 'banner_button_settings_title', 'banner_content', 'button_width', 'modal_accordion_icon_width', 'modal_button_accept_title', 'modal_button_reject_title', 'modal_button_save_title', 'modal_button_width', 'modal_categories_checkbox_large', 'modal_categories_description', 'modal_categories_right', 'modal_content', 'modal_sections', 'modal_title', 'privacy_policy_link'] as $key) {
    $props[$key] ??= '';
}

/** @var ConsentHelper $consent */
$consent = app(ConsentHelper::class);

$banner = $this->el('div', [

    'class' => [
        'tm-consent',

        'uk-position-{banner_position} uk-position-fixed uk-position-z-index-high uk-position-{banner_margin} {@banner_type: card|notification}',

        'uk-section uk-section-xsmall uk-section-{section_style} {@banner_type: section}',
        'uk-position-bottom uk-position-fixed uk-position-z-index-high {@banner_position: bottom} {@banner_type: section}',
        'uk-position-relative uk-position-z-index {@banner_position: top} {@banner_type: section}',
    ],

    'style' => [
        'width: {banner_width}px; {@!banner_type: section}',
        'width: 550px; {@!banner_width} {@!banner_type: section}',
    ],

]);

$banner_container = $this->el('div', [

    'class' => [
        'uk-card uk-card-{card_style} uk-card-body [uk-card-small {@card_padding_small}] {@banner_type: card}',

        'uk-notification-message uk-notification-message-{notification_style} {@banner_type: notification}',
        'uk-notification-message uk-panel {@!notification_style} {@banner_type: notification}',

        'uk-container {@banner_type: section}',
        'uk-container-{section_width: xsmall|small|large|xlarge|expand}',
        'uk-text-center {@banner_content_center}'
    ],

]);

// Section Grid
$section_grid = '';
$section_cell_buttons = '';

if ($props['banner_type'] == 'section' && !empty($props['section_grid'])) {

    $section_grid = $this->el('div', [

        'class' => [
            'uk-child-width-{section_grid}[@{section_grid_breakpoint}] uk-grid-small uk-flex-middle',
            'uk-flex-center {@banner_content_center}',
        ],

        'uk-grid' => true,
    ]);

    $section_cell_buttons = $this->el('div', [

        'class' => [
            'uk-width-auto {@!button_width: expand}',
            'uk-width-expand {@button_width: expand|equal}',
        ],

    ]);

}

$banner_content = $this->el('p', [

    'class' => [
        'uk-text-{banner_content_style}',
    ],

]);

// Button Grid
$button_grid = $this->el('div', [

    'class' => [
        'uk-child-width-1-1 {@!button_width: expand}',
        'uk-child-width-auto@s {@!button_width}',
        'uk-child-width-expand@s {@button_width: equal}',
        'uk-grid-small',
        'uk-flex-center {@banner_content_center}' => !($props['banner_type'] == 'section' && !empty($props['section_grid'])),
        // Button Text
        'uk-flex-middle' => in_array('text', [$props['button_accept_style'], $props['button_reject_style'], $props['button_settings_style']]),
        'uk-text-center' => in_array('text', [$props['button_accept_style'], $props['button_reject_style'], $props['button_settings_style']]) && $props['button_width'],
    ],

    'uk-grid' => true,
]);

$button_cell = $this->el('div', [

    'class' => [
        'uk-flex-auto {@button_width: expand}',
    ],

]);

// Buttons
foreach (['accept', 'reject', 'settings'] as $button) {
    ${"button_{$button}"} = $this->el('button', [

        'type' => 'button',
        'data-consent-button' => $button,

        'class' => [
            "uk-button uk-button-{button_{$button}_style} [uk-button-{button_size}]",
            "uk-width-1-1 {@!button_{$button}_style: text}",
        ],

        'data-uk-toggle' => $button !== 'settings' ? [
            'target: !.tm-consent;',
            'animation: {0};' => ($props['banner_type'] === 'section' ? 'true' : 'uk-animation-fade'),
        ] : false,

    ]);
}

$modal = $this->el('form', [

    'class' => [
        'uk-modal-dialog uk-margin-auto-vertical',
        'uk-modal-body {@!modal_sections}'
    ],

    'style' => [
        'width: {modal_width}px;',
        'width: 720px; {@!modal_width}',
    ],

    'aria-label' => [
        __('Privacy Settings', 'yootheme')
    ],

]);

$modal_title = $this->el($props['modal_title_element'], [

    'class' => [
        'uk-{modal_title_style}',
        'uk-modal-title {@!modal_title_style}',
    ],

]);

$modal_content = $this->el('p', [

    'class' => [
        'uk-text-{modal_content_style}',
        'uk-margin-{modal_category_margin}-bottom',
    ],

]);

$modal_category_title = $this->el($props['modal_category_title_element'], [

    'class' => [
        'uk-{modal_category_title_style}',
        'uk-margin-remove',
    ],

]);

$modal_category_content = $this->el('p', [

    'class' => [
        'uk-text-{modal_category_content_style}',
        'uk-margin-small-top uk-margin-remove-bottom',
    ],

]);

// Button Grid
$modal_button_grid = $this->el('div', [

    'class' => [
        'uk-margin-medium-top {@!modal_sections}',
        'uk-child-width-1-1',
        'uk-child-width-auto@s {@!modal_button_width}',
        'uk-child-width-expand@s {@modal_button_width: equal}',
        'uk-grid-small',
        'uk-flex-row-reverse {@modal_button_flip}',
        // Button Text
        'uk-flex-middle' => in_array('text', [$props['modal_button_accept_style'], $props['modal_button_reject_style'], $props['modal_button_save_style']]),
        'uk-text-center' => in_array('text', [$props['modal_button_accept_style'], $props['modal_button_reject_style'], $props['modal_button_save_style']]) && $props['modal_button_width'],
    ],

    'uk-grid' => true,
]);

$modal_button_cell = $this->el('div');

$modal_button_cell_save = $this->el('div', [

    'class' => [

        'uk-margin-auto-left@s {@!modal_button_width} {@!modal_button_flip}',
        'uk-margin-auto-right@s {@!modal_button_width} {@modal_button_flip}',
    ],

]);

// Buttons
foreach (['accept', 'reject', 'save'] as $button) {
    ${"modal_button_{$button}"} = $this->el('button', [

        'type' => $button === 'save' ? 'submit' : 'button',

        'data-consent-button' => $button,

        'class' => [
            "uk-button uk-button-{modal_button_{$button}_style} [uk-button-{modal_button_size}]",
            "uk-width-1-1 {@!modal_button_{$button}_style: text}",
            'uk-modal-close',
        ],

    ]);
}

$modal_accordion_icon = $this->el('div', [

    'class' => [
        'uk-width-auto',
    ],

    'uk-accordion-icon' => $props['modal_accordion_icon_width'] ? [
        'width: {modal_accordion_icon_width}; height: {modal_accordion_icon_width}',
    ] : true,

]);

$modal_toggle = $this->el('a', [

    'href' => true,
    'class' => [
        'tm-toggle',
        'uk-link-{modal_toggles_link_style}',
    ],

    'uk-toggle' => 'target: !.uk-width-expand .tm-toggle',

]);

?>

<template id="consent-banner">
    <?= $banner($props) ?>
        <?= $banner_container($props) ?>

            <?php if ($section_grid) : ?>
            <?= $section_grid($props) ?>
                <div>
            <?php endif ?>

                <?= $banner_content($props) ?>
                    <?= __('We use cookies and similar technologies to improve your experience on our website.', 'yootheme') ?>

                    <?php if ($props['privacy_policy_link']) : ?>
                    <?= sprintf(__('Read our <a href="%s">Privacy Policy</a>.', 'yootheme'), $props['privacy_policy_link']) ?>
                    <?php endif ?>
                <?= $banner_content->end() ?>

            <?php if ($section_grid) : ?>
                </div>
                <?= $section_cell_buttons($props) ?>
            <?php endif ?>

                <?= $button_grid($props) ?>
                    <?= $button_cell($props) ?>

                        <?= $button_accept($props, __('Accept', 'yootheme')) ?>

                    <?= $button_cell->end() ?>
                    <?= $button_cell($props) ?>

                        <?= $button_reject($props, __('Reject', 'yootheme')) ?>

                    <?= $button_cell->end() ?>
                    <?= $button_cell($props) ?>

                        <?= $button_settings($props, __('Manage Settings', 'yootheme')) ?>

                    <?= $button_cell->end() ?>
                <?= $button_grid->end() ?>

            <?php if ($section_grid) : ?>
                <?= $section_cell_buttons->end() ?>
            <?= $section_grid->end() ?>
            <?php endif ?>

        <?= $banner_container->end() ?>
    <?= $banner->end() ?>
</template>

<template id="consent-settings">
    <div class="uk-position-z-index-highest" uk-modal>
        <?= $modal($props) ?>

            <button class="uk-modal-close-default uk-close-large" type="button" uk-close></button>

            <?php if ($props['modal_sections']) : ?>
            <div class="uk-modal-header">
            <?php endif ?>

                <?= $modal_title($props) ?><?= __('Privacy Settings', 'yootheme') ?><?= $modal_title->end() ?>

            <?php if ($props['modal_sections']) : ?>
            </div>
            <div class="uk-modal-body">
            <?php endif ?>

                <?= $modal_content($props) ?>
                    <?= __('This website uses cookies and similar technologies. They are grouped into categories, which you can review and manage below. If you have accepted any non-essential cookies, you can change your preferences at any time in the settings.', 'yootheme') ?>

                    <?php if ($props['privacy_policy_link']) : ?>
                    <?= sprintf(__('Learn more in our <a href="%s">Privacy Policy</a>.', 'yootheme'), $props['privacy_policy_link']) ?>
                    <?php endif ?>
                <?= $modal_content->end() ?>

                <?php if ($props['modal_layout'] == 'accordion') : ?>
                <div uk-accordion="multiple: true">
                <?php endif ?>

                    <?php foreach ($consent->getCategories() as $category) : ?>
                    <div class="uk-grid-column-small uk-grid-row<?= $props['modal_category_grid_row_gap'] ? '-' . $props['modal_category_grid_row_gap'] : '' ?>" uk-grid>
                        <div class="uk-width-auto <?= $props['modal_categories_right'] ? 'uk-flex-last' : '' ?>">

                            <div class="uk-<?= $props['modal_category_title_style'] ?>"><input id="consent-cookies-<?= $category ?>" class="uk-checkbox <?= $props['modal_categories_checkbox_large'] ? 'uk-form-large' : '' ?>" type="checkbox" <?= $category === 'functional' ? 'checked disabled' : "name=\"{$category}\"" ?>></div>

                        </div>
                        <div class="uk-width-expand">

                            <?php if ($props['modal_layout'] == 'accordion') : ?>
                            <a class="uk-accordion-title uk-link-reset" href>
                                <div class="uk-grid-small uk-flex-middle" uk-grid>
                                    <div class="uk-width-expand <?= $props['modal_categories_right'] ? 'uk-flex-last' : '' ?>">
                            <?php endif ?>

                                        <?= $modal_category_title($props) ?>

                                            <?php if ($props['modal_layout'] != 'accordion') : ?>
                                            <label for="consent-cookies-<?= $category ?>">
                                            <?php endif ?>

                                            <?php
                                               if ($category === 'functional') {
                                                    echo __('Functional', 'yootheme');
                                                } elseif ($category === 'preferences') {
                                                    echo __('Preferences', 'yootheme');
                                                } elseif ($category === 'statistics') {
                                                    echo __('Statistics', 'yootheme');
                                                } elseif ($category === 'marketing') {
                                                    echo __('Marketing', 'yootheme');
                                                }
                                            ?>

                                            <?php if ($props['modal_layout'] != 'accordion') : ?>
                                            </label>
                                            <?php endif ?>

                                        <?= $modal_category_title->end() ?>

                            <?php if ($props['modal_layout'] == 'accordion') : ?>
                                    </div>
                                    <?= $modal_accordion_icon($props, '') ?>
                                </div>
                            </a>

                            <div class="uk-accordion-content uk-margin-small-bottom">
                            <?php endif ?>

                                <?php if ($props['modal_categories_description']) : ?>
                                <?= $modal_category_content($props) ?>
                                <?php
                                    if ($category === 'functional') {
                                        echo __('These technologies are required to activate the core functionality of our website.', 'yootheme');
                                    } elseif ($category === 'preferences') {
                                        echo __('These technologies allow our website to remember your preferences and provide you with a more personalized experience.', 'yootheme');
                                    } elseif ($category === 'statistics') {
                                        echo __('These technologies enable us to analyse the use of our website in order to measure and improve performance.', 'yootheme');
                                    } elseif ($category === 'marketing') {
                                        echo __('These technologies are used by our marketing partners to show you personalized advertisements relevant to your interests.', 'yootheme');
                                    }
                                ?>
                                <?= $modal_category_content->end() ?>
                                <?php endif ?>

                                <?php if ($services = $consent->getServices($category)) : ?>

                                    <?php if ($props['modal_layout'] == 'toggles') : ?>
                                    <div class="uk-margin-small-top uk-text-small">
                                        <?= $modal_toggle($props, __('Show Services', 'yootheme')) ?>
                                        <?= $modal_toggle($props, ['hidden' => true], __('Hide Services', 'yootheme')) ?>
                                    </div>
                                    <?php endif ?>

                                    <ul class="uk-list tm-toggle uk-margin-small-top <?= $props['modal_categories_right'] ? 'uk-margin-left' : '' ?>"<?= $props['modal_layout'] == 'toggles' ? ' hidden' : '' ?>>
                                        <?php foreach ($services as $service => ['title' => $serviceTitle]) :?>
                                        <li class="uk-text-emphasis">
                                            <input id="consent-cookies-<?= $service ?>" class="uk-checkbox uk-margin-xsmall-right" type="checkbox" <?= $category === 'functional' ? 'checked disabled' : "name=\"{$category}.{$service}\"" ?>>
                                            <label for="consent-cookies-<?= $service ?>"><?= __($serviceTitle, 'yootheme') ?></label>
                                        </li>
                                        <?php endforeach ?>
                                    </ul>

                                <?php endif ?>

                            <?php if ($props['modal_layout'] == 'accordion') : ?>
                            </div>
                            <?php endif ?>

                        </div>
                    </div>
                    <?php endforeach ?>

                <?php if ($props['modal_layout'] == 'accordion') : ?>
                </div>
                <?php endif ?>

            <?php if ($props['modal_sections']) : ?>
            </div>
            <div class="uk-modal-footer">
            <?php endif ?>

                <?= $modal_button_grid($props) ?>
                    <?= $modal_button_cell($props) ?>

                        <?= $modal_button_accept($props, __('Accept all', 'yootheme')) ?>

                    <?= $modal_button_cell->end() ?>
                    <?= $modal_button_cell($props) ?>

                        <?= $modal_button_reject($props, __('Reject all', 'yootheme')) ?>

                    <?= $modal_button_cell->end() ?>
                    <?= $modal_button_cell_save($props) ?>

                        <?= $modal_button_save($props, __('Save', 'yootheme')) ?>

                    <?= $modal_button_cell_save->end() ?>
                <?= $modal_button_grid->end() ?>

            <?php if ($props['modal_sections']) : ?>
            </div>
            <?php endif ?>

        <?= $modal->end() ?>
    </div>
</template>
