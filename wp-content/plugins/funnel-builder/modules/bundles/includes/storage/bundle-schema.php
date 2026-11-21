<?php
/**
 * The bundle form, described on the server.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Describes every field a bundle carries, so the description lives in one
 * place rather than only inside the admin app.
 *
 * A field names a `type`; it does not choose a component. The admin app maps a
 * type to whatever renders it, which is why a repeater or a design panel can be
 * described here without being flattened into a generic control.
 *
 * An add-on describes the fields for the type it owns through
 * `wffn_bundles_bundle_schema`.
 */
class Bundle_Schema {

	/** Field types whose `fields` describe one row rather than the value itself. */
	const ROW_TYPES = array( 'repeater' );

	/** Field types that draw something but hold no value of their own. */
	const VALUELESS_TYPES = array( 'html', 'note', 'mirror' );

	/** Sections, by tab, for parts a Frequently Bought Together widget does not draw. */
	const FBT_UNSUPPORTED_SECTIONS = array(
		'settings' => array( 'upsells', 'gifts', 'images' ),
		'design'   => array( 'badge' ),
	);

	/**
	 * The whole form: tabs, each holding sections, each holding fields.
	 *
	 * @return array
	 */
	public static function get() {
		$schema = array(
			'tabs' => array(
				'settings' => array(
					'label'    => __( 'Settings', 'funnel-builder' ),
					'sections' => self::settings_sections(),
				),
				'design'   => array(
					'label'    => __( 'Design', 'funnel-builder' ),
					'sections' => self::design_sections(),
				),
			),
		);

		/**
		 * Filters the bundle form description.
		 *
		 * An add-on appends the sections and fields for the bundle type it owns.
		 *
		 * @since 1.0.0
		 *
		 * @param array $schema Tabs, each holding sections, each holding fields.
		 */
		$schema = (array) apply_filters( 'wffn_bundles_bundle_schema', $schema );

		// An add-on appends; `order` is what puts its sections where they belong.
		foreach ( $schema['tabs'] as $id => $tab ) {
			if ( empty( $tab['sections'] ) ) {
				continue;
			}
			$sections = $tab['sections'];
			uasort(
				$sections,
				static function ( $a, $b ) {
					return (int) ( $a['order'] ?? 999 ) <=> (int) ( $b['order'] ?? 999 );
				}
			);
			$schema['tabs'][ $id ]['sections'] = $sections;
		}

		return self::without_fbt_unsupported( $schema );
	}

	/**
	 * Hide the sections a Frequently Bought Together bundle never renders.
	 *
	 * Applied after the filter, so a section an add-on or integration supplies
	 * is hidden too. A section that already names its own bundle types is left alone.
	 *
	 * @param array $schema Tabs, each holding sections.
	 * @return array
	 */
	private static function without_fbt_unsupported( array $schema ) {
		$others = array_values( array_diff( Bundle_Store::accepted_types(), array( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER ) ) );
		foreach ( self::FBT_UNSUPPORTED_SECTIONS as $tab => $ids ) {
			foreach ( $ids as $section_id ) {
				if ( ! isset( $schema['tabs'][ $tab ]['sections'][ $section_id ] ) ) {
					continue;
				}
				$section = $schema['tabs'][ $tab ]['sections'][ $section_id ];
				if ( isset( $section['condition']['discountType'] ) ) {
					continue;
				}
				$section['condition']                              = array_merge( array( 'discountType' => $others ), (array) ( $section['condition'] ?? array() ) );
				$schema['tabs'][ $tab ]['sections'][ $section_id ] = $section;
			}
		}
		return $schema;
	}

	/**
	 * Every field key the form carries, flattened.
	 *
	 * The admin app's own schema is the other half of this contract; a test
	 * asserts the two agree.
	 *
	 * @param bool $deep Include the children of composite fields.
	 * @return string[] Sorted, unique.
	 */
	public static function keys( $deep = false ) {
		$keys = array();

		foreach ( (array) ( self::get()['tabs'] ?? array() ) as $tab ) {
			foreach ( (array) ( $tab['sections'] ?? array() ) as $section ) {
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					if ( empty( $field['key'] ) || in_array( $field['type'] ?? '', self::VALUELESS_TYPES, true ) ) {
						continue;
					}

					$key    = (string) $field['key'];
					$keys[] = $key;

					if ( ! $deep || empty( $field['fields'] ) ) {
						continue;
					}

					// A repeater's children belong to a row, not to the field.
					$prefix = in_array( $field['type'], self::ROW_TYPES, true ) ? $key . '[].' : $key . '.';
					foreach ( (array) $field['fields'] as $child ) {
						if ( empty( $child['key'] ) || in_array( $child['type'] ?? '', self::VALUELESS_TYPES, true ) ) {
							continue;
						}
						$keys[] = $prefix . (string) $child['key'];
					}
				}
			}
		}

		$keys = array_values( array_unique( $keys ) );
		sort( $keys );

		return $keys;
	}

	/**
	 * The Settings tab.
	 *
	 * @return array
	 */
	private static function settings_sections() {
		$fb  = array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES );
		$fbt = array( 'discountType' => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER );

		return array(
			'header'        => array(
				'label'     => __( 'Bundle', 'funnel-builder' ),
				'accordion' => false,
				'order'     => 10,
				// The page header draws these; they are described, not placed.
				'fields'    => array(
					array(
						'key'        => 'bundleName',
						'type'       => 'input',
						'label'      => __( 'Bundle name', 'funnel-builder' ),
						'required'   => true,
						'max'        => Bundles_Table::NAME_LENGTH,
						'renderedBy' => 'pageHeader',
					),
					array(
						'key'        => 'status',
						'type'       => 'toggle',
						'label'      => __( 'Active', 'funnel-builder' ),
						'default'    => 0,
						'renderedBy' => 'pageHeader',
					),
					array(
						'key'        => 'discountType',
						'type'       => 'bundle_type',
						'label'      => __( 'Bundle type', 'funnel-builder' ),
						'options'    => self::type_options(),
						'renderedBy' => 'pageHeader',
					),
				),
			),
			'products'      => array(
				'label'       => __( 'Products', 'funnel-builder' ),
				'description' => __( 'Pick the products or categories whose pages show this bundle.', 'funnel-builder' ),
				'testId'      => 'products',
				'defaultOpen' => true,
				'order'       => 20,
				'fields'      => self::placement_fields(),
			),
			'discount'      => array(
				'label'       => __( 'Discount', 'funnel-builder' ),
				'description' => __( 'Choose what the discount applies to and how much it takes off.', 'funnel-builder' ),
				'testId'      => 'discount',
				'defaultOpen' => true,
				'condition'   => $fb,
				// After what the bundle holds, before how it is arranged.
				'order'       => 27,
				'fields'      => self::fixed_bundle_discount_fields(),
			),
			'layout'        => array(
				'label'       => __( 'Layout', 'funnel-builder' ),
				'description' => __( "Set the offer's arrangement and the text shown above it.", 'funnel-builder' ),
				'testId'      => 'layout',
				'order'       => 30,
				'fields'      => array(
					array(
						'key'   => 'bundleHeading',
						'type'  => 'input',
						'label' => __( 'Heading', 'funnel-builder' ),
						'hint'  => __( 'Enter heading', 'funnel-builder' ),
						'width' => 'half',
					),
					array(
						'key'   => 'bundleSubheading',
						'type'  => 'input',
						'label' => __( 'Subheading', 'funnel-builder' ),
						'hint'  => __( 'Enter subheading', 'funnel-builder' ),
						'width' => 'half',
					),
					array(
						'key'       => 'fbtLayout',
						'type'      => 'option_cards',
						'label'     => __( 'Layout', 'funnel-builder' ),
						'default'   => 'row',
						'condition' => $fbt,
						'options'   => array(
							array( 'value' => 'row', 'label' => __( 'Row', 'funnel-builder' ), 'icon' => 'GalleryHorizontal' ),
							array( 'value' => 'list', 'label' => __( 'List', 'funnel-builder' ), 'icon' => 'List' ),
						),
					),
					array(
						'key'         => 'fbtButtonLabel',
						'type'        => 'input',
						'label'       => __( 'Button text', 'funnel-builder' ),
						'hint'        => __( 'Add 3 items to cart', 'funnel-builder' ),
						'description' => __( 'Leave empty to show how many items the button adds.', 'funnel-builder' ),
						'width'       => 'half',
						'condition'   => $fbt,
					),
					array(
						'key'       => 'fixedBundleTitle',
						'type'      => 'input',
						'label'     => __( 'Bundle Title', 'funnel-builder' ),
						'hint'      => __( 'Enter bundle title', 'funnel-builder' ),
						'width'     => 'half',
						'condition' => array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES ),
					),
					array(
						'key'       => 'fixedBundleSubtitle',
						'type'      => 'input',
						'label'     => __( 'Bundle Subtitle', 'funnel-builder' ),
						'hint'      => __( 'Enter bundle subtitle', 'funnel-builder' ),
						'width'     => 'half',
						'condition' => array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES ),
					),
					array(
						'key'       => 'fixedBundleShowSingle',
						'type'      => 'toggle',
						'label'     => __( 'Show a single-product option', 'funnel-builder' ),
						'tooltip'   => __( 'Also let shoppers buy the product on its own, at its regular price, next to the bundle.', 'funnel-builder' ),
						'condition' => array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES ),
					),
					array(
						'key'       => 'fixedBundleSingleTitle',
						'type'      => 'input',
						'label'     => __( 'Single product title', 'funnel-builder' ),
						'hint'      => __( 'Enter title', 'funnel-builder' ),
						'width'     => 'half',
						'condition' => array(
							'discountType'          => Bundle_Store::TYPE_FIXED_BUNDLES,
							'fixedBundleShowSingle' => true,
						),
					),
					array(
						'key'       => 'fixedBundleSingleSubtitle',
						'type'      => 'input',
						'label'     => __( 'Single product subtitle', 'funnel-builder' ),
						'hint'      => __( 'Enter subtitle', 'funnel-builder' ),
						'width'     => 'half',
						'condition' => array(
							'discountType'          => Bundle_Store::TYPE_FIXED_BUNDLES,
							'fixedBundleShowSingle' => true,
						),
					),
				),
			),
			'items'         => array(
				'label'       => __( 'Bundle Items', 'funnel-builder' ),
				'description' => __( 'Choose the products in the bundle and their quantities.', 'funnel-builder' ),
				'testId'      => 'items',
				'defaultOpen' => true,
				'condition'   => $fb,
				// What is in the bundle comes before how it is arranged.
				'order'       => 25,
				'fields'      => self::fixed_bundle_fields(),
			),
			'companions'    => array(
				'label'       => __( 'Bought Together', 'funnel-builder' ),
				'description' => __( 'Choose the products shown with the viewed product, and the discount for buying them together.', 'funnel-builder' ),
				'testId'      => 'companions',
				'defaultOpen' => true,
				'condition'   => $fbt,
				'order'       => 25,
				'fields'      => self::fbt_fields(),
			),
			'upsells'       => array(
				'label'       => __( 'Upsells', 'funnel-builder' ),
				'description' => __( 'Offer discounted add-ons next to the bundle, added to the cart separately.', 'funnel-builder' ),
				'testId'      => 'upsells',
				'order'       => 50,
				'promo'       => array(
					'preview'  => 'upsells',
					'message'  => __( 'Sell a matching add-on beside the bundle, at its own discount, added to the cart as its own line.', 'funnel-builder' ),
					'linkTags' => array( 'upsells', 'editor' ),
				),
				'fields'      => array(),
			),
			'gifts'         => array(
				'label'       => __( 'Progressive Gifts', 'funnel-builder' ),
				'description' => __( 'Set gifts or free shipping that unlock when shoppers reach higher offers.', 'funnel-builder' ),
				'testId'      => 'gifts',
				'order'       => 60,
				'promo'       => array(
					'preview'  => 'gifts',
					'message'  => __( 'Unlock a free gift or free shipping as the order grows, so a shopper has a reason to reach the next offer.', 'funnel-builder' ),
					'linkTags' => array( 'gifts', 'editor' ),
				),
				'fields'      => array(),
			),
			'images'        => array(
				'label'       => __( 'Product Images', 'funnel-builder' ),
				'description' => __( 'Manage which image shows for each offer across your selected products.', 'funnel-builder' ),
				'testId'      => 'images',
				'order'       => 90,
				'promo'       => array(
					'preview'  => 'images',
					'message'  => __( 'Show a different picture for each offer, so shoppers see what they get before they pick it.', 'funnel-builder' ),
					'linkTags' => array( 'images', 'editor' ),
				),
				'fields'      => array(),
			),
			'targeting'     => array(
				'label'       => __( 'Targeting & Schedule', 'funnel-builder' ),
				'description' => __( 'Choose who can see the bundle and when it runs.', 'funnel-builder' ),
				'testId'      => 'targeting',
				'order'       => 100,
				'promo'       => array(
					'preview'  => 'targeting',
					'message'  => __( 'Show a bundle only to the roles you choose, and run it between the dates you set, for launches, sales and wholesale pricing.', 'funnel-builder' ),
					'linkTags' => array( 'targeting', 'editor' ),
				),
				'fields'      => array(),
			),
		);
	}

	/**
	 * Which products the bundle runs on.
	 *
	 * @return array
	 */
	private static function placement_fields() {
		$categories = array( 'productMode' => Bundles_Table::TARGET_CATEGORY );

		return array(
			array(
				'key'         => 'productMode',
				'type'        => 'radio',
				'label'       => __( 'Apply to', 'funnel-builder' ),
				// The section header already names it.
				'hideLabel'   => true,
				'orientation' => 'horizontal',
				'default'     => Bundles_Table::TARGET_PRODUCT,
				// A pack names its own products; only an add-on's type targets a catalogue.
				'condition'   => array( 'discountType' => self::category_target_types() ),
				'options'     => array(
					array( 'value' => Bundles_Table::TARGET_PRODUCT, 'label' => __( 'Specific products', 'funnel-builder' ) ),
					array( 'value' => Bundles_Table::TARGET_CATEGORY, 'label' => __( 'Specific categories', 'funnel-builder' ) ),
				),
			),
			array(
				'key'         => 'products',
				'type'        => 'catalog_select',
				'variant'     => 'dialog',
				'label'       => __( 'Products', 'funnel-builder' ),
				'hideLabel'   => true,
				'dialogTitle' => __( 'Select Products', 'funnel-builder' ),
				'addLabel'    => __( 'Add Products', 'funnel-builder' ),
				'condition'   => array( 'productMode' => Bundles_Table::TARGET_PRODUCT ),
			),
			array(
				'key'       => 'selectedCategories',
				'type'      => 'field_promo',
				'label'     => __( 'Categories', 'funnel-builder' ),
				'message'   => __( 'One-click scale: place this bundle across a whole category instead of picking products one by one.', 'funnel-builder' ),
				'linkTags'  => array( 'category', 'targeting' ),
				'condition' => $categories,
			),
			array(
				'key'        => 'excludeEnabled',
				'type'       => 'checkbox',
				'label'      => __( 'Exclude Products', 'funnel-builder' ),
				'default'    => false,
				'renderedBy' => 'selectedCategories',
			),
			array(
				'key'        => 'excludedProducts',
				'type'       => 'catalog_select',
				'variant'    => 'tags',
				'label'      => __( 'Excluded products', 'funnel-builder' ),
				'renderedBy' => 'selectedCategories',
			),
			array(
				'key'        => 'excludedCategories',
				'type'       => 'catalog_select',
				'entity'     => 'category',
				'label'      => __( 'Excluded categories', 'funnel-builder' ),
				'renderedBy' => 'selectedCategories',
			),
		);
	}

	/**
	 * The Design tab.
	 *
	 * The token panel is one field: its 36 tokens are a design system, not a
	 * form, and describing them here would put styling on the wire.
	 *
	 * @return array
	 */
	private static function design_sections() {
		$fb = array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES );
		// The Frequently Bought Together row draws no selected item, so its selected-state tokens do nothing.
		$fbt_row = array(
			'discountType' => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
			'fbtLayout'    => 'row',
		);

		return array(
			'theme'      => array(
				'label'       => __( 'Theme', 'funnel-builder' ),
				'description' => __( 'Theme the whole bundle from one brand color, and save it to reuse.', 'funnel-builder' ),
				'testId'      => 'design-theme',
				'order'       => 10,
				'promo'       => array(
					'preview'  => 'theme',
					'message'  => __( 'Set your brand color once and every offer follows it, then save the result as a preset and apply it to the next bundle in a click.', 'funnel-builder' ),
					'linkTags' => array( 'design', 'themes' ),
				),
				'fields'      => array(
					array( 'key' => 'bundleDesign', 'type' => 'hidden', 'fields' => self::bundle_design_fields() ),
				),
			),
			'container'  => array(
				'label'       => __( 'Layout & Container', 'funnel-builder' ),
				'description' => __( "Style the option cards' background, border, corners and padding.", 'funnel-builder' ),
				'defaultOpen' => true,
				'order'       => 20,
				'fields'      => self::design_row_fields(
					array(
						array( 'layout_bgColor', 'state-color', __( 'Background', 'funnel-builder' ), null, null, array( 'stateless' => $fbt_row ) ),
						array( 'layout_borderColor', 'state-color', __( 'Border', 'funnel-builder' ), null, null, array( 'stateless' => $fbt_row ) ),
						array( 'layout_borderStyle', 'select', __( 'Border Style', 'funnel-builder' ), 'half' ),
						array( 'layout_borderWidth', 'number', __( 'Border Width', 'funnel-builder' ), 'half' ),
						// Every type but the Frequently Bought Together row; ids 1 and 3 are add-on types.
						array( 'layout_selectedBorderWidth', 'number', __( 'Selected Border Width', 'funnel-builder' ), 'half', array( '_any' => array( 'discountType' => array( 1, Bundle_Store::TYPE_FIXED_BUNDLES, 3 ), 'fbtLayout' => 'list' ) ) ),
						array( 'layout_cornerRadius', 'number', __( 'Corner Radius', 'funnel-builder' ), 'half' ),
						array( 'layout_padding', 'dimension', __( 'Padding', 'funnel-builder' ), 'half' ),
					)
				),
			),
			'image'      => array(
				'label'       => __( 'Image', 'funnel-builder' ),
				'description' => __( "Choose the image file, corners and widths of the bundle's images. Leave a width empty to keep the layout size.", 'funnel-builder' ),
				'order'       => 25,
				'fields'      => self::design_row_fields(
					array(
						array(
							'image_size',
							'image_size',
							__( 'Image Size', 'funnel-builder' ),
							'half',
							null,
							array(
								'default'    => 'thumbnail',
								'groupLabel' => __( 'WordPress sizes', 'funnel-builder' ),
								'options'    => self::image_size_options(),
							),
						),
						array( 'layout_imageRadius', 'number', __( 'Image Radius', 'funnel-builder' ), 'half' ),
						// One field per type so the placeholder shows that layout's own size; ids 1 and 3 are add-on types.
						array( 'layout_offerImageWidth', 'number', __( 'Offer Image Width', 'funnel-builder' ), 'half', array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES ), array( 'hint' => '56' ) ),
						array( 'layout_offerImageWidth', 'number', __( 'Offer Image Width', 'funnel-builder' ), 'half', array( 'discountType' => array( 1, 3 ) ), array( 'hint' => '48' ) ),
						array( 'layout_itemImageWidth', 'number', __( 'Item Images Width', 'funnel-builder' ), 'half', array( 'discountType' => array( 1, Bundle_Store::TYPE_FIXED_BUNDLES ) ), array( 'hint' => '32' ) ),
						array( 'layout_itemImageWidth', 'number', __( 'Item Images Width', 'funnel-builder' ), 'half', array( 'discountType' => 3 ), array( 'hint' => '72' ) ),
						array( 'layout_itemImageWidth', 'number', __( 'Item Images Width', 'funnel-builder' ), 'half', $fbt_row, array( 'hint' => '72' ) ),
						array( 'layout_itemImageWidth', 'number', __( 'Item Images Width', 'funnel-builder' ), 'half', array( 'discountType' => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER, 'fbtLayout' => 'list' ), array( 'hint' => '56' ) ),
					)
				),
			),
			'typography' => array(
				'label'       => __( 'Typography', 'funnel-builder' ),
				'description' => __( "Set the size and color of the widget's text.", 'funnel-builder' ),
				'order'       => 30,
				'fields'      => self::design_row_fields(
					array(
						array( 'typography_headingFontSize', 'number', __( 'Heading Size', 'funnel-builder' ), 'half' ),
						array( 'typography_headingColor', 'color', __( 'Heading Color', 'funnel-builder' ), 'half' ),
						array( 'typography_bundleTitleFontSize', 'number', __( 'Title Size', 'funnel-builder' ), 'half' ),
						array( 'typography_bundleTitleColor', 'color', __( 'Title Color', 'funnel-builder' ), 'half' ),
						array( 'typography_descriptionFontSize', 'number', __( 'Description Size', 'funnel-builder' ), 'half' ),
						array( 'typography_descriptionColor', 'color', __( 'Description Color', 'funnel-builder' ), 'half' ),
						array( 'typography_priceFontSize', 'number', __( 'Price Size', 'funnel-builder' ), 'half' ),
						array( 'typography_regularPriceColor', 'color', __( 'Regular Price Color', 'funnel-builder' ), 'half' ),
						array( 'typography_salePriceColor', 'color', __( 'Sale Price Color', 'funnel-builder' ), 'half' ),
						// Quantity Breaks (add-on type 1) and Frequently Bought Together draw a price description; the others do not.
						array( 'typography_priceDescriptionFontSize', 'number', __( 'Price Description Size', 'funnel-builder' ), 'half', array( 'discountType' => array( 1, Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER ) ) ),
						array( 'typography_priceDescriptionColor', 'color', __( 'Price Description Color', 'funnel-builder' ), 'half', array( 'discountType' => array( 1, Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER ) ) ),
					)
				),
			),
			'badge'      => array(
				'label'       => __( 'Badge', 'funnel-builder' ),
				'description' => __( 'Style the savings badge shown on discounted options.', 'funnel-builder' ),
				'order'       => 40,
				'fields'      => self::design_row_fields(
					array(
						array( 'badge_bgColor', 'color', __( 'Background', 'funnel-builder' ), 'half' ),
						array( 'badge_textColor', 'color', __( 'Text', 'funnel-builder' ), 'half' ),
						array( 'badge_fontSize', 'number', __( 'Font Size', 'funnel-builder' ), 'half' ),
						array( 'badge_borderRadius', 'number', __( 'Corner Radius', 'funnel-builder' ), 'half' ),
					)
				),
			),
			'accents'    => array(
				'label'       => __( 'Selection & Accents', 'funnel-builder' ),
				'description' => __( 'Style the selection control and accent text such as the FREE label.', 'funnel-builder' ),
				// Every type but Frequently Bought Together; ids 1 and 3 are add-on types this plugin does not render.
				'condition'   => array( 'discountType' => array( 1, Bundle_Store::TYPE_FIXED_BUNDLES, 3 ) ),
				'order'       => 50,
				'fields'      => array_merge(
					array(
						array(
							'key'       => 'bundleDesign.layout_radioShow',
							'type'      => 'toggle',
							'label'     => __( 'Show radio button', 'funnel-builder' ),
							'hint'      => __( 'Turn off to let shoppers pick an option by clicking anywhere on its card.', 'funnel-builder' ),
							'default'   => true,
							// Quantity Breaks, Fixed Bundle and Mix & Match (add-on types 1 and 3); Frequently Bought Together has no radio.
							'condition' => array( 'discountType' => array( 1, Bundle_Store::TYPE_FIXED_BUNDLES, 3 ) ),
						),
					),
					self::design_row_fields(
						array(
							array( 'layout_radioColor', 'state-color', __( 'Selection Control', 'funnel-builder' ) ),
							array( 'layout_radioSize', 'number', __( 'Control Size', 'funnel-builder' ), 'half' ),
							// Drawn by upsells, and by gifts inside an offer, which Mix & Match (add-on type 3) never has.
							array( 'accent_successColor', 'color', __( 'Accent', 'funnel-builder' ), 'half', array( '_any' => array( 'upsellsEnabled' => true, '_all' => array( 'progressiveGiftsEnabled' => true, 'discountType' => array( 1, Bundle_Store::TYPE_FIXED_BUNDLES ) ) ) ) ),
						)
					)
				),
			),
		);
	}

	/**
	 * Design tokens as fields, keyed by their path inside `bundleDesign`.
	 *
	 * @param array $rows Tuples of [ token, type, label, width?, condition?, extra props? ].
	 * @return array
	 */
	private static function design_row_fields( array $rows ) {
		$fields = array();

		foreach ( $rows as $row ) {
			$field = array(
				'key'   => 'bundleDesign.' . $row[0],
				'type'  => $row[1],
				'label' => $row[2],
			);
			if ( isset( $row[3] ) ) {
				$field['width'] = $row[3];
			}
			if ( isset( $row[4] ) ) {
				$field['condition'] = $row[4];
			}
			if ( isset( $row[5] ) ) {
				$field = array_merge( $field, $row[5] );
			}
			if ( 'select' === $row[1] ) {
				$field['options'] = self::border_style_options();
			}
			if ( 'number' === $row[1] ) {
				$field['min'] = 0;
			}
			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * The Fixed Bundle discount, in its own box.
	 *
	 * @return array
	 */
	private static function fixed_bundle_discount_fields() {
		$fb    = array( 'discountType' => Bundle_Store::TYPE_FIXED_BUNDLES );
		$whole = array(
			'discountType'             => Bundle_Store::TYPE_FIXED_BUNDLES,
			'fixedBundleDiscountScope' => 'whole_bundle',
		);

		return array(
			array(
				'key'         => 'fixedBundleDiscountScope',
				'type'        => 'radio',
				'label'       => __( 'Apply discount on', 'funnel-builder' ),
				'tooltip'   => __( "How the discount is applied.\n\n• Whole Bundle: one discount on the combined price.\n• Separate Products: a different discount on each item.", 'funnel-builder' ),
				'default'     => 'whole_bundle',
				'orientation' => 'horizontal',
				'condition'   => $fb,
				'options'     => array(
					array( 'value' => 'whole_bundle', 'label' => __( 'Whole Bundle', 'funnel-builder' ) ),
					array( 'value' => 'separate_products', 'label' => __( 'Separate Products', 'funnel-builder' ) ),
				),
			),
			array(
				'key'         => 'fixedBundleDiscountValue',
				'type'        => 'number',
				'label'       => __( 'Bundle Discount', 'funnel-builder' ),
				'tooltip'   => __( "How much is taken off the bundle's total price, as a percentage or a fixed amount.", 'funnel-builder' ),
				'hint'        => '10',
				'prefixField' => 'fixedBundleDiscountType',
				'min'         => 0,
				'width'     => 'half',
				'condition' => $whole,
			),
			array(
				'key'        => 'fixedBundleDiscountType',
				'type'       => 'select',
				'label'      => __( 'Discount type', 'funnel-builder' ),
				'renderedBy' => 'fixedBundleDiscountValue',
				'options'    => self::discount_mode_options(),
			),
		);
	}

	/**
	 * Fixed Bundle.
	 *
	 * @return array
	 */
	private static function fixed_bundle_fields() {
		return array(
			array(
				'key'       => 'fixedBundleItems',
				'type'      => 'repeater',
				'label'     => __( 'Items in the bundle', 'funnel-builder' ),
				'addLabel'  => __( 'Add Product', 'funnel-builder' ),
				'removeLabel' => __( 'Remove product', 'funnel-builder' ),
				'variant'   => 'rows',
				'draggable' => true,
				// A bundle needs one product; the last row cannot be removed.
				'minRows'   => 1,
				'fields'    => self::bundle_item_fields(),
			),
		);
	}

	/**
	 * Frequently Bought Together: the products shown with the viewed one, and their discount.
	 *
	 * @return array
	 */
	private static function fbt_fields() {
		return array(
			array(
				'key'         => 'fbtFallback',
				'type'        => 'radio',
				'label'       => __( 'Which products to show', 'funnel-builder' ),
				'default'     => 'none',
				'options'     => array(
					array(
						'value'       => 'cross_sells',
						'label'       => __( 'Use its cross-sells', 'funnel-builder' ),
					),
					array(
						'value'       => 'cross_sells_upsells',
						'label'       => __( 'Use its cross-sells, then upsells', 'funnel-builder' ),
					),
					// Stored as "none": with nothing listed there is nothing to fall back on.
					array(
						'value'       => 'none',
						'label'       => __( 'Choose products manually', 'funnel-builder' ),
					),
				),
			),
			array(
				'key'          => 'fbtItems',
				'type'         => 'repeater',
				'label'        => __( 'Products shown with it', 'funnel-builder' ),
				'addLabel'     => __( 'Add Product', 'funnel-builder' ),
				'removeLabel'  => __( 'Remove product', 'funnel-builder' ),
				'variant'      => 'rows',
				'draggable'    => true,
				// Only a hand-picked set lists products; the cross-sell choices read them off the product.
				'condition'    => array( 'fbtFallback' => 'none' ),
				'minRows'      => 0,
				// The widget shows no more than this, so the list stops there rather than drop rows silently.
				'maxRows'      => Bundle_Rules::FBT_MAX_COMPANIONS,
				'fields'       => self::fbt_item_fields(),
			),
			array(
				'key'         => 'fbtDiscountScope',
				'type'        => 'radio',
				'label'       => __( 'Discount', 'funnel-builder' ),
				'default'     => 'companions',
				'orientation' => 'horizontal',
				'options'     => array(
					array( 'value' => 'companions', 'label' => __( 'Added items only', 'funnel-builder' ) ),
					array( 'value' => 'set', 'label' => __( 'The whole set', 'funnel-builder' ) ),
					array( 'value' => 'none', 'label' => __( 'No discount', 'funnel-builder' ) ),
				),
			),
			array(
				'key'         => 'fbtDiscountValue',
				'type'        => 'number',
				'label'       => __( 'Discount amount', 'funnel-builder' ),
				'hint'        => '10',
				'prefixField' => 'fbtDiscountType',
				'min'         => 0,
				'width'       => 'half',
				'condition'   => array( 'fbtDiscountScope' => array( 'companions', 'set' ) ),
			),
			array(
				'key'        => 'fbtDiscountType',
				'type'       => 'select',
				'label'      => __( 'Discount type', 'funnel-builder' ),
				'renderedBy' => 'fbtDiscountValue',
				'options'    => self::discount_mode_options(),
			),
			array(
				'key'         => 'fbtMinCompanions',
				'type'        => 'number',
				'label'       => __( 'Minimum items added for discount', 'funnel-builder' ),
				'hint'        => '1',
				'min'         => 1,
				'width'       => 'half',
				'condition'   => array( 'fbtDiscountScope' => array( 'companions', 'set' ) ),
			),
		);
	}

	/**
	 * One product shown with the viewed one.
	 *
	 * @return array
	 */
	public static function fbt_item_fields() {
		return array(
			array( 'key' => 'id', 'type' => 'hidden' ),
			array(
				'key'   => 'product',
				'type'  => 'catalog_select',
				'label' => __( 'Select Product', 'funnel-builder' ),
				'row'   => 'item',
				'width' => 'grow',
			),
			array(
				'key'         => 'price',
				'type'        => 'mirror',
				'label'       => __( 'Price', 'funnel-builder' ),
				'sourceField' => 'product',
				'sourceKey'   => 'price',
				'row'         => 'item',
				'width'       => 'sm',
			),
			// Every companion starts ticked; the merchant has no setting for it.
			array( 'key' => 'checked', 'type' => 'hidden', 'default' => true ),
		);
	}

	/**
	 * One item in a Fixed Bundle.
	 *
	 * @return array
	 */
	public static function bundle_item_fields() {
		return array(
			array( 'key' => 'id', 'type' => 'hidden' ),
			array(
				'key'   => 'product',
				'type'  => 'catalog_select',
				'label' => __( 'Select Product', 'funnel-builder' ),
				'row'   => 'item',
				'width' => 'grow',
			),
			array(
				'key'   => 'quantity',
				'type'  => 'number',
				'label' => __( 'Qty', 'funnel-builder' ),
				'hint'  => '1',
				'min'   => 1,
				'row'   => 'item',
				'width' => 'xs',
			),
			array(
				'key'         => 'discountValue',
				'type'        => 'number',
				'label'       => __( 'Discount', 'funnel-builder' ),
				'hint'        => '10',
				'prefixField' => 'discountType',
				'min'         => 0,
				'row'         => 'item',
				'width'       => 'xl',
				// The scope is the bundle's, not the row's.
				'condition' => array( '/fixedBundleDiscountScope' => 'separate_products' ),
			),
			array(
				'key'        => 'discountType',
				'type'       => 'select',
				'label'      => __( 'Discount type', 'funnel-builder' ),
				'renderedBy' => 'discountValue',
				'default'    => 'percentage',
				'options'    => self::discount_mode_options(),
			),
			array(
				'key'         => 'price',
				'type'        => 'mirror',
				'label'       => __( 'Price', 'funnel-builder' ),
				'sourceField' => 'product',
				'sourceKey'   => 'price',
				'row'         => 'item',
				'width'       => 'sm',
			),
		);
	}

	/**
	 * The bundle design tokens.
	 *
	 * Described so a caller can be told what it may set, and validated against.
	 * The panel that draws them still owns their grouping and presentation.
	 *
	 * @return array
	 */
	public static function bundle_design_fields() {
		$colors = array(
			'typography_headingColor',
			'typography_bundleTitleColor',
			'typography_descriptionColor',
			'typography_regularPriceColor',
			'typography_salePriceColor',
			'typography_priceDescriptionColor',
			'badge_bgColor',
			'badge_textColor',
			'accent_successColor',
			'progress_fillColor',
			'hint_bgColor',
			'hint_textColor',
			'connector_color',
		);

		$sizes = array(
			'layout_radioSize',
			'layout_cornerRadius',
			'layout_selectedBorderWidth',
			'layout_imageRadius',
			'layout_offerImageWidth',
			'layout_itemImageWidth',
			'typography_headingFontSize',
			'typography_bundleTitleFontSize',
			'typography_descriptionFontSize',
			'typography_priceFontSize',
			'typography_priceDescriptionFontSize',
			'badge_fontSize',
			'badge_borderRadius',
			'progress_height',
		);

		$fields = array(
			array( 'key' => 'image_size', 'type' => 'select', 'options' => self::image_size_options() ),
			array( 'key' => 'layout_bgColor', 'type' => 'state-color' ),
			array( 'key' => 'layout_radioColor', 'type' => 'state-color' ),
			array( 'key' => 'layout_borderColor', 'type' => 'state-color' ),
			array( 'key' => 'layout_padding', 'type' => 'dimension' ),
			array( 'key' => 'layout_borderWidth', 'type' => 'number' ),
			array( 'key' => 'layout_borderStyle', 'type' => 'select', 'options' => self::border_style_options() ),
			array( 'key' => 'hint_show', 'type' => 'toggle' ),
			array( 'key' => 'layout_radioShow', 'type' => 'toggle' ),
		);

		foreach ( $colors as $key ) {
			$fields[] = array( 'key' => $key, 'type' => 'color' );
		}
		foreach ( $sizes as $key ) {
			$fields[] = array( 'key' => $key, 'type' => 'number', 'min' => 0 );
		}

		return $fields;
	}

	/**
	 * The site's registered image sizes and the original upload, as options.
	 *
	 * Each carries its dimensions and a badge saying whether WordPress crops it.
	 *
	 * @return array[]
	 */
	public static function image_size_options() {
		$names = array(
			'thumbnail'    => __( 'Thumbnail', 'funnel-builder' ),
			'medium'       => __( 'Medium', 'funnel-builder' ),
			'medium_large' => __( 'Medium Large', 'funnel-builder' ),
			'large'        => __( 'Large', 'funnel-builder' ),
		);
		$auto  = __( 'auto', 'funnel-builder' );
		$out   = array();
		foreach ( wp_get_registered_image_subsizes() as $name => $size ) {
			$width  = (int) ( $size['width'] ?? 0 );
			$height = (int) ( $size['height'] ?? 0 );
			$crop   = ! empty( $size['crop'] );
			$out[]  = array(
				'value'       => (string) $name,
				'label'       => $names[ $name ] ?? ucwords( str_replace( array( '_', '-' ), ' ', (string) $name ) ),
				/* translators: 1: image width in pixels, or "auto". 2: image height in pixels, or "auto". */
				'description' => sprintf( __( '%1$s × %2$s', 'funnel-builder' ), $width ? $width : $auto, $height ? $height : $auto ),
				'badge'       => $crop ? __( 'Cropped', 'funnel-builder' ) : __( 'Full Image', 'funnel-builder' ),
				'badgeTone'   => $crop ? 'default' : 'warning',
			);
		}
		$out[] = array(
			'value'       => 'full',
			'label'       => __( 'Full Size', 'funnel-builder' ),
			'description' => __( 'Original upload', 'funnel-builder' ),
			'badge'       => __( 'Full Image', 'funnel-builder' ),
			'badgeTone'   => 'warning',
		);
		return $out;
	}

	/**
	 * Border styles a design token accepts.
	 *
	 * @return array
	 */
	public static function border_style_options() {
		$out = array();
		foreach ( array( 'none', 'solid', 'dashed', 'dotted' ) as $style ) {
			$out[] = array(
				'value' => $style,
				'label' => $style,
			);
		}
		return $out;
	}

	/**
	 * Bundle types that may target a whole category rather than named products.
	 *
	 * @return int[]
	 */
	private static function category_target_types() {
		/**
		 * Filters the bundle types that may target a whole category.
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $types Type ids; none of this plugin's own.
		 */
		return array_values( array_map( 'intval', (array) apply_filters( 'wffn_bundles_category_target_types', array() ) ) );
	}

	/**
	 * Bundle types as select options; the label comes from the store.
	 *
	 * @return array
	 */
	private static function type_options() {
		$out = array();
		foreach ( Bundle_Store::TYPES as $type ) {
			$out[] = array(
				'value' => (int) $type,
				'label' => Bundle_Store::type_label( $type ),
			);
		}
		return $out;
	}

	/**
	 * Percentage or fixed, shared by every type that discounts.
	 *
	 * @return array
	 */
	public static function discount_mode_options() {
		return array(
			array(
				'value' => 'percentage',
				'label' => __( 'Percentage', 'funnel-builder' ),
				'short' => '%',
			),
			array(
				'value' => 'fixed',
				'label' => __( 'Fixed amount', 'funnel-builder' ),
				'short' => html_entity_decode( get_woocommerce_currency_symbol() ),
			),
		);
	}
}
