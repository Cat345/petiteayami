<?php
return array(
	'tracking_analysis' => array(
		'title'    => __( 'Tracking Analytics', 'funnel-builder' ),
		'heading'  => __( 'Tracking and Analytics', 'funnel-builder' ),
		'slug'     => 'tracking_analysis',
		'hint'     => __( 'Use this to adjust the tracking events for one-page checkouts', 'funnel-builder' ),
		'fields'   => array(
			array(
				'type'   => 'radios',
				'key'    => 'override_global_track_event',
				'label'  => __( 'Override Global Settings', 'funnel-builder' ),
				'hint'   => '',
				'values' => array(
					0 => array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					1 => array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'bwf-label',
				'key'     => 'fb_pixel',
				'label'   => __( 'Facebook Pixel', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
			),
			// FB Pixel.
			array(
				'type'    => 'radios',
				'key'     => 'pixel_is_page_view',
				'label'   => __( 'Enable PageView Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'pixel_add_to_cart_event',
				'label'   => __( 'Enable AddtoCart Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'pixel_add_to_cart_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'pixel_add_to_cart_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'pixel_initiate_checkout_event',
				'label'   => __( 'Enable InitiateCheckout Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'pixel_initiate_checkout_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'pixel_initiate_checkout_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'pixel_add_payment_info_event',
				'label'   => __( 'Enable AddPaymentInfo Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			// Google Analytics.
			array(
				'type'    => 'bwf-label',
				'key'     => 'google_analytics',
				'label'   => __( 'Google Analytics', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ua_is_page_view',
				'label'   => __( 'Enable PageView Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ua_add_to_cart_event',
				'label'   => __( 'Enable AddtoCart Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'google_ua_add_to_cart_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'google_ua_add_to_cart_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ua_initiate_checkout_event',
				'label'   => __( 'Enable BeginCheckout Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'google_ua_initiate_checkout_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'google_ua_initiate_checkout_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ua_add_payment_info_event',
				'label'   => __( 'Enable AddPaymentInfo Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ua_add_shipping_info_event',
				'label'   => __( 'Enable AddShippingInfo Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'google_ua_add_shipping_info_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'google_ua_add_shipping_info_event',
						'value' => 'true',
					),
				),
				'values'  => array(
					array(
						'value' => 'load',
						'name'  => __( 'On Page Load', 'funnel-builder' ),
					),
					array(
						'value' => 'button',
						'name'  => __( 'On Button Click', 'funnel-builder' ),
					),
				),
			),
			// Google Ads.
			array(
				'type'    => 'bwf-label',
				'key'     => 'google_ads',
				'label'   => __( 'Google ADS', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ads_is_page_view',
				'label'   => __( 'Enable PageView Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ads_add_to_cart_event',
				'label'   => __( 'Enable AddtoCart Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'google_ads_add_to_cart_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'google_ads_add_to_cart_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'google_ads_initiate_checkout_event',
				'label'   => __( 'Enable InitiateCheckout Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'google_ads_initiate_checkout_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'google_ads_initiate_checkout_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			// Pinterest
			array(
				'type'    => 'bwf-label',
				'key'     => 'pinterest',
				'label'   => __( 'Pinterest', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'pint_is_page_view',
				'label'   => __( 'Enable PageView Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'pint_add_to_cart_event',
				'label'   => __( 'Enable AddtoCart Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'pint_add_to_cart_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'pint_add_to_cart_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'pint_initiate_checkout_event',
				'label'   => __( 'Enable InitiateCheckout Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			// Tiktok.
			array(
				'type'    => 'bwf-label',
				'key'     => 'TikTok',
				'label'   => __( 'TikTok', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'tiktok_is_page_view',
				'label'   => __( 'Enable PageView Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'tiktok_add_to_cart_event',
				'label'   => __( 'Enable AddtoCart Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'tiktok_add_to_cart_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'tiktok_add_to_cart_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'tiktok_initiate_checkout_event',
				'label'   => __( 'Enable InitiateCheckout Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'tiktok_initiate_checkout_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'tiktok_initiate_checkout_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),

			// Snapchat.
			array(
				'type'    => 'bwf-label',
				'key'     => 'snap_chat',
				'label'   => __( 'SnapChat', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'snapchat_is_page_view',
				'label'   => __( 'Enable PageView Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'radios',
				'key'     => 'snapchat_add_to_cart_event',
				'label'   => __( 'Enable AddtoCart Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'snapchat_add_to_cart_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'snapchat_add_to_cart_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
			array(
				'type'    => 'radios',
				'key'     => 'snapchat_initiate_checkout_event',
				'label'   => __( 'Enable InitiateCheckout Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					'key'   => 'override_global_track_event',
					'value' => 'true',
				),
				'values'  => array(
					array(
						'value' => 'true',
						'name'  => __( 'Yes', 'funnel-builder' ),
					),
					array(
						'value' => 'false',
						'name'  => __( 'No', 'funnel-builder' ),
					),
				),
			),
			array(
				'type'    => 'select',
				'key'     => 'snapchat_initiate_checkout_event_position',
				'label'   => __( 'Trigger Event', 'funnel-builder' ),
				'hint'    => '',
				'toggler' => array(
					array(
						'key'   => 'override_global_track_event',
						'value' => 'true',
					),
					array(
						'key'   => 'snapchat_initiate_checkout_event',
						'value' => 'true',
					),
				),
				'values'  => $track_event_options,
			),
		),
		'priority' => 10,
		'values'   => $tracking_analysis,
	),
	'header_css'        => array(
		'title'    => __( 'Custom CSS', 'funnel-builder' ),
		'heading'  => __( 'Custom CSS', 'funnel-builder' ),
		'hint'     => __( 'Add Custom CSS on checkout page', 'funnel-builder' ),
		'slug'     => 'custom_css',
		'fields'   => array(
			array(
				'key'         => 'header_css',
				'type'        => 'textArea',
				'label'       => __( 'CSS', 'funnel-builder' ),
				'placeholder' => __( 'Paste your CSS code here', 'funnel-builder' ),
				'className'   => 'bwf-textarea-lg-resizable',
			),
		),
		'priority' => 30,
		'values'   => array(
			'header_css' => ! empty( $values['header_css'] ) ? $values['header_css'] : '',
		),
	),
	'custom_js'         => array(
		'title'    => __( 'Custom Scripts', 'funnel-builder' ),
		'heading'  => __( 'Embed Script', 'funnel-builder' ),
		'hint'     => __( 'Add custom scripts on checkout page', 'funnel-builder' ),
		'slug'     => 'custom_js',
		'fields'   => array(
			array(
				'key'         => 'header_script',
				'type'        => 'textArea',
				'label'       => __( 'Header', 'funnel-builder' ),
				'placeholder' => __( 'Paste your code here', 'funnel-builder' ),
				'className'   => 'bwf-textarea-lg-resizable',
			),
			array(
				'key'         => 'footer_script',
				'type'        => 'textArea',
				'label'       => __( 'Footer', 'funnel-builder' ),
				'placeholder' => __( 'Paste your code here', 'funnel-builder' ),
				'className'   => 'bwf-textarea-lg-resizable',
			),
		),
		'priority' => 20,
		'values'   => array(
			'header_script' => ! empty( $values['header_script'] ) ? $values['header_script'] : '',
			'footer_script' => ! empty( $values['footer_script'] ) ? $values['footer_script'] : '',
		),
	),
);
