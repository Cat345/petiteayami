<?php

defined( 'ABSPATH' ) || exit;

/**
 * The classic widgets on a Funnel Step - FunnelKit's funnel widgets (checkout
 * form, optin form, order details, upsell offer controls) and any classic
 * Elementor widget a page kept - which Elementor's own tools cannot edit.
 *
 * Lists them with the settings they accept, merges checked settings into one in
 * place (the user's draft of a published Step, as Elementor's tools write), and
 * points their brand colours at a global the site has. Site-wide styles are
 * used, never changed.
 *
 * Loaded by FKMCP_Pack_Elementor_Design when one of its funnel widget tools runs.
 */
class FKMCP_Funnel_Widgets {

	/** Control types that hold no value, and value types too open to set from here. */
	const UI_CONTROLS = array( 'section', 'tab', 'tabs', 'heading', 'raw_html', 'divider', 'deprecated_notice', 'alert', 'notice', 'button', 'hidden' );

	const REFUSED_CONTROLS = array( 'code' );

	const REFUSED_SETTINGS = array( 'custom_css', '_attributes', '_element_custom_attributes' );

	/**
	 * get-funnel-widgets: a Step's classic widgets, their settings as set, and
	 * with search the matching settings they accept.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function get( $input ) {
		$input = (array) $input;
		$step  = FKMCP_Pack_Elementor_Design::resolve_step( $input );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		self::widget_context( $step );

		$only    = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';
		$words   = array_filter( preg_split( '/\s+/', strtolower( trim( (string) ( $input['search'] ?? '' ) ) ) ) );
		$widgets = array();

		foreach ( self::classic_widgets( FKMCP_Pack_Elementor_Design::elements( FKMCP_Pack_Elementor_Design::working_post_id( $step['id'] ) ) ) as $node ) {
			if ( '' !== $only && $only !== $node['id'] ) {
				continue;
			}

			$controls = self::widget_controls( $node['widgetType'] );
			$entry    = array(
				'element_id'     => (string) $node['id'],
				'widget_type'    => (string) $node['widgetType'],
				'title'          => self::widget_title( $node['widgetType'] ),
				'settings'       => (object) ( is_array( $node['settings'] ?? null ) ? $node['settings'] : array() ),
				'settings_count' => count( $controls ),
			);

			if ( $words ) {
				$matches = array();

				foreach ( $controls as $name => $control ) {
					$hay = strtolower( $name . ' ' . ( $control['label'] ?? '' ) );

					if ( count( array_filter( $words, static fn( $w ) => false !== strpos( $hay, $w ) ) ) === count( $words ) ) {
						$matches[] = self::describe_control( $name, $control );
					}
				}

				$entry['controls'] = array_slice( $matches, 0, 60 );
				$entry['more']     = max( 0, count( $matches ) - 60 );
			}

			$widgets[] = $entry;
		}

		if ( '' !== $only && ! $widgets ) {
			return new WP_Error( 'fkmcp_widget_not_found', sprintf( 'Step %1$d has no classic widget %2$s: list them with funnelkit/get-funnel-widgets.', $step['id'], $only ) );
		}

		return array(
			'step_id' => $step['id'],
			'widgets' => $widgets,
		);
	}

	/**
	 * update-funnel-widget: merges checked settings into one classic widget in
	 * place, in the user's draft of a published Step.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function update( $input ) {
		$input = (array) $input;
		$step  = FKMCP_Pack_Elementor_Design::resolve_step( $input );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $step['id'], false );

		if ( ! $document || ! $document->is_editable_by_current_user() ) {
			return new WP_Error( 'fkmcp_step_not_editable', sprintf( 'You cannot edit Step %d with Elementor.', $step['id'] ) );
		}

		$locked = FKMCP_Pack_Elementor_Design::lock_error( $step['id'] );

		if ( $locked ) {
			return $locked;
		}

		// A Step moved off Elementor: saving here would bring its old Elementor design back.
		$not_elementor = FKMCP_Pack_Elementor_Design::not_elementor_error( $step );

		if ( $not_elementor ) {
			return $not_elementor;
		}

		self::widget_context( $step );

		$settings = isset( $input['settings'] ) ? (array) $input['settings'] : array();
		$globals  = isset( $input['globals'] ) ? (array) $input['globals'] : array();

		if ( ! $settings && ! $globals ) {
			return new WP_Error( 'fkmcp_invalid_input', 'Name at least one setting (settings) or global (globals) to change. Nothing was changed.' );
		}

		$elements = FKMCP_Pack_Elementor_Design::elements( FKMCP_Pack_Elementor_Design::working_post_id( $step['id'] ) );
		$path     = self::element_path( $elements, (string) $input['element_id'] );
		$found    = null;

		if ( null !== $path ) {
			$found = &$elements;
			foreach ( $path as $i => $index ) {
				$found = &$found[ $index ];
				if ( $i < count( $path ) - 1 ) {
					$found = &$found['elements'];
				}
			}
		}

		if ( null === $found || 'widget' !== ( $found['elType'] ?? '' ) || 0 === strpos( (string) ( $found['widgetType'] ?? '' ), 'e-' ) ) {
			return new WP_Error( 'fkmcp_widget_not_found', sprintf( 'Step %1$d has no classic widget %2$s: list them with funnelkit/get-funnel-widgets (atomic elements are edited with elementor/manage-elements). Nothing was changed.', $step['id'], (string) $input['element_id'] ) );
		}

		$controls = self::widget_controls( $found['widgetType'] );
		$current  = is_array( $found['settings'] ?? null ) ? $found['settings'] : array();
		$changed  = array();
		$removed  = array();
		$errors   = array();

		foreach ( $settings as $name => $value ) {
			$name = (string) $name;

			if ( ! isset( $controls[ $name ] ) || in_array( $name, self::REFUSED_SETTINGS, true ) ) {
				$errors[] = sprintf( '%s is not a setting of %s', $name, $found['widgetType'] );
				continue;
			}

			if ( null === $value ) {
				unset( $current[ $name ] );
				$removed[] = $name;
				continue;
			}

			$clean = self::clean_setting( $controls[ $name ], $value );

			if ( is_wp_error( $clean ) ) {
				$errors[] = $name . ': ' . $clean->get_error_message();
				continue;
			}

			$current[ $name ] = $clean;
			$changed[]        = $name;

			// Elementor renders a global link over the value: a value set here unlinks it.
			if ( isset( $current['__globals__'][ $name ] ) ) {
				unset( $current['__globals__'][ $name ] );
				$removed[] = '__globals__.' . $name;
			}
		}

		foreach ( $globals as $name => $id ) {
			$name    = (string) $name;
			$control = $controls[ $name ] ?? null;
			$kind    = $control && 'color' === ( $control['type'] ?? '' ) ? 'colors' : ( $control && 'popover_toggle' === ( $control['type'] ?? '' ) ? 'typography' : '' );

			if ( '' === $kind ) {
				$errors[] = sprintf( '%s is not a colour or typography setting of %s', $name, $found['widgetType'] );
				continue;
			}

			if ( '' === (string) $id ) {
				unset( $current['__globals__'][ $name ] );
				$removed[] = '__globals__.' . $name;
				continue;
			}

			if ( ! in_array( (string) $id, self::kit_global_ids( $kind ), true ) ) {
				$errors[] = sprintf( 'the site has no global %1$s %2$s (it has: %3$s)', 'colors' === $kind ? 'colour' : 'font', (string) $id, implode( ', ', array_slice( self::kit_global_ids( $kind ), 0, 12 ) ) );
				continue;
			}

			$current['__globals__']          = isset( $current['__globals__'] ) && is_array( $current['__globals__'] ) ? $current['__globals__'] : array();
			$current['__globals__'][ $name ] = 'globals/' . $kind . '?id=' . (string) $id;
			$changed[]                       = '__globals__.' . $name;
		}

		if ( $errors ) {
			return new WP_Error( 'fkmcp_invalid_input', implode( '; ', array_slice( $errors, 0, 6 ) ) . '. Check the names and values with funnelkit/get-funnel-widgets (search). Nothing was changed.' );
		}

		FKMCP_Pack_Elementor_Design::save_revision( $step['id'], FKMCP_Pack_Elementor_Design::BEFORE_AI );

		$found['settings'] = $current;
		unset( $found );
		$target = FKMCP_Pack_Elementor_Design::save_design( $step['id'], $elements );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$unpublished = $target !== $step['id'];

		if ( ! $unpublished ) {
			FKMCP_Pack_Elementor_Design::save_revision( $step['id'], FKMCP_Pack_Elementor_Design::AI_RESULT );
		}

		return array(
			'step_id'     => $step['id'],
			'element_id'  => (string) $input['element_id'],
			'widget_type' => (string) self::widget_type_of( $elements, (string) $input['element_id'] ),
			'changed'     => $changed,
			'removed'     => $removed,
			'unpublished' => $unpublished,
			'next'        => $unpublished ? 'Publish with edit-design (tool elementor/publish-document, input post_id ' . $step['id'] . '), then open the Step and look at it.' : 'Open the Step and look at it.',
		);
	}

	/** Containers a classic widget can be moved into. */
	const WIDGET_PARENTS = array( 'e-flexbox', 'e-div-block', 'container', 'column' );

	/**
	 * move-funnel-widget: moves one classic widget, settings and all, into another
	 * container - an atomic flexbox the Elementor tools built, or a classic one -
	 * and removes the classic containers it leaves empty.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function move( $input ) {
		$input = (array) $input;
		$step  = FKMCP_Pack_Elementor_Design::resolve_step( $input );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $step['id'], false );

		if ( ! $document || ! $document->is_editable_by_current_user() ) {
			return new WP_Error( 'fkmcp_step_not_editable', sprintf( 'You cannot edit Step %d with Elementor.', $step['id'] ) );
		}

		$locked = FKMCP_Pack_Elementor_Design::lock_error( $step['id'] );

		if ( $locked ) {
			return $locked;
		}

		// A Step moved off Elementor: saving here would bring its old Elementor design back.
		$not_elementor = FKMCP_Pack_Elementor_Design::not_elementor_error( $step );

		if ( $not_elementor ) {
			return $not_elementor;
		}

		$widget_id = (string) $input['element_id'];
		$parent_id = (string) $input['new_parent_id'];
		$elements  = FKMCP_Pack_Elementor_Design::elements( FKMCP_Pack_Elementor_Design::working_post_id( $step['id'] ) );
		$path      = self::element_path( $elements, $widget_id );
		$node      = null === $path ? null : self::node_at( $elements, $path );

		if ( null === $node || 'widget' !== ( $node['elType'] ?? '' ) || 0 === strpos( (string) ( $node['widgetType'] ?? '' ), 'e-' ) ) {
			return new WP_Error( 'fkmcp_widget_not_found', sprintf( 'Step %1$d has no classic widget %2$s: list them with funnelkit/get-funnel-widgets (atomic elements are moved with elementor/manage-elements). Nothing was changed.', $step['id'], $widget_id ) );
		}

		$parent_path = self::element_path( $elements, $parent_id );
		$parent      = null === $parent_path ? null : self::node_at( $elements, $parent_path );

		if ( null === $parent || ! in_array( (string) ( $parent['elType'] ?? '' ), self::WIDGET_PARENTS, true ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'new_parent_id %1$s is not a container on Step %2$d: give the id of an atomic flexbox or div block (from elementor/build-composition or elementor/get-page-structure), or of a classic container or column. Nothing was changed.', $parent_id, $step['id'] ) );
		}

		// The new container and everything around it stay, even when emptied.
		$keep = array();
		for ( $i = 1; $i <= count( $parent_path ); $i++ ) {
			$keep[] = (string) ( self::node_at( $elements, array_slice( $parent_path, 0, $i ) )['id'] ?? '' );
		}

		FKMCP_Pack_Elementor_Design::save_revision( $step['id'], FKMCP_Pack_Elementor_Design::BEFORE_AI );

		// Out of its place; then up the tree, the classic containers it emptied.
		$list = &self::list_at( $elements, $path );
		array_splice( $list, (int) end( $path ), 1 );
		unset( $list );
		$removed = self::prune_empty( $elements, array_slice( $path, 0, -1 ), $keep );

		// Into the new container, found again: removing shifted the positions.
		$parent_path      = self::element_path( $elements, $parent_id );
		$list             = &self::list_at( $elements, $parent_path );
		$host             = &$list[ (int) end( $parent_path ) ];
		$host['elements'] = isset( $host['elements'] ) && is_array( $host['elements'] ) ? $host['elements'] : array();
		$index            = isset( $input['index'] ) ? max( 0, min( (int) $input['index'], count( $host['elements'] ) ) ) : count( $host['elements'] );
		array_splice( $host['elements'], $index, 0, array( $node ) );
		unset( $host, $list );

		$target = FKMCP_Pack_Elementor_Design::save_design( $step['id'], $elements );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$unpublished = $target !== $step['id'];

		if ( ! $unpublished ) {
			FKMCP_Pack_Elementor_Design::save_revision( $step['id'], FKMCP_Pack_Elementor_Design::AI_RESULT );
		}

		return array(
			'step_id'       => $step['id'],
			'element_id'    => $widget_id,
			'widget_type'   => (string) $node['widgetType'],
			'new_parent_id' => $parent_id,
			'index'         => $index,
			'removed_empty' => $removed,
			'unpublished'   => $unpublished,
			'next'          => $unpublished ? 'Publish with edit-design (tool elementor/publish-document, input post_id ' . $step['id'] . '), then open the Step and look at it.' : 'Open the Step and look at it.',
		);
	}

	/**
	 * The element at an index path.
	 *
	 * @param array $tree Element tree.
	 * @param array $path Index path.
	 *
	 * @return array|null
	 */
	private static function node_at( array $tree, array $path ) {
		$node = array( 'elements' => $tree );

		foreach ( $path as $index ) {
			if ( ! isset( $node['elements'][ $index ] ) || ! is_array( $node['elements'][ $index ] ) ) {
				return null;
			}
			$node = $node['elements'][ $index ];
		}

		return $node;
	}

	/**
	 * The list (a parent's elements, or the root) that holds the element at a path.
	 *
	 * @param array $tree Element tree, by reference.
	 * @param array $path Index path of the element.
	 *
	 * @return array Reference to the list.
	 */
	private static function &list_at( array &$tree, array $path ) {
		$list = &$tree;

		foreach ( array_slice( $path, 0, -1 ) as $index ) {
			$list = &$list[ $index ]['elements'];
		}

		return $list;
	}

	/**
	 * Removes, from a path upwards, the classic containers a move left empty: a
	 * container with nothing in it, and a section whose columns are all empty.
	 * An empty column in a section that still holds widgets stays, so the row
	 * keeps its layout.
	 *
	 * @param array    $tree Element tree, by reference.
	 * @param array    $path Index path of the emptied element's parent.
	 * @param string[] $keep Ids never removed (the move's destination and its ancestors).
	 *
	 * @return string[] Ids removed.
	 */
	private static function prune_empty( array &$tree, array $path, array $keep ) {
		$removed = array();

		while ( $path ) {
			$node = self::node_at( $tree, $path );

			if ( in_array( (string) ( $node['id'] ?? '' ), $keep, true ) ) {
				break;
			}

			$type = (string) ( $node['elType'] ?? '' );
			$kids = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array();

			if ( 'column' === $type ) {
				// Up to the section: it goes only when every column is empty.
				$path = array_slice( $path, 0, -1 );
				continue;
			}

			$empty = 'section' === $type
				? ! array_filter( $kids, static fn( $col ) => ! empty( $col['elements'] ) )
				: 'container' === $type && ! $kids;

			if ( ! $empty ) {
				break;
			}

			$removed[] = (string) ( $node['id'] ?? '' );
			$list      = &self::list_at( $tree, $path );
			array_splice( $list, (int) end( $path ), 1 );
			unset( $list );
			$path = array_slice( $path, 0, -1 );
		}

		return $removed;
	}

	/**
	 * Index path (child positions from the root) of the element with an id.
	 *
	 * @param array  $tree Element tree.
	 * @param string $id   Element id.
	 *
	 * @return array|null
	 */
	private static function element_path( array $tree, $id ) {
		foreach ( $tree as $index => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && (string) $node['id'] === $id ) {
				return array( $index );
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$sub = self::element_path( $node['elements'], $id );
				if ( null !== $sub ) {
					return array_merge( array( $index ), $sub );
				}
			}
		}

		return null;
	}

	private static function widget_type_of( array $elements, $id ) {
		foreach ( self::classic_widgets( $elements ) as $node ) {
			if ( (string) $node['id'] === $id ) {
				return $node['widgetType'];
			}
		}

		return '';
	}

	/**
	 * Sets up what a Step's funnel widgets build their settings from: the
	 * checkout form registers its controls from the checkout's template, which
	 * only a checkout request loads otherwise.
	 *
	 * @param array $step Resolved Step.
	 */
	private static function widget_context( array $step ) {
		if ( 'wfacp_checkout' === $step['post']->post_type && class_exists( 'WFACP_Common' ) && function_exists( 'WFACP_Core' ) && isset( WFACP_Core()->template_loader ) ) {
			WFACP_Common::set_id( $step['id'] );
			WFACP_Core()->template_loader->load_template( $step['id'] );
		}

		do_action( 'fkmcp_funnel_widgets_context', $step['id'], $step['post']->post_type );
	}

	/**
	 * Classic (V3) widgets anywhere in a tree: what the Elementor tools cannot edit.
	 *
	 * @param array $elements Element tree.
	 *
	 * @return array
	 */
	private static function classic_widgets( array $elements ) {
		$found = array();
		$walk  = static function ( $tree ) use ( &$walk, &$found ) {
			foreach ( (array) $tree as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( 'widget' === ( $node['elType'] ?? '' ) && ! empty( $node['widgetType'] ) && 0 !== strpos( (string) $node['widgetType'], 'e-' ) ) {
					$found[] = $node;
				}
				$walk( $node['elements'] ?? array() );
			}
		};
		$walk( $elements );

		return $found;
	}

	/**
	 * A classic widget's settings that hold a value, by name.
	 *
	 * @param string $type Widget type.
	 *
	 * @return array
	 */
	private static function widget_controls( $type ) {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type );

		if ( ! $widget ) {
			return array();
		}

		$controls = array();

		foreach ( (array) $widget->get_controls() as $name => $control ) {
			$kind = (string) ( $control['type'] ?? '' );

			if ( in_array( $kind, self::UI_CONTROLS, true ) || in_array( $kind, self::REFUSED_CONTROLS, true ) || in_array( (string) $name, self::REFUSED_SETTINGS, true ) ) {
				continue;
			}

			$controls[ (string) $name ] = $control;
		}

		return $controls;
	}

	private static function widget_title( $type ) {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type );

		return $widget ? (string) $widget->get_title() : (string) $type;
	}

	private static function describe_control( $name, array $control ) {
		$out = array(
			'name'  => (string) $name,
			'type'  => (string) ( $control['type'] ?? '' ),
			'label' => wp_strip_all_tags( (string) ( $control['label'] ?? '' ) ),
		);

		if ( isset( $control['default'] ) && '' !== $control['default'] && array() !== $control['default'] ) {
			$out['default'] = $control['default'];
		}
		if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
			$out['options'] = array_slice( array_map( 'strval', array_keys( $control['options'] ) ), 0, 40 );
		}
		if ( ! empty( $control['size_units'] ) ) {
			$out['units'] = array_values( (array) $control['size_units'] );
		}

		return $out;
	}

	/**
	 * A value in the shape its control stores, or why not. Text is filtered as
	 * post content; choices must be among the control's options (a choice
	 * with no options listed must be a plain word: it may reach the CSS);
	 * units must be among the control's size units. Elementor writes control
	 * values into the Step's stylesheet as they are, so a font must be one
	 * Elementor lists, shadows are numbers and a colour, a media URL is a URL,
	 * and any other value a control writes into the CSS (it has selectors)
	 * may not hold characters that end a declaration or a rule.
	 *
	 * @param array $control Control.
	 * @param mixed $value   Value.
	 *
	 * @return mixed|WP_Error
	 */
	private static function clean_setting( array $control, $value ) {
		$type    = (string) ( $control['type'] ?? '' );
		$options = ! empty( $control['options'] ) && is_array( $control['options'] ) ? array_map( 'strval', array_keys( $control['options'] ) ) : array();

		switch ( $type ) {
			case 'select':
			case 'choose':
			case 'select2':
				$list = is_array( $value ) ? $value : array( $value );
				foreach ( $list as $one ) {
					if ( ! is_scalar( $one ) || ( $options && '' !== (string) $one && ! in_array( (string) $one, $options, true ) ) ) {
						return new WP_Error( 'invalid', 'one of ' . implode( ', ', array_slice( $options, 0, 20 ) ) );
					}
					if ( ! $options && ! preg_match( '/^[\w\- ]*$/', (string) $one ) ) {
						return new WP_Error( 'invalid', 'a value of letters, digits, hyphens, underscores and spaces' );
					}
				}
				return is_array( $value ) ? array_map( 'strval', $value ) : (string) $value;
			case 'switcher':
				$on = isset( $control['return_value'] ) ? (string) $control['return_value'] : 'yes';
				if ( true === $value || $on === (string) $value ) {
					return $on;
				}
				return ( false === $value || '' === (string) $value ) ? '' : new WP_Error( 'invalid', $on . ' or an empty string' );
			case 'color':
				$value = trim( (string) $value );
				return self::is_colour( $value ) ? $value : new WP_Error( 'invalid', 'a colour such as #1a2b3c' );
			case 'number':
				return '' === $value || is_numeric( $value ) ? $value : new WP_Error( 'invalid', 'a number' );
			case 'slider':
				if ( ! is_array( $value ) || ( isset( $value['size'] ) && '' !== $value['size'] && ! is_numeric( $value['size'] ) ) ) {
					return new WP_Error( 'invalid', '{size, unit}' );
				}
				$units = ! empty( $control['size_units'] ) ? (array) $control['size_units'] : array( 'px' );
				$unit  = isset( $value['unit'] ) ? (string) $value['unit'] : (string) reset( $units );
				if ( ! in_array( $unit, $units, true ) ) {
					return new WP_Error( 'invalid', 'a unit among ' . implode( ', ', $units ) );
				}
				return array(
					'unit'  => $unit,
					'size'  => $value['size'] ?? '',
					'sizes' => array(),
				);
			case 'dimensions':
				if ( ! is_array( $value ) ) {
					return new WP_Error( 'invalid', '{top, right, bottom, left, unit, isLinked}' );
				}
				$units = ! empty( $control['size_units'] ) ? (array) $control['size_units'] : array( 'px' );
				$unit  = isset( $value['unit'] ) ? (string) $value['unit'] : (string) reset( $units );
				if ( ! in_array( $unit, $units, true ) ) {
					return new WP_Error( 'invalid', 'a unit among ' . implode( ', ', $units ) );
				}
				$out = array(
					'unit'     => $unit,
					'isLinked' => ! empty( $value['isLinked'] ),
				);
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					$v = $value[ $side ] ?? '';
					if ( '' !== $v && ! is_numeric( $v ) ) {
						return new WP_Error( 'invalid', 'numeric sides' );
					}
					$out[ $side ] = (string) $v;
				}
				return $out;
			case 'font':
				if ( ! is_scalar( $value ) ) {
					return new WP_Error( 'invalid', 'a font family name' );
				}
				$value = trim( (string) $value );
				$fonts = self::font_names( $options );
				if ( '' === $value || in_array( $value, $fonts, true ) ) {
					return $value;
				}
				// Without Elementor's list to check against, only a plain name.
				if ( ! $fonts && preg_match( '/^[\w\- ]+$/D', $value ) ) {
					return $value;
				}
				return new WP_Error( 'invalid', 'a font Elementor lists, such as Roboto or Open Sans, or empty for the default' );
			case 'box_shadow':
			case 'text_shadow':
				$fields = 'box_shadow' === $type ? array( 'horizontal', 'vertical', 'blur', 'spread' ) : array( 'horizontal', 'vertical', 'blur' );
				$shape  = '{' . implode( ', ', $fields ) . ', color}';
				if ( ! is_array( $value ) || array_diff( array_keys( $value ), array_merge( $fields, array( 'color' ) ) ) ) {
					return new WP_Error( 'invalid', $shape . ' with numeric offsets and a colour' );
				}
				$out = array();
				foreach ( $fields as $field ) {
					if ( ! isset( $value[ $field ] ) ) {
						continue;
					}
					if ( '' !== $value[ $field ] && ( ! is_scalar( $value[ $field ] ) || ! is_numeric( $value[ $field ] ) ) ) {
						return new WP_Error( 'invalid', $shape . ' with numeric ' . $field );
					}
					$out[ $field ] = $value[ $field ];
				}
				if ( isset( $value['color'] ) ) {
					$colour = is_scalar( $value['color'] ) ? trim( (string) $value['color'] ) : null;
					if ( null === $colour || ! self::is_colour( $colour ) ) {
						return new WP_Error( 'invalid', $shape . ' with a colour such as rgba(0,0,0,0.5)' );
					}
					$out['color'] = $colour;
				}
				return $out;
			case 'url':
				$value = is_array( $value ) ? $value : array( 'url' => $value );
				$url   = isset( $value['url'] ) && is_scalar( $value['url'] ) ? trim( (string) $value['url'] ) : '';
				$safe  = '' === $url ? '' : sanitize_url( $url, array( 'http', 'https', 'mailto', 'tel' ) );
				if ( '' !== $url && '' === $safe ) {
					return new WP_Error( 'invalid', '{url} with an http(s), mailto: or tel: link, a path or #anchor' );
				}
				$value['url'] = $safe;
				return self::clean_text( $value );
			case 'media':
				if ( ! is_array( $value ) ) {
					return new WP_Error( 'invalid', '{url, id}' );
				}
				$url = isset( $value['url'] ) && is_scalar( $value['url'] ) ? trim( (string) $value['url'] ) : '';
				$out = array(
					'url' => '' === $url ? '' : sanitize_url( $url ),
					'id'  => isset( $value['id'] ) && '' !== $value['id'] && is_scalar( $value['id'] ) ? absint( $value['id'] ) : '',
				);
				if ( '' !== $url && '' === $out['url'] ) {
					return new WP_Error( 'invalid', '{url, id} with an http(s) URL' );
				}
				foreach ( array( 'size', 'alt', 'source' ) as $key ) {
					if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
						$out[ $key ] = wp_strip_all_tags( (string) $value[ $key ] );
					}
				}
				return $out;
			case 'repeater':
				if ( empty( $control['fields'] ) || ! is_array( $control['fields'] ) ) {
					return self::clean_setting( array(), $value );
				}
				if ( ! is_array( $value ) ) {
					return new WP_Error( 'invalid', 'a list of rows' );
				}
				$fields = array();
				foreach ( $control['fields'] as $key => $field ) {
					if ( is_array( $field ) ) {
						$fields[ (string) ( $field['name'] ?? $key ) ] = $field;
					}
				}
				$rows = array();
				foreach ( $value as $index => $row ) {
					if ( ! is_array( $row ) ) {
						return new WP_Error( 'invalid', 'a list of rows' );
					}
					foreach ( $row as $key => $one ) {
						$clean = self::clean_setting( isset( $fields[ $key ] ) ? $fields[ $key ] : array(), $one );
						if ( is_wp_error( $clean ) ) {
							return new WP_Error( 'invalid', sprintf( 'row %1$s, %2$s: %3$s', $index, $key, $clean->get_error_message() ) );
						}
						$row[ $key ] = $clean;
					}
					$rows[ $index ] = $row;
				}
				return $rows;
			default:
				// A value written into the stylesheet may not end its declaration or rule.
				if ( ! empty( $control['selectors'] ) && self::breaks_css( $value ) ) {
					return new WP_Error( 'invalid', 'a value without ; { } " < > \\ or /* (it is written into the page\'s CSS)' );
				}
				// An untyped control may still end up in an href.
				if ( self::script_url( $value ) ) {
					return new WP_Error( 'invalid', 'a value that is not a javascript: or vbscript: link' );
				}
				return self::clean_text( $value );
		}
	}

	/**
	 * Whether a value is a colour Elementor can write into the CSS: hex, rgb(a),
	 * hsl(a) or transparent.
	 *
	 * @param string $value Value.
	 *
	 * @return bool
	 */
	private static function is_colour( $value ) {
		return '' === $value || (bool) preg_match( '/^(#[0-9a-f]{3,8}|(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)|transparent)$/i', $value );
	}

	/**
	 * The font families a font control takes: its own options, or Elementor's
	 * font list (system, Google and custom fonts).
	 *
	 * @param array $options The control's option keys.
	 *
	 * @return string[]
	 */
	private static function font_names( array $options ) {
		if ( $options ) {
			return $options;
		}

		return class_exists( '\Elementor\Fonts' ) ? array_map( 'strval', array_keys( (array) \Elementor\Fonts::get_fonts() ) ) : array();
	}

	/**
	 * Whether a value, at any depth, holds a character that ends a CSS
	 * declaration, rule, string or style tag, or opens a comment.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private static function breaks_css( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $one ) {
				if ( self::breaks_css( $one ) ) {
					return true;
				}
			}

			return false;
		}

		return is_string( $value ) && ( false !== strpbrk( $value, ';{}"<>\\' ) || false !== strpos( $value, '/*' ) );
	}

	/**
	 * Whether a value, at any depth, is a link that runs script.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private static function script_url( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $one ) {
				if ( self::script_url( $one ) ) {
					return true;
				}
			}

			return false;
		}

		// Browsers ignore control characters and whitespace inside a scheme, and decode entities - numeric ones even without the semicolon.
		$value = is_string( $value ) ? preg_replace( array( '/&#([0-9]+)(?![0-9;])/', '/&#(x[0-9a-f]+)(?![0-9a-f;])/i' ), '&#$1;', $value ) : '';
		$value = preg_replace( '/[\x00-\x20]+/', '', html_entity_decode( $value, ENT_QUOTES | ENT_HTML5 ) );

		// ponytail: data: is left to the url control's allowlist - "Data: ..." is ordinary heading text.
		return (bool) preg_match( '/^(javascript|vbscript):/i', $value );
	}

	/** Strings, at any depth, filtered as post content. */
	private static function clean_text( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'clean_text' ), $value );
		}

		return is_string( $value ) ? wp_kses_post( $value ) : $value;
	}

	/**
	 * The site kit's global colour or typography ids (system and custom).
	 *
	 * @param string $kind colors or typography.
	 *
	 * @return array
	 */
	private static function kit_global_ids( $kind ) {
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit ) {
			return array();
		}

		$keys = 'colors' === $kind ? array( 'system_colors', 'custom_colors' ) : array( 'system_typography', 'custom_typography' );
		$ids  = array();

		foreach ( $keys as $key ) {
			foreach ( (array) $kit->get_settings( $key ) as $item ) {
				if ( ! empty( $item['_id'] ) ) {
					$ids[] = (string) $item['_id'];
				}
			}
		}

		return $ids;
	}

	/**
	 * Points the brand colour controls of the Step's funnel widgets at a global
	 * colour. Elementor renders a colour control whose __globals__ entry names
	 * a global as that global's CSS variable, so the widgets follow it.
	 *
	 * @param array  $elements  Element tree, changed in place.
	 * @param array  $controls  Widget type => colour control keys.
	 * @param string $global_id Global colour id.
	 *
	 * @return array widget type:control pairs applied.
	 */
	public static function apply_brand( array &$elements, $controls, $global_id ) {
		$applied = array();
		$value   = 'globals/colors?id=' . $global_id;

		$walk = static function ( array &$tree ) use ( &$walk, &$applied, $controls, $value ) {
			foreach ( $tree as &$node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}

				$type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';

				if ( '' !== $type && ! empty( $controls[ $type ] ) ) {
					if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
						$node['settings'] = array();
					}

					foreach ( (array) $controls[ $type ] as $control ) {
						$node['settings']['__globals__'][ $control ] = $value;
						$applied[ $type . ':' . $control ]           = true;
					}
				}

				if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					$walk( $node['elements'] );
				}
			}
			unset( $node );
		};

		$walk( $elements );

		return array_keys( $applied );
	}

	/**
	 * The classic global colour an Elementor colour variable is published as.
	 * Site-wide styles are used as the site has them, never changed: a variable
	 * not synced to Global Colors yet is refused, for the user to turn it on.
	 *
	 * @param string $label Variable label.
	 *
	 * @return array|WP_Error variable, global
	 */
	public static function brand_global( $label ) {
		$service_class = '\Elementor\Modules\Variables\Services\Variables_Service';
		$repo_class    = '\Elementor\Modules\Variables\Storage\Variables_Repository';
		$batch_class   = '\Elementor\Modules\Variables\Services\Batch_Operations\Batch_Processor';
		$sync_class    = '\Elementor\Modules\DesignSystemSync\Module';

		// Classic widgets read a variable through the global colour Elementor's
		// design system sync publishes for it; without that module there is none.
		if ( ! class_exists( $service_class ) || ! class_exists( $repo_class ) || ! class_exists( $batch_class ) || ! class_exists( $sync_class ) || ! \Elementor\Plugin::$instance->modules_manager->get_modules( 'design-system-sync' ) ) {
			return new WP_Error( 'fkmcp_brand_unavailable', 'This Elementor version cannot share its colour variables with the funnel widgets. Set the widget colours with the checkout / optin tools instead. Nothing was changed.' );
		}

		try {
			$kit      = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
			$service  = new $service_class( new $repo_class( $kit ), new $batch_class() );
			$variable = $service->find_by_label_or_id( $label );

			if ( ! is_array( $variable ) || 'global-color-variable' !== ( $variable['type'] ?? '' ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'No colour variable labelled "%1$s". Colour variables synced to Global Colors on this site: %2$s. Nothing was changed.', $label, self::synced_colours( $service ) ) );
			}

			$label = (string) $variable['label'];

			if ( empty( $variable['sync_to_v3'] ) ) {
				return new WP_Error( 'fkmcp_brand_not_synced', sprintf( 'The funnel widgets read a colour variable through Elementor\'s Global Colors, and "%1$s" is not synced to them. Ask the user to turn on Sync to Global Colors for it in Elementor\'s Variables, or pick one that is: %2$s. Site-wide styles are not changed from here. Nothing was changed.', $label, self::synced_colours( $service ) ) );
			}

			return array(
				'variable' => $label,
				'global'   => method_exists( $sync_class, 'get_v3_sync_id' ) ? (string) $sync_class::get_v3_sync_id( $label ) : 'v4-' . strtolower( $label ),
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'fkmcp_brand_failed', 'Elementor\'s colour variables could not be read. Nothing was changed.' );
		}
	}

	/**
	 * Labels of the colour variables synced to Global Colors, for an error message.
	 *
	 * @param object $service Elementor variables service.
	 *
	 * @return string
	 */
	private static function synced_colours( $service ) {
		$colours = array();

		foreach ( (array) $service->get_variables_list() as $item ) {
			if ( empty( $item['deleted'] ) && 'global-color-variable' === ( $item['type'] ?? '' ) && ! empty( $item['sync_to_v3'] ) ) {
				$colours[] = (string) ( $item['label'] ?? '' );
			}
		}

		return $colours ? implode( ', ', array_slice( $colours, 0, 20 ) ) : 'none';
	}
}
