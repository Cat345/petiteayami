<?php

return [
    'panels' => [
        'consent' => [
            'title' => 'Consent Manager',
            'width' => 400,
            'fields' => [
                'consent.type' => [
                    'label' => 'Compliance Type',
                    'description' => 'Choose a compliance type to enable the consent manager. To allow visitors to open the consent manager and change their cookie preferences, add a link with the URL <code>#consent-settings</code>.',
                    'type' => 'select',
                    'options' => [
                        'None' => 'none',
                        'Opt-in (GDPR)' => 'optin',
                        'Opt-out (CCPA)' => 'optout',
                    ],
                ],
                'consent.privacy_policy_link' => [
                    'label' => 'Privacy Policy Link',
                    'type' => 'link',
                    'description' => 'Show a link to the privacy policy in the cookie banner and consent manager.',
                    'attrs' => [
                        'placeholder' => 'http://',
                    ],
                ],
                'consent.banner_layout' => [
                    'label' => 'Banner Layout',
                    'title' => 'Select banner layout',
                    'type' => 'select-img',
                    'options' => [
                        'section-top' => [
                            'label' => 'Section Top',
                            'src' => '$ASSETS/images/consent/section-top.svg',
                        ],
                        'card-bottom-left' => [
                            'label' => 'Card Left',
                            'src' => '$ASSETS/images/consent/card-bottom-left.svg',
                        ],
                        'card-bottom-center' => [
                            'label' => 'Card Center',
                            'src' => '$ASSETS/images/consent/card-bottom-center.svg',
                        ],
                        'card-bottom-right' => [
                            'label' => 'Card Right',
                            'src' => '$ASSETS/images/consent/card-bottom-right.svg',
                        ],
                        'section-bottom' => [
                            'label' => 'Section Bottom',
                            'src' => '$ASSETS/images/consent/section-bottom.svg',
                        ],
                        'notification-bottom-left' => [
                            'label' => 'Notification Left',
                            'src' => '$ASSETS/images/consent/notification-bottom-left.svg',
                        ],
                        'notification-bottom-center' => [
                            'label' => 'Notification Center',
                            'src' => '$ASSETS/images/consent/notification-bottom-center.svg',
                        ],
                        'notification-bottom-right' => [
                            'label' => 'Notification Right',
                            'src' => '$ASSETS/images/consent/notification-bottom-right.svg',
                        ],
                    ],
                ],
                '_banner' => [
                    'description' => 'Select the layout for the cookie banner.',
                    'text' => 'Edit Banner',
                    'type' => 'button-panel',
                    'panel' => 'consent-banner',
                ],
                'consent.modal_layout' => [
                    'label' => 'Modal Layout',
                    'title' => 'Select modal layout',
                    'type' => 'select-img',
                    'options' => [
                        'list' => [
                            'label' => 'List',
                            'src' => '$ASSETS/images/consent/category-list.svg',
                        ],
                        'toggles' => [
                            'label' => 'Toggles',
                            'src' => '$ASSETS/images/consent/category-toggles.svg',
                        ],
                        'accordion' => [
                            'label' => 'Accordion',
                            'src' => '$ASSETS/images/consent/category-accordion.svg',
                        ],
                    ],
                ],
                '_modal' => [
                    'description' => 'Define the layout for the consent manager modal.',
                    'text' => 'Edit Modal',
                    'type' => 'button-panel',
                    'panel' => 'consent-modal',
                ],
            ],
        ],
        'consent-banner' => [
            'title' => 'Banner',
            'width' => 500,
            'fields' => [
                'consent.banner_width' => [
                    'label' => 'Width',
                    'attrs' => [
                        'placeholder' => '550',
                    ],
                    'show' => '$match(consent.banner_layout, \'^card|notification\')',
                ],
                'consent.banner_content_center' => [
                    'description' => 'Set the banner width in pixels.',
                    'type' => 'checkbox',
                    'text' => 'Center content',
                ],
                'consent.banner_margin' => [
                    'label' => 'Margin',
                    'description' => 'Apply a margin between the banner and the browser window.',
                    'type' => 'select',
                    'options' => [
                        'Small' => 'small',
                        'Medium' => 'medium',
                        'Large' => 'large',
                    ],
                    'show' => '$match(consent.banner_layout, \'^card|notification\')',
                ],
                'consent.card_style' => [
                    'label' => 'Style',
                    'type' => 'select',
                    'options' => [
                        'Default' => 'default',
                        'Primary' => 'primary',
                        'Secondary' => 'secondary',
                        'Overlay' => 'overlay',
                    ],
                    'show' => '$match(consent.banner_layout, \'^card\')',
                ],
                'consent.card_padding_small' => [
                    'type' => 'checkbox',
                    'text' => 'Small padding',
                    'show' => '$match(consent.banner_layout, \'^card\')',
                ],
                'consent.notification_style' => [
                    'label' => 'Style',
                    'type' => 'select',
                    'options' => [
                        'Default' => '',
                        'Primary' => 'primary',
                        'Warning' => 'warning',
                        'Danger' => 'danger',
                    ],
                    'show' => '$match(consent.banner_layout, \'^notification\')',
                ],
                'consent.section_width' => [
                    'label' => 'Width',
                    'description' => 'Set the width of the banner content.',
                    'type' => 'select',
                    'options' => [
                        'Default' => '',
                        'X-Small' => 'xsmall',
                        'Small' => 'small',
                        'Large' => 'large',
                        'X-Large' => 'xlarge',
                        'Expand' => 'expand',
                    ],
                    'show' => '$match(consent.banner_layout, \'^section\')',
                ],
                'consent.section_style' => [
                    'label' => 'Style',
                    'type' => 'select',
                    'options' => [
                        'Default' => 'default',
                        'Muted' => 'muted',
                        'Primary' => 'primary',
                        'Secondary' => 'secondary',
                    ],
                    'show' => '$match(consent.banner_layout, \'^section\')',
                ],
                'consent.section_grid' => [
                    'label' => 'Grid',
                    'type' => 'select',
                    'options' => [
                        'Stacked' => '',
                        'Auto' => 'auto',
                        'Expand' => 'expand',
                    ],
                    'show' => '$match(consent.banner_layout, \'^section\')',
                ],
                'consent.section_grid_breakpoint' => [
                    'label' => 'Grid Breakpoint',
                    'description' => 'Set the breakpoint from which grid items will stack.',
                    'type' => 'select',
                    'options' => [
                        'Always' => '',
                        'Small (Phone Landscape)' => 's',
                        'Medium (Tablet Landscape)' => 'm',
                        'Large (Desktop)' => 'l',
                        'X-Large (Large Screens)' => 'xl',
                    ],
                    'show' => '$match(consent.banner_layout, \'^section\')',
                    'enable' => 'consent.section_grid',
                ],
                'consent.banner_content_style' => [
                    'label' => 'Content Style',
                    'description' => 'Select a predefined text style, including color, size and font-family.',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'Meta' => 'meta',
                        'Lead' => 'lead',
                        'Small' => 'small',
                        'Large' => 'large',
                    ],
                ],
                'consent.button_size' => [
                    'label' => 'Button Size',
                    'type' => 'select',
                    'options' => [
                        'Small' => 'small',
                        'Default' => '',
                        'Large' => 'large',
                    ],
                ],
                'consent.button_width' => [
                    'label' => 'Button Width',
                    'type' => 'select',
                    'options' => [
                        'Auto' => '',
                        'Expand' => 'expand',
                        'Expand Equal' => 'equal',
                        '100%' => 'full',
                    ],
                ],
                'consent.button_accept_style' => [
                    'label' => 'Button Accept Style',
                    'type' => 'select',
                    'options' => [
                        'Button Default' => 'default',
                        'Button Primary' => 'primary',
                        'Button Secondary' => 'secondary',
                        'Button Text' => 'text',
                    ],
                ],
                'consent.button_reject_style' => [
                    'label' => 'Button Reject Style',
                    'type' => 'select',
                    'options' => [
                        'Button Default' => 'default',
                        'Button Primary' => 'primary',
                        'Button Secondary' => 'secondary',
                        'Button Text' => 'text',
                    ],
                ],
                'consent.button_settings_style' => [
                    'label' => 'Button Settings Style',
                    'type' => 'select',
                    'options' => [
                        'Button Default' => 'default',
                        'Button Primary' => 'primary',
                        'Button Secondary' => 'secondary',
                        'Button Text' => 'text',
                    ],
                ],
            ],
        ],
        'consent-modal' => [
            'title' => 'Modal',
            'width' => 500,
            'fields' => [
                'consent.modal_width' => [
                    'label' => 'Width',
                    'attrs' => [
                        'placeholder' => '720',
                    ],
                ],
                'consent.modal_sections' => [
                    'type' => 'checkbox',
                    'description' => 'Set the modal width in pixels.',
                    'text' => 'Show header and footer sections',
                ],
                'consent.modal_title_style' => [
                    'label' => 'Title Style',
                    'type' => 'select',
                    'options' => [
                        'Default' => '',
                        'Heading Small' => 'heading-small',
                        'Heading H1' => 'h1',
                        'Heading H2' => 'h2',
                        'Heading H3' => 'h3',
                        'Heading H4' => 'h4',
                        'Heading H5' => 'h5',
                        'Heading H6' => 'h6',
                    ],
                ],
                'consent.modal_title_element' => [
                    'label' => 'Title HTML Element',
                    'type' => 'select',
                    'options' => [
                        'h1' => 'h1',
                        'h2' => 'h2',
                        'h3' => 'h3',
                        'h4' => 'h4',
                        'h5' => 'h5',
                        'h6' => 'h6',
                        'div' => 'div',
                    ],
                ],
                'consent.modal_content_style' => [
                    'label' => 'Content Style',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'Meta' => 'meta',
                        'Lead' => 'lead',
                        'Small' => 'small',
                        'Large' => 'large',
                    ],
                ],
                'consent.modal_accordion_icon_width' => [
                    'label' => 'Accordion Icon Width',
                    'description' => 'Set the icon width.',
                    'show' => 'consent.modal_layout == \'accordion\'',
                ],
                'consent.modal_categories_right' => [
                    'label' => 'Category Layout',
                    'type' => 'checkbox',
                    'text' => 'Show checkboxes on the right',
                ],
                'consent.modal_categories_checkbox_large' => [
                    'type' => 'checkbox',
                    'text' => 'Large checkboxes',
                ],
                'consent.modal_categories_description' => [
                    'type' => 'checkbox',
                    'text' => 'Show category descriptions',
                ],
                'consent.modal_category_margin' => [
                    'label' => 'Category Margin Top',
                    'type' => 'select',
                    'options' => [
                        'X-Small' => 'xsmall',
                        'Small' => 'small',
                        'Default' => '',
                        'Medium' => 'medium',
                        'Large' => 'large',
                        'X-Large' => 'xlarge',
                        'None' => 'remove',
                    ],
                ],
                'consent.modal_category_grid_row_gap' => [
                    'label' => 'Category Grid Row Gap',
                    'type' => 'select',
                    'options' => [
                        'Small' => 'small',
                        'Medium' => 'medium',
                        'Default' => '',
                        'Large' => 'large',
                        'None' => 'collapse',
                    ],
                ],
                'consent.modal_category_title_style' => [
                    'label' => 'Category Title Style',
                    'type' => 'select',
                    'options' => [
                        'Heading H1' => 'h1',
                        'Heading H2' => 'h2',
                        'Heading H3' => 'h3',
                        'Heading H4' => 'h4',
                        'Heading H5' => 'h5',
                        'Heading H6' => 'h6',
                    ],
                ],
                 'consent.modal_category_title_element' => [
                    'label' => 'Category Title HTML Element',
                    'type' => 'select',
                    'options' => [
                        'h1' => 'h1',
                        'h2' => 'h2',
                        'h3' => 'h3',
                        'h4' => 'h4',
                        'h5' => 'h5',
                        'h6' => 'h6',
                        'div' => 'div',
                    ],
                ],
                'consent.modal_category_content_style' => [
                    'label' => 'Category Content Style',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'Meta' => 'meta',
                        'Lead' => 'lead',
                        'Small' => 'small',
                        'Large' => 'large',
                    ],
                ],
                'consent.modal_toggles_link_style' => [
                    'label' => 'Category Toggles Link Style',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'Muted' => 'muted',
                        'Text' => 'text',
                    ],
                    'show' => 'consent.modal_layout == \'toggles\'',
                ],
                'consent.modal_button_size' => [
                    'label' => 'Button Size',
                    'type' => 'select',
                    'options' => [
                        'Small' => 'small',
                        'Default' => '',
                        'Large' => 'large',
                    ],
                ],
                'consent.modal_button_width' => [
                    'label' => 'Button Width',
                    'type' => 'select',
                    'options' => [
                        'Auto' => '',
                        'Expand Equal' => 'equal',
                    ],
                ],
                'consent.modal_button_flip' => [
                    'label' => 'Buttons Alignment',
                    'type' => 'checkbox',
                    'text' => 'Flip buttons',
                ],
                'consent.modal_button_accept_style' => [
                    'label' => 'Button Accept Style',
                    'type' => 'select',
                    'options' => [
                        'Button Default' => 'default',
                        'Button Primary' => 'primary',
                        'Button Secondary' => 'secondary',
                        'Button Text' => 'text',
                    ],
                ],
                'consent.modal_button_reject_style' => [
                    'label' => 'Button Reject Style',
                    'type' => 'select',
                    'options' => [
                        'Button Default' => 'default',
                        'Button Primary' => 'primary',
                        'Button Secondary' => 'secondary',
                        'Button Text' => 'text',
                    ],
                ],
                'consent.modal_button_save_style' => [
                    'label' => 'Button Save Style',
                    'type' => 'select',
                    'options' => [
                        'Button Default' => 'default',
                        'Button Primary' => 'primary',
                        'Button Secondary' => 'secondary',
                        'Button Text' => 'text',
                    ],
                ],
            ],
        ],
    ],
];
