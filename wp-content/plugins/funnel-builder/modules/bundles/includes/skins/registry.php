<?php
/** Registry — type → layouts → { template, css, view-model }. */
namespace WFFN\Bundles\Includes\Skins;

use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Helpers\Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of bundle types, their layouts and their render entry points.
 */
class Registry {

	/**
	 * Full type/layout map. Paths are relative to the plugin root.
	 *
	 * @return array Type id => type config.
	 */
	public static function types() {
		return apply_filters(
			'wffn_bundles_skins',
			array(
				Bundle_Store::TYPE_FIXED_BUNDLES => array(
					'label'      => 'Fixed Bundle',
					'view_model' => array( Fixed_Bundle::class, 'view_model' ),
					'defaults'   => array( Fixed_Bundle::class, 'defaults' ),
					// Stacked-only: Product_Display::bundle_layout() always returns 'stacked'.
					'layouts'    => array(
						'stacked' => array(
							'label'    => 'Stacked',
							'template' => 'templates/skins/fixed-bundle/stacked.php',
							'css'      => array( 'assets/skins/_shared.css', 'assets/skins/fixed-bundle/stacked.css' ),
						),
					),
				),
				Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER => array(
					'label'      => 'Frequently Bought Together',
					'view_model' => array( Frequently_Bought_Together::class, 'view_model' ),
					'defaults'   => array( Frequently_Bought_Together::class, 'defaults' ),
					'layouts'    => array(
						'row'  => array(
							'label'    => 'Row',
							'template' => 'templates/skins/frequently-bought-together/row.php',
							'css'      => array( 'assets/skins/_shared.css', 'assets/skins/frequently-bought-together/base.css', 'assets/skins/frequently-bought-together/row.css' ),
						),
						'list' => array(
							'label'    => 'List',
							'template' => 'templates/skins/frequently-bought-together/list.php',
							'css'      => array( 'assets/skins/_shared.css', 'assets/skins/frequently-bought-together/base.css', 'assets/skins/frequently-bought-together/list.css' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Config for one layout, falling back to the type's first registered layout.
	 *
	 * @param string $type   Bundle type id.
	 * @param string $layout Layout id.
	 * @return array|null Layout config, or null when the type has none.
	 */
	public static function layout( $type, $layout ) {
		$types = self::types();
		if ( ! isset( $types[ $type ]['layouts'] ) ) {
			return null;
		}
		$layouts = $types[ $type ]['layouts'];
		if ( isset( $layouts[ $layout ] ) ) {
			return $layouts[ $layout ];
		}
		// Unknown layout → first registered layout for the type.
		return reset( $layouts ) ?: null;
	}

	/**
	 * Resolve a skin template, letting a theme override it.
	 *
	 * Templates are `require`d for their RETURN value rather than echoed, so
	 * wc_get_template() cannot be used; wc_locate_template() resolves the path on
	 * the lookup order every other WooCommerce extension uses, which is what a
	 * theme author expects: `{theme}/wffn-bundles/skins/...` wins over our copy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative Template path relative to the plugin root.
	 * @return string Absolute path to the template that should render.
	 */
	private static function template_path( $relative ) {
		$default = Assets::path( $relative );
		// An add-on registers an absolute path; there is no name to look up.
		if ( ! function_exists( 'wc_locate_template' ) || path_is_absolute( $relative ) ) {
			return $default;
		}
		// `templates/skins/x.php` becomes `skins/x.php`, so a theme copy sits under
		// `wffn-bundles/` rather than repeating our own directory name.
		$name  = preg_replace( '#^templates/#', '', $relative );
		$found = wc_locate_template( $name, 'wffn-bundles/', Assets::path( 'templates/' ) );

		/**
		 * Filters the resolved path of a skin template.
		 *
		 * @since 1.0.0
		 *
		 * @param string $found   Path that will be required.
		 * @param string $name    Template name, relative to the templates directory.
		 * @param string $default The plugin's own copy.
		 */
		$found = apply_filters( 'wffn_bundles_skin_template_path', $found, $name, $default );
		return $found && file_exists( $found ) ? $found : $default;
	}

	/** The authored template markup (with data-wffn-bundles-* directives intact). */
	public static function template_html( $type, $layout ) {
		$cfg = self::layout( $type, $layout );
		if ( ! $cfg || empty( $cfg['template'] ) ) {
			return '';
		}
		$path = self::template_path( $cfg['template'] );
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$html = (string) require $path;

		/**
		 * Filters the marker → markup fragment map stamped into a skin template.
		 *
		 * Keys are literal markers authored in the templates; values replace
		 * them via strtr(). A marker no fragment claims survives as an inert HTML comment.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $fragments Marker → markup map.
		 * @param string $type      Bundle type id.
		 * @param string $layout    Layout id.
		 */
		$fragments = apply_filters( 'wffn_bundles_template_fragments', array(), $type, $layout );
		return empty( $fragments ) ? $html : strtr( $html, $fragments );
	}

	/**
	 * Authored markup for a fetch-on-demand modal body.
	 *
	 * The bodies belong to whichever add-on registers the modal, so nothing is
	 * mapped here by default.
	 *
	 * @param string $kind Modal body id.
	 * @return string
	 */
	public static function modal_template( $kind ) {
		/**
		 * Filters the fetch-on-demand modal bodies a skin can ask for.
		 *
		 * Keys are body ids; values are absolute paths to the template, or paths
		 * relative to this plugin's root.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $map Body id → template path.
		 */
		$map = (array) apply_filters( 'wffn_bundles_modal_templates', array() );
		if ( ! isset( $map[ $kind ] ) ) {
			return '';
		}
		$path = Assets::path( $map[ $kind ] );
		return file_exists( $path ) ? (string) require $path : '';
	}

	/**
	 * Concatenated CSS for a layout: shared sheet then layout-specific.
	 *
	 * Runs the same styles filter the storefront render does, so the editor
	 * preview carries whatever sheets an add-on attaches to the layout.
	 *
	 * @param string $type   Bundle type id.
	 * @param string $layout Layout id.
	 * @return string
	 */
	public static function css( $type, $layout ) {
		$cfg = self::layout( $type, $layout );
		if ( ! $cfg || empty( $cfg['css'] ) ) {
			return '';
		}
		$out = '';
		foreach ( (array) apply_filters( 'wffn_bundles_skin_styles', (array) $cfg['css'], $type, $layout ) as $rel ) {
			$path = Assets::path( $rel );
			if ( file_exists( $path ) ) {
				$out .= Files::read( $path ) . "\n";
			}
		}
		return $out;
	}

	/**
	 * Per-layout chunk payload for the admin, shared by REST and localization.
	 *
	 * @param string $type Bundle type id.
	 * @return array List of layout payloads.
	 */
	public static function layouts_for( $type ) {
		$types = self::types();
		if ( empty( $types[ $type ]['layouts'] ) ) {
			return array();
		}
		$defaults = self::defaults( $type );
		$items    = array();
		foreach ( $types[ $type ]['layouts'] as $layout_id => $cfg ) {
			$items[] = array(
				'id'       => $layout_id,
				'label'    => $cfg['label'] ?? $layout_id,
				'template' => self::template_html( $type, $layout_id ),
				'css'      => self::css( $type, $layout_id ),
				'defaults' => $defaults,
			);
		}
		return $items;
	}

	/**
	 * Starter content and design for a type.
	 *
	 * @param string $type Bundle type id.
	 * @return array
	 */
	public static function defaults( $type ) {
		$types = self::types();
		$cb    = $types[ $type ]['defaults'] ?? null;
		return is_callable( $cb ) ? call_user_func( $cb ) : array( 'heading' => '', 'tiers' => array(), 'design' => array() );
	}

	/**
	 * Render a skin to final HTML by stamping its template with a view-model.
	 *
	 * @param string $type   Bundle type id.
	 * @param string $layout Layout id.
	 * @param array  $args   View-model input args.
	 * @return string
	 */
	public static function render( $type, $layout, array $args ) {
		$types = self::types();
		$vm_cb = $types[ $type ]['view_model'] ?? null;
		if ( ! is_callable( $vm_cb ) ) {
			return '';
		}
		$template = self::template_html( $type, $layout );
		if ( '' === $template ) {
			return '';
		}
		$scope = call_user_func( $vm_cb, $args );
		return Bindings::stamp( $template, is_array( $scope ) ? $scope : array() );
	}
}
