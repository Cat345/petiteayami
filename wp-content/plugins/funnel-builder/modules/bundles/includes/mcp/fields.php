<?php
/**
 * The settings a bundle and the store hold, as the abilities describe and check them.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Mcp;

defined( 'ABSPATH' ) || exit;

use WFFN\Bundles\Includes\Rest\Settings_Controller;
use WFFN\Bundles\Includes\Skins\Registry;
use WFFN\Bundles\Includes\Storage\Bundle_Schema;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Settings_Schema;

/**
 * Reads the editor's own field schema (Bundle_Schema) and the settings
 * screen's (Settings_Schema), so an agent learns which key is which setting
 * from the screens a merchant sees, and every value it writes is checked
 * against the control that would have sent it: one of a select's options, a
 * number within its limits, true or false for a toggle.
 *
 * The editor names a few fields differently from where they are stored;
 * every other field is a `discountConfig` key of the same name.
 */
class Fields {

	/** Editor field => stored path, for the fields not kept in discountConfig. */
	const FORM_PATHS = array(
		'bundleName'       => 'name',
		'productMode'        => 'productSelection.mode',
		'products'           => 'productSelection.products',
		'selectedCategories' => 'productSelection.selectedCategories',
		'excludeEnabled'     => 'productSelection.excludeEnabled',
		'excludedProducts'   => 'productSelection.excludedProducts',
		'excludedCategories' => 'productSelection.excludedCategories',
		'showToUser'         => 'targeting.userRoles',
		'alwaysAvailable'    => 'schedule.alwaysAvailable',
		'startDate'          => 'schedule.startDate',
		'endDate'            => 'schedule.endDate',
	);

	/** Fields another ability owns: the type is fixed at create, status has its own tool. */
	const OWNED_ELSEWHERE = array( 'discountType', 'status' );

	/** Controls that show something but hold no value. */
	const VALUELESS = array( 'hidden', 'mirror', 'note', 'html', 'pro', 'bundle_type', 'mix_match_summary', 'field_promo' );

	/** Controls that take exactly one of their options. */
	const CHOICES = array( 'select', 'radio', 'row_radio', 'option_cards', 'row_select', 'image_size' );

	/** Controls that take true or false. */
	const SWITCHES = array( 'toggle', 'checkbox' );

	/** Stored keys the editor keeps without a control of their own, and the control their value follows. */
	const UNDRAWN = array( 'designBrandColor' => 'color' );

	/**
	 * Where a design token is drawn, which its label does not say: the heading
	 * sits on the page, the cards' colours paint the cards alone.
	 */
	const TOKEN_HINTS = array(
		'layout_bgColor'          => 'Paints the option cards only. The heading and subheading sit above them, on the page\'s own background.',
		'typography_headingColor' => 'Colours the heading and the subheading, which sit above the cards on the page\'s own background (usually white), not on layout_bgColor: pick a colour that reads there.',
	);

	/** The design token sets a bundle stores, each a design_panel in the editor. */
	const DESIGN_SETS = array( 'bundleDesign', 'upsellDesign', 'giftDesign' );

	/** Per-request cache of each type's sections. */
	private static $sections = array();

	/**
	 * Every setting a bundle type has, by editor section.
	 *
	 * @param int        $type   Bundle type id.
	 * @param array|null $config A stored bundle, to report each field's current value.
	 * @return array[] { tab, section, label, description, fields[] }
	 */
	public static function sections( $type, $config = null ) {
		$type = (int) $type;
		if ( ! isset( self::$sections[ $type ] ) ) {
			self::$sections[ $type ] = self::build( $type );
		}
		if ( ! is_array( $config ) ) {
			return self::$sections[ $type ];
		}

		$out = self::$sections[ $type ];
		foreach ( $out as &$section ) {
			foreach ( $section['fields'] as &$field ) {
				$field['value'] = self::read_path( $config, $field['path'] );
			}
			unset( $field );
		}
		unset( $section );
		return $out;
	}

	/**
	 * Every field a bundle type has, keyed by stored path.
	 *
	 * @param int $type Bundle type id.
	 * @return array<string,array>
	 */
	public static function defs( $type ) {
		$defs = array();
		foreach ( self::sections( $type ) as $section ) {
			foreach ( $section['fields'] as $field ) {
				$defs[ $field['path'] ] = $field;
			}
		}
		return $defs;
	}

	/**
	 * Check a payload's values against the controls of its bundle type, the way
	 * the editor would have sent them. Values a control coerces (a "true"
	 * string for a toggle) come back coerced.
	 *
	 * @param array $payload Normalised payload or patch, stored keys.
	 * @param int   $type    Bundle type id.
	 * @return array{0:array,1:array} [ coerced payload, issues {path, message} ]
	 */
	public static function check( array $payload, $type ) {
		$issues = array();
		if ( ! isset( $payload['discountConfig'] ) || ! is_array( $payload['discountConfig'] ) ) {
			return array( $payload, $issues );
		}

		$defs  = self::defs( $type );
		$keys  = Bundle_Store::config_keys( (int) $type );
		$label = Bundle_Store::type_label( (int) $type );

		foreach ( $payload['discountConfig'] as $key => $value ) {
			$path = 'discountConfig.' . $key;
			// config_keys() is the whitelist every save prunes against: a key outside it would vanish silently.
			if ( is_array( $keys ) && ! in_array( (string) $key, $keys, true ) ) {
				$issues[] = self::issue( $path, sprintf( '%s is not a setting of this bundle type (%s), so it would not be saved. get-bundle-fields lists the settings it has.', $key, $label ) );
				continue;
			}
			// A token set has no field of its own in the editor, only one per token.
			if ( in_array( (string) $key, self::DESIGN_SETS, true ) && is_array( $value ) ) {
				foreach ( $value as $token => $token_value ) {
					$sub = $defs[ $path . '.' . $token ] ?? null;
					if ( null !== $sub ) {
						$checked = self::coerce( $path . '.' . $token, $token_value, $sub );
						if ( is_wp_error( $checked ) ) {
							$issues[] = self::issue( $path . '.' . $token, $checked->get_error_message() );
						} else {
							$payload['discountConfig'][ $key ][ $token ] = $checked;
						}
					}
				}
				continue;
			}

			$def = $defs[ $path ] ?? ( isset( self::UNDRAWN[ $key ] ) ? array( 'type' => self::UNDRAWN[ $key ] ) : null );
			if ( null === $def ) {
				continue;
			}

			if ( ! empty( $def['row_fields'] ) && is_array( $value ) ) {
				foreach ( array_values( $value ) as $i => $row ) {
					list( $row, $row_issues ) = self::check_row( is_array( $row ) ? $row : array(), $def, $path . '.' . $i );
					$issues                   = array_merge( $issues, $row_issues );
					if ( is_array( $payload['discountConfig'][ $key ][ $i ] ?? null ) ) {
						$payload['discountConfig'][ $key ][ $i ] = $row;
					}
				}
				continue;
			}

			$checked = self::coerce( $path, $value, $def );
			if ( is_wp_error( $checked ) ) {
				$issues[] = self::issue( $path, $checked->get_error_message() );
			} else {
				$payload['discountConfig'][ $key ] = $checked;
			}
		}

		return array( $payload, $issues );
	}

	/**
	 * Check one row of a list against the list's row controls.
	 *
	 * @param array  $row   The row.
	 * @param array  $def   The list's field, from defs().
	 * @param string $path  Where the row sits, for the issue.
	 * @param bool   $whole False for a patch of an existing row, which may leave its product out.
	 * @return array{0:array,1:array} [ coerced row, issues ]
	 */
	public static function check_row( array $row, array $def, $path, $whole = true ) {
		$issues = array();
		$fields = array();
		foreach ( (array) ( $def['row_fields'] ?? array() ) as $field ) {
			$fields[ $field['key'] ] = $field;
		}
		// The store keeps a row with no product, and mints a row key over a numeric id, so the product is lost unseen.
		if ( $whole && isset( $fields['product'] ) && empty( $row['product'] ) ) {
			$hint     = isset( $row['id'] ) && is_numeric( $row['id'] ) ? sprintf( ' id is the row\'s own key, not the product; for product %1$d send {"product":{"id":"%1$d"}}.', absint( $row['id'] ) ) : ' Send each row as {"product":{"id":"18"}}, with the product id from search-products.';
			$issues[] = self::issue( $path . '.product', sprintf( '%s has no product.%s', $path, $hint ) );
		}
		foreach ( $row as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			$checked = self::coerce( $path . '.' . $key, $value, $fields[ $key ] );
			if ( is_wp_error( $checked ) ) {
				$issues[] = self::issue( $path . '.' . $key, $checked->get_error_message() );
			} else {
				$row[ $key ] = $checked;
			}
		}
		return array( $row, $issues );
	}

	/**
	 * The store-wide settings, as the settings screen draws them.
	 *
	 * @return array[] { group_key, group, settings[] }
	 */
	public static function settings() {
		$values = Settings_Controller::read();
		$groups = array();
		foreach ( (array) ( Settings_Schema::get()['tabs'] ?? array() ) as $tab ) {
			foreach ( (array) ( $tab['sections'] ?? array() ) as $slug => $section ) {
				$items = array();
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					$key = (string) ( $field['key'] ?? '' );
					if ( '' === $key || in_array( (string) ( $field['type'] ?? '' ), Settings_Schema::VALUELESS_TYPES, true ) || ! array_key_exists( $key, $values ) ) {
						continue;
					}
					$row          = self::shape( $field, $key );
					$row['value'] = $values[ $key ];
					unset( $row['path'] );
					$items[] = $row;
				}
				if ( $items ) {
					$groups[] = array(
						'group_key' => (string) $slug,
						'group'     => self::plain( $section['label'] ?? $slug ),
						'settings'  => $items,
					);
				}
			}
		}
		return $groups;
	}

	/**
	 * Check a settings patch against the settings screen's controls.
	 *
	 * A key an add-on registers without a control on that screen is passed
	 * through for the add-on's own sanitizer.
	 *
	 * @param array $patch Setting key => value.
	 * @return array|\WP_Error The coerced patch.
	 */
	public static function check_settings( array $patch ) {
		$defs = array();
		foreach ( self::settings() as $group ) {
			foreach ( $group['settings'] as $setting ) {
				$defs[ $setting['key'] ] = $setting;
			}
		}
		$known = array_keys( Settings_Controller::read() );

		foreach ( $patch as $key => $value ) {
			if ( ! isset( $defs[ $key ] ) ) {
				if ( ! in_array( (string) $key, $known, true ) ) {
					return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s is not a Bundles setting. get-bundle-settings lists them: %s.', $key, implode( ', ', array_keys( $defs ) ) ) );
				}
				continue;
			}
			$checked = self::coerce( $key, $value, $defs[ $key ] );
			if ( is_wp_error( $checked ) ) {
				return $checked;
			}
			$patch[ $key ] = $checked;
		}
		return $patch;
	}

	/**
	 * Flatten the editor schema for one bundle type.
	 *
	 * @param int $type Bundle type id.
	 * @return array[]
	 */
	private static function build( $type ) {
		$schema  = Bundle_Schema::get();
		$keys    = Bundle_Store::config_keys( $type );
		$layouts = array();
		foreach ( (array) ( Registry::types()[ $type ]['layouts'] ?? array() ) as $id => $layout ) {
			$layouts[] = array(
				'value' => (string) $id,
				'label' => self::plain( $layout['label'] ?? $id ),
			);
		}

		$out = array();
		foreach ( (array) ( $schema['tabs'] ?? array() ) as $tab_key => $tab ) {
			foreach ( (array) ( $tab['sections'] ?? array() ) as $slug => $section ) {
				if ( ! self::for_type( $section['condition'] ?? array(), $type ) ) {
					continue;
				}
				// A section shown only while a setting holds a value passes that on to its fields.
				$section_when = self::when( (array) ( $section['condition'] ?? array() ) );
				$fields       = array();
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					foreach ( self::expand( $field, $type, $keys, $layouts ) as $row ) {
						if ( $section_when ) {
							$row['applies_when'] = array_merge( $section_when, (array) ( $row['applies_when'] ?? array() ) );
						}
						$fields[] = $row;
					}
				}
				if ( $fields ) {
					$out[] = array(
						'tab'         => (string) $tab_key,
						'section'     => (string) $slug,
						'label'       => self::plain( $section['label'] ?? $slug ),
						'description' => self::plain( $section['description'] ?? '' ),
						'fields'      => $fields,
					);
				}
			}
		}
		return self::drop_orphans( $out );
	}

	/**
	 * Drop the fields that depend on a setting this type does not have, such as
	 * the upsell design on a type with no upsells.
	 *
	 * @param array[] $sections Sections from build().
	 * @return array[]
	 */
	private static function drop_orphans( array $sections ) {
		$paths = array();
		foreach ( $sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				$paths[ $field['path'] ] = true;
			}
		}
		$out = array();
		foreach ( $sections as $section ) {
			$section['fields'] = array_values(
				array_filter(
					$section['fields'],
					static function ( $field ) use ( $paths ) {
						$when = (array) ( $field['applies_when'] ?? array() );
						$any  = (array) ( $when['any_of'] ?? array() );
						unset( $when['any_of'] );
						foreach ( array_keys( $when ) as $on ) {
							if ( ! isset( $paths[ $on ] ) ) {
								return false;
							}
						}
						return ! $any || array_intersect_key( $any, $paths );
					}
				)
			);
			if ( $section['fields'] ) {
				$out[] = $section;
			}
		}
		return $out;
	}

	/**
	 * One editor field as the settings it stands for: none for a display-only
	 * control or another type's key, one for most, one per token for a design panel.
	 *
	 * @param array      $field   Editor field.
	 * @param int        $type    Bundle type id.
	 * @param array|null $keys    The type's discountConfig keys, or null for any.
	 * @param array      $layouts The type's layouts, as options.
	 * @return array[]
	 */
	private static function expand( array $field, $type, $keys, array $layouts ) {
		$key     = (string) ( $field['key'] ?? '' );
		$control = (string) ( $field['type'] ?? '' );
		if ( '' === $key || in_array( $control, self::VALUELESS, true ) || in_array( $key, self::OWNED_ELSEWHERE, true ) || ! self::for_type( $field['condition'] ?? array(), $type ) ) {
			return array();
		}
		// A design token is named by its path inside its token set: bundleDesign.layout_bgColor.
		$stored = ! isset( self::FORM_PATHS[ $key ] );
		if ( $stored && is_array( $keys ) && ! in_array( strtok( $key, '.' ), $keys, true ) ) {
			return array();
		}

		$row = self::shape( $field, $stored ? 'discountConfig.' . $key : self::FORM_PATHS[ $key ] );

		if ( 'skin_picker' === $control || 'bundleLayout' === $key ) {
			$row['options'] = ! empty( $row['options'] ) ? $row['options'] : $layouts;
		}
		if ( 'design_theme_panel' === $control ) {
			$row['hint'] = 'The saved design preset applied; set it with apply-bundle-design-preset, not by hand.';
		}
		if ( 'limit' === $control && ! empty( $field['valueKey'] ) ) {
			$row['hint'] = trim( $row['hint'] . ' The number itself is discountConfig.' . $field['valueKey'] . '.' );
		}

		if ( 'design_panel' === $control ) {
			$rows = array();
			foreach ( (array) ( $field['fields'] ?? array() ) as $token ) {
				if ( empty( $token['key'] ) || in_array( (string) ( $token['type'] ?? '' ), self::VALUELESS, true ) ) {
					continue;
				}
				$sub          = self::shape( $token, $row['path'] . '.' . $token['key'] );
				$sub['label'] = trim( $row['label'] . ': ' . ( '' !== $sub['label'] ? $sub['label'] : $token['key'] ) );
				$rows[]       = $sub;
			}
			return $rows;
		}

		if ( 'repeater' === $control ) {
			$row['type']       = 'list';
			$row['row_fields'] = array();
			foreach ( (array) ( $field['fields'] ?? array() ) as $sub ) {
				if ( empty( $sub['key'] ) || in_array( (string) ( $sub['type'] ?? '' ), self::VALUELESS, true ) ) {
					continue;
				}
				$shaped = self::shape( $sub, (string) $sub['key'] );
				unset( $shaped['path'] );
				$row['row_fields'][] = $shaped;
			}
			if ( isset( Bundle_Store::id_lists()[ $key ] ) ) {
				$row['hint'] = trim( $row['hint'] . ' Change one row with edit-bundle-rows.' );
			}
		}

		return array( $row );
	}

	/**
	 * A field's description for an agent: what it is called, takes and needs.
	 *
	 * @param array  $field Editor or settings field.
	 * @param string $path  Where its value is stored.
	 * @return array
	 */
	private static function shape( array $field, $path ) {
		$row = array(
			'key'   => (string) $field['key'],
			'path'  => $path,
			'label' => self::plain( $field['label'] ?? ( $field['groupLabel'] ?? '' ) ),
			'type'  => (string) ( $field['type'] ?? '' ),
			'hint'  => self::plain( $field['tooltip'] ?? ( $field['description'] ?? ( $field['help'] ?? '' ) ) ),
		);

		$options = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( is_array( $option ) && array_key_exists( 'value', $option ) ) {
				$options[] = array(
					'value' => $option['value'],
					'label' => self::plain( $option['label'] ?? $option['value'] ),
				);
			}
		}
		if ( $options ) {
			$row['options'] = $options;
		}
		foreach ( array( 'default', 'min', 'max' ) as $attr ) {
			if ( array_key_exists( $attr, $field ) && is_scalar( $field[ $attr ] ) ) {
				$row[ $attr ] = $field[ $attr ];
			}
		}

		$token = substr( (string) strrchr( '.' . $row['key'], '.' ), 1 );
		if ( isset( self::TOKEN_HINTS[ $token ] ) ) {
			$row['hint'] = trim( $row['hint'] . ' ' . self::TOKEN_HINTS[ $token ] );
		}

		$when = self::when( (array) ( $field['condition'] ?? array() ) );
		if ( $when ) {
			$row['applies_when'] = $when;
		}

		return $row;
	}

	/**
	 * A schema condition as stored paths; `_any` (any one of them holds) and `_all` stay groups.
	 *
	 * @param array $condition Schema condition.
	 * @return array
	 */
	private static function when( array $condition ) {
		$when = array();
		foreach ( $condition as $on => $value ) {
			$on = ltrim( (string) $on, '/' );
			if ( '_any' === $on && is_array( $value ) ) {
				$when['any_of'] = self::when( $value );
			} elseif ( '_all' === $on && is_array( $value ) ) {
				$when['all_of'] = self::when( $value );
			} elseif ( 'discountType' !== $on ) {
				$when[ self::FORM_PATHS[ $on ] ?? 'discountConfig.' . $on ] = $value;
			}
		}
		return $when;
	}

	/**
	 * One value the way its control would submit it.
	 *
	 * @param string $path  Where the value goes, for the message.
	 * @param mixed  $value Incoming value.
	 * @param array  $def   The field, from shape().
	 * @return mixed|\WP_Error
	 */
	private static function coerce( $path, $value, array $def ) {
		$control = (string) ( $def['type'] ?? '' );

		if ( in_array( $control, self::SWITCHES, true ) ) {
			if ( in_array( $value, array( true, 1, '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $value, array( false, 0, '0', 'false', 'no', 'off', '' ), true ) ) {
				return false;
			}
			return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s is a switch: pass true or false.', $path ) );
		}

		if ( in_array( $control, self::CHOICES, true ) && ! empty( $def['options'] ) ) {
			foreach ( $def['options'] as $option ) {
				if ( is_scalar( $value ) && (string) $option['value'] === (string) $value ) {
					return $option['value'];
				}
				if ( is_bool( $value ) && ( $value ? 'true' : 'false' ) === (string) $option['value'] ) {
					return $option['value'];
				}
			}
			return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be one of: %s.', $path, implode( ', ', array_map( 'strval', array_column( $def['options'], 'value' ) ) ) ) );
		}

		if ( 'number' === $control && '' !== $value && null !== $value ) {
			if ( ! is_numeric( $value ) ) {
				return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be a number.', $path ) );
			}
			if ( isset( $def['min'] ) && is_numeric( $def['min'] ) && $value < $def['min'] ) {
				return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be at least %s.', $path, $def['min'] ) );
			}
			if ( isset( $def['max'] ) && is_numeric( $def['max'] ) && $value > $def['max'] ) {
				return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be at most %s.', $path, $def['max'] ) );
			}
			return 0 + $value;
		}

		if ( 'color' === $control && '' !== $value && null !== $value && ! self::is_hex( $value ) ) {
			return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be a hex colour like #1a2b3c.', $path ) );
		}

		// The skin reads these as objects and skips any other shape, so a lone value would save and never show.
		if ( 'state-color' === $control ) {
			return self::state_color( $path, $value );
		}
		if ( 'dimension' === $control ) {
			return self::dimension( $path, $value );
		}

		return $value;
	}

	/**
	 * Whether a value is a hex colour.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_hex( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value );
	}

	/**
	 * A colour with a selected state, as {default, active}: one hex sets both.
	 *
	 * @param string $path  Where the value goes, for the message.
	 * @param mixed  $value Incoming value.
	 * @return array|\WP_Error
	 */
	private static function state_color( $path, $value ) {
		if ( self::is_hex( $value ) ) {
			return array(
				'default' => $value,
				'active'  => $value,
			);
		}
		// "selected" is what the editor calls the active state; any other key would be dropped on save without a word.
		if ( is_array( $value ) && isset( $value['selected'] ) && ! isset( $value['active'] ) ) {
			$value['active'] = $value['selected'];
			unset( $value['selected'] );
		}
		if ( is_array( $value ) && ! array_diff( array_keys( $value ), array( 'default', 'active' ) ) && self::is_hex( $value['default'] ?? null ) && ( ! isset( $value['active'] ) || self::is_hex( $value['active'] ) ) ) {
			return array(
				'default' => $value['default'],
				'active'  => $value['active'] ?? $value['default'],
			);
		}
		return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s takes {"default": hex, "active": hex} (the selected state), or one hex colour for both.', $path ) );
	}

	/**
	 * Spacing as {top, right, bottom, left} in px: a number or CSS shorthand ("12px 20px") is expanded.
	 *
	 * @param string $path  Where the value goes, for the message.
	 * @param mixed  $value Incoming value.
	 * @return array|\WP_Error
	 */
	private static function dimension( $path, $value ) {
		$sides = array( 'top', 'right', 'bottom', 'left' );
		if ( is_array( $value ) && array_intersect_key( $value, array_flip( $sides ) ) ) {
			$out = array();
			foreach ( $sides as $side ) {
				$n = $value[ $side ] ?? 0;
				if ( ! is_numeric( $n ) || $n < 0 ) {
					return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s.%s must be a number of px, 0 or more.', $path, $side ) );
				}
				$out[ $side ] = 0 + $n;
			}
			return $out;
		}
		$parts = is_numeric( $value ) ? array( $value ) : ( is_string( $value ) ? preg_split( '/\s+/', trim( $value ) ) : array() );
		$nums  = array();
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^(\d+(?:\.\d+)?)(px)?$/', (string) $part, $m ) ) {
				$nums = array();
				break;
			}
			$nums[] = 0 + $m[1];
		}
		if ( ! $nums || count( $nums ) > 4 ) {
			return new \WP_Error( 'fkmcp_invalid_input', sprintf( '%s takes {"top", "right", "bottom", "left"} in px, or CSS shorthand like "12px 20px".', $path ) );
		}
		// CSS shorthand order: one, two, three or four values.
		$top    = $nums[0];
		$right  = $nums[1] ?? $top;
		$bottom = $nums[2] ?? $top;
		$left   = $nums[3] ?? $right;
		return array_combine( $sides, array( $top, $right, $bottom, $left ) );
	}

	/**
	 * Whether a schema condition lets a section or field show for a bundle type.
	 * An empty list of types means every type.
	 *
	 * @param mixed $condition Schema condition.
	 * @param int   $type      Bundle type id.
	 * @return bool
	 */
	private static function for_type( $condition, $type ) {
		if ( ! is_array( $condition ) || ! array_key_exists( 'discountType', $condition ) ) {
			return true;
		}
		$want = $condition['discountType'];
		if ( is_array( $want ) ) {
			return empty( $want ) || in_array( (int) $type, array_map( 'intval', $want ), true );
		}
		return (int) $type === (int) $want;
	}

	/**
	 * A dotted path's value in a stored bundle.
	 *
	 * @param array  $config Stored bundle.
	 * @param string $path   Dotted path.
	 * @return mixed
	 */
	private static function read_path( array $config, $path ) {
		$node = $config;
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $node ) || ! array_key_exists( $part, $node ) ) {
				return null;
			}
			$node = $node[ $part ];
		}
		return $node;
	}

	/**
	 * Schema text as plain text.
	 *
	 * @param mixed $text Label, tooltip or description.
	 * @return string
	 */
	private static function plain( $text ) {
		return is_scalar( $text ) ? trim( wp_specialchars_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES ) ) : '';
	}

	/**
	 * One issue, in validate-bundle's shape.
	 *
	 * @param string $path    Where.
	 * @param string $message What.
	 * @return array
	 */
	private static function issue( $path, $message ) {
		return array(
			'path'    => $path,
			'message' => $message,
		);
	}
}
