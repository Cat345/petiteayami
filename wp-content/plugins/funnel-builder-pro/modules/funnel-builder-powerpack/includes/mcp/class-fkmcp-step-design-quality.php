<?php

defined( 'ABSPATH' ) || exit;

/**
 * How well a Step's page is made - the checks a designer runs before a page
 * goes live, measured on the Step's Elementor design so an agent can improve
 * it round by round, as the email designer's quality review does for emails.
 *
 * It reads the design the agent is working on (its draft of a published Step)
 * and judges craft and completeness, not correctness: the funnel widgets
 * styled to match and present, one headline, readable contrast, few fonts and
 * colours, a layout that holds on a phone, no placeholder copy or typed
 * prices, buttons that go somewhere, the template's sample images replaced.
 *
 * A score out of 100 with a finding per shortfall, each saying how to fix it.
 * Loaded by FKMCP_Pack_Elementor_Design only when a check runs.
 */
class FKMCP_Step_Design_Quality {

	/** Score from which a Step is ready. */
	const GOOD = 90;

	/** Findings a visitor sees as broken or unfinished: while one is left the Step is not ready, whatever its score. */
	const DEFECTS = array( 'missing_funnel_widget', 'unstyled_funnel_widget', 'placeholder_text', 'dead_button', 'unreadable_text', 'typed_step_link', 'theme_template' );

	/** Contrast below which text cannot be read at all. */
	const UNREADABLE = 2.0;

	/** A background whose colour cannot be judged: an image, a gradient, or one a global class may set. */
	const UNKNOWN = 'unknown';

	/** Body text below this size (px) is hard to read on a phone. */
	const MIN_TEXT_SIZE = 14;

	/** A heading above this size (px) with no smaller phone size overflows a phone screen. */
	const MAX_PHONE_HEADING = 48;

	/** A fixed width (px) from which an element no longer fits a phone. */
	const PHONE_WIDTH = 420;

	/** Typefaces a page carries before it reads as unplanned. */
	const MAX_FONTS = 3;

	/** Text colours a page carries before it reads as unplanned. */
	const MAX_TEXT_COLOURS = 5;

	/** Funnel widget settings that style it; one of them set means the widget was styled. */
	const STYLE_KEY = '/color|colour|typography|font|background|border|__globals__/i';

	/** Copy that is a placeholder, not the page's words. */
	const PLACEHOLDER = '/lorem ipsum|dolor sit amet|\b(your|add|insert) (text|title|heading|headline|content|description) here\b|\bplaceholder\b/i';

	/** A price typed into the page's own text: a currency sign or code by a number. */
	const PRICE = '/(?:[$€£₹¥]\s?\d|\d[\d.,]*\s?(?:USD|EUR|GBP|INR|AUD|CAD)\b)/u';

	/** Text that starts with an icon typed as a character: arrows, ticks, crosses, bullets, stars, dingbats, emoji. */
	const ICON_CHAR = '/^\s*[\x{2022}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{1F300}-\x{1FAFF}]/u';

	/** A star rating typed as characters. */
	const STARS = '/[\x{2605}\x{2606}\x{2B50}]{2,}/u';

	/** Words a header or footer link reads as - typed as plain text, they look like a broken link. */
	const LINK_WORDS = '/^\s*(privacy( policy)?|terms( (and|&) conditions| of (service|use))?|refund( policy)?|returns?( policy)?|shipping( policy)?|contact( us)?|support|help|about( us)?|faq|faqs|disclaimer|cookie( policy)?|home|log ?in|sign in|my account|affiliates?|blog)\s*$/i';

	/** Elements sharing one hand-typed style before it should be a class. */
	const REPEATED_STYLE = 4;

	/** @var array|null Global class id => label, from the active kit. */
	private static $global_classes = null;

	/** Global class id => the background it paints: #rrggbb, self::UNKNOWN, or '' for none. */
	private static $class_backgrounds = null;

	/** The active kit's global classes, as Elementor stores them. */
	private static $class_items = null;

	/** @var array Colour variable id => hex, from the active kit. */
	private static $variables = null;

	/**
	 * check-step-design: review one Step.
	 *
	 * @param array $input step_id.
	 *
	 * @return array|WP_Error
	 */
	public static function check( $input ) {
		$step = FKMCP_Pack_Elementor_Design::resolve_step( (array) $input );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		// The review reads the Elementor design; a Step another builder shows has none,
		// so it would read as missing its form, headline and buttons.
		$design = FKMCP_Pack_Elementor_Design::step_design( $step );

		if ( 'elementor' !== $design['builder'] ) {
			return new WP_Error( 'fkmcp_wrong_step_type', sprintf( 'check-step-design scores Elementor designs only, and Step %1$d shows %2$s, so it cannot be checked here - a score would report its form, headline and buttons as missing. Look at its view link (get-step-design) to review it; to design it with Elementor, call prepare-step-design first.', $step['id'], '' !== $design['builder'] ? 'a ' . $design['builder'] . ' design' : 'no template yet' ) );
		}

		return array( 'step_id' => $step['id'] ) + self::review( $step );
	}

	/**
	 * Reviews a resolved Step's working design.
	 *
	 * @param array      $step     FKMCP_Pack_Elementor_Design::resolve_step() result.
	 * @param array|null $elements Its working design, when the caller has decoded it.
	 *
	 * @return array score, grade, ready, findings, unpublished, next
	 */
	public static function review( array $step, $elements = null ) {
		$working  = FKMCP_Pack_Elementor_Design::working_post_id( $step['id'] );
		$elements = is_array( $elements ) ? $elements : FKMCP_Pack_Elementor_Design::elements( $working );
		$facts    = self::facts( $elements );
		$found    = array();

		$add = static function ( $code, $cost, $message, $element_id = '' ) use ( &$found ) {
			$found[] = array_filter(
				array(
					'code'       => $code,
					'cost'       => $cost,
					'message'    => $message,
					'element_id' => $element_id,
				),
				static function ( $value ) {
					return '' !== $value;
				}
			);
		};

		self::check_funnel_widgets( $step, $facts, $add );
		self::check_page_template( $step, $add );
		self::check_content( $step, $facts, $add );
		self::check_type_and_colour( $facts, $add );
		self::check_phone( $facts, $add );

		$score   = max( 0, 100 - array_sum( array_column( $found, 'cost' ) ) );
		$defects = array_intersect( array_column( $found, 'code' ), self::DEFECTS );
		$ready   = $score >= self::GOOD && ! $defects;

		usort(
			$found,
			static function ( $a, $b ) {
				return $b['cost'] - $a['cost'];
			}
		);

		return array(
			'score'       => $score,
			'grade'       => $ready ? 'good' : ( $score >= 70 ? 'fair' : 'needs_work' ),
			'ready'       => $ready,
			'findings'    => $found,
			'unpublished' => $working !== $step['id'],
			'next'        => $ready
				? 'The design is ready. Publish it (edit-design elementor/publish-document) if it waits in the draft, then open the Step\'s view link and look at it at desktop and phone width before telling the user it is done.'
				: 'Fix the findings - the biggest cost first - then check again with read-design funnelkit/check-step-design. Do not tell the user the Step is done while ready is false.',
		);
	}

	/* ------------------------------------------------------------ checks */

	private static function check_page_template( array $step, callable $add ) {
		$canvas = FKMCP_Pack_Elementor_Design::missing_canvas( $step );

		if ( '' !== $canvas ) {
			$add( 'theme_template', 15, sprintf( 'The Step renders with the theme\'s page template: the theme\'s header, footer and sidebar surround the design. Call prepare-step-design - it sets FunnelKit Canvas (%s) and keeps the design.', $canvas ) );
		}
	}

	private static function check_funnel_widgets( array $step, array $facts, callable $add ) {
		foreach ( (array) $step['entry']['widgets'] as $type => $label ) {
			if ( ! isset( $facts['classic'][ $type ] ) ) {
				$add( 'missing_funnel_widget', 20, sprintf( 'The Step has no %1$s (%2$s), so it cannot work: add it back with prepare-step-design.', $label, $type ) );
			}
		}

		foreach ( (array) ( $step['entry']['keep'] ?? array() ) as $type ) {
			if ( empty( $facts['classic'][ $type ] ) ) {
				continue;
			}

			foreach ( $facts['classic'][ $type ] as $element_id => $settings ) {
				if ( ! preg_grep( self::STYLE_KEY, array_keys( array_filter( (array) $settings, array( __CLASS__, 'is_set' ) ) ) ) ) {
					$label = isset( $step['entry']['widgets'][ $type ] ) ? $step['entry']['widgets'][ $type ] : $type;
					$add( 'unstyled_funnel_widget', 15, sprintf( 'The %1$s (%2$s) still shows FunnelKit\'s default fonts, colours and button beside your design. Style it to match: read-design funnelkit/get-funnel-widgets (search: button color typography), then edit-design funnelkit/update-funnel-widget.', $label, $type ), $element_id );
				}
			}
		}

		// Other classic widgets: the design tools cannot edit them, but they are not wrong - a note, at no cost.
		$funnel  = array_merge( array_keys( (array) $step['entry']['widgets'] ), (array) ( $step['entry']['keep'] ?? array() ) );
		$classic = array_diff( array_keys( $facts['classic'] ), $funnel );

		if ( $classic ) {
			$add( 'classic_content', 0, sprintf( 'Classic widgets (%s) are on the page: Elementor\'s design tools cannot edit them - restyle them with funnelkit/update-funnel-widget, or rebuild them as atomic elements if they must change.', implode( ', ', $classic ) ) );
		}
	}

	private static function check_content( array $step, array $facts, callable $add ) {
		$type = (string) $step['entry']['type'];

		if ( ! $facts['headlines'] && in_array( $type, array( 'landing', 'optin' ), true ) ) {
			$add( 'no_headline', 8, 'No main headline (an e-heading with tag h1 or h2): lead the page with one, saying what the visitor gets.' );
		}

		if ( $facts['h1'] > 1 ) {
			$add( 'several_h1', 4, sprintf( '%d h1 headings: keep one h1 for the page\'s main headline and make the rest h2 or h3.', $facts['h1'] ) );
		}

		foreach ( array_slice( $facts['placeholders'], 0, 3 ) as $element_id ) {
			$add( 'placeholder_text', 10, 'Placeholder copy (lorem ipsum, "your text here"): write the page\'s real words.', $element_id );
		}

		foreach ( array_slice( $facts['prices'], 0, 2 ) as $element_id ) {
			$add( 'typed_price', 3, 'This text carries a figure with a currency sign. If it is a product\'s price, it goes stale when the product changes: let the funnel widgets show prices, or drop it.', $element_id );
		}

		foreach ( array_slice( $facts['dead_buttons'], 0, 3 ) as $element_id ) {
			$add( 'dead_button', 8, 'A button that goes nowhere (no link, or #): link it to the next Step (next_step_link from get-step-design), the form (#wfacp-e-form on a checkout), or a real page.', $element_id );
		}

		foreach ( array_slice( $facts['step_links'], 0, 3 ) as $element_id ) {
			$add( 'typed_step_link', 10, 'A link typed to a Step\'s or the checkout\'s address: link it to next_step_link (?wffn-next-link=yes, from get-step-design) so the visitor follows the funnel - its order bumps, upsells and order - and the link survives the funnel changing.', $element_id );
		}

		foreach ( array_slice( $facts['text_links'], 0, 3 ) as $element_id ) {
			$add( 'link_as_text', 5, 'Text that reads as a link (Privacy Policy, Terms, Contact...) but has none: give it its link (the element\'s link setting, or <a href> in the text) - plain text styled as a link looks broken.', $element_id );
		}

		$icons = count( $facts['icon_lines'] );

		if ( $icons > 0 ) {
			$add( 'icon_as_text', $icons > 1 ? 5 : 2, sprintf( '%d line(s) start with an icon typed as a character (a tick, arrow or bullet): make them a list - a paragraph with <ul><li>, one item per line - so they read, align and restyle as one.', $icons ), $facts['icon_lines'][0] );
		}

		foreach ( array_slice( $facts['icon_rows'], 0, 2, true ) as $element_id => $rows ) {
			$add( 'list_as_rows', 3, sprintf( '%d icon-and-text rows make a list by hand: build it as a list - a paragraph with <ul><li>, one item per line - so it reads as one to screen readers and search engines, and restyles in one place.', $rows ), (string) $element_id );
		}

		foreach ( array_slice( $facts['ratings'], 0, 2 ) as $element_id ) {
			$add( 'rating_as_text', 4, 'A star rating typed as characters: use e-svg star icons (fa-star, fa-star-half-alt) in a row, coloured with the accent.', $element_id );
		}

		if ( 'landing' === $type && ! $facts['next_links'] ) {
			$add( 'no_next_step_link', 10, 'Nothing on this Sales Page leads to the next Step: link its main button to next_step_link (from get-step-design).' );
		}

		$samples = count( $facts['sample_images'] );

		if ( $samples > 0 ) {
			$add( 'sample_image', min( 10, 2 * $samples ), sprintf( '%d image(s) are still the template\'s samples: replace them with the brand\'s own (read-design elementor/list-assets) when customising the page.', $samples ), $facts['sample_images'][0] );
		}
	}

	private static function check_type_and_colour( array $facts, callable $add ) {
		if ( count( $facts['fonts'] ) > self::MAX_FONTS ) {
			$add( 'too_many_fonts', 5, sprintf( '%1$d typefaces (%2$s): keep to two or three - one for headings, one for text.', count( $facts['fonts'] ), implode( ', ', array_slice( array_keys( $facts['fonts'] ), 0, 6 ) ) ) );
		}

		if ( count( $facts['text_colours'] ) > self::MAX_TEXT_COLOURS ) {
			$add( 'too_many_colours', 4, sprintf( '%d text colours: keep to a text colour, a heading colour and one accent.', count( $facts['text_colours'] ) ) );
		}

		foreach ( array_slice( $facts['contrast'], 0, 3 ) as $low ) {
			$add( $low['ratio'] < self::UNREADABLE ? 'unreadable_text' : 'low_contrast', $low['ratio'] < self::UNREADABLE ? 10 : 5, sprintf( 'Text %1$s on %2$s has a contrast of %3$.1f:1 - below the %4$.1f:1 it needs to read. Darken the text or lighten the background (or the reverse).', $low['color'], $low['background'], $low['ratio'], $low['needs'] ), $low['element_id'] );
		}

		// Measured only against the page's assumed white: the page, the kit or the theme may set another background - a note, at no cost.
		foreach ( array_slice( $facts['contrast_assumed'], 0, 3 ) as $low ) {
			$add( 'contrast_unverified', 0, sprintf( 'Text %1$s would have a contrast of %2$.1f:1 on a white page (it needs %3$.1f:1), but nothing on the page sets the background behind it - the page settings, the kit or the theme may. Look at it on the page, or give its section a background so it can be measured.', $low['color'], $low['ratio'], $low['needs'] ), $low['element_id'] );
		}

		$unknown = count( $facts['contrast_unknown'] );

		if ( $unknown > 0 ) {
			$add( 'contrast_not_checked', 0, sprintf( '%d coloured text(s) sit on an image, a gradient or a background a global class may set, so their contrast cannot be measured: look at them on the page, at desktop and phone width.', $unknown ), $facts['contrast_unknown'][0] );
		}

		$classes  = self::global_classes();
		$repeated = $facts['style_sigs'] ? max( $facts['style_sigs'] ) : 0;

		if ( $classes && ! $facts['global_used'] ) {
			$add( 'no_global_classes', 5, sprintf( 'The site has global classes (%s) but this page uses none: attach them with elementor/manage-elements (classes, by label) for the headings, text and buttons they fit, and drop the same properties from the elements\' own styles (style_apply_mode replace).', implode( ', ', array_slice( array_values( $classes ), 0, 6 ) ) ) );
		} elseif ( ! $classes && $repeated >= self::REPEATED_STYLE ) {
			$add( 'repeated_styles', 0, sprintf( '%d elements repeat the same style by hand. The site has no global classes, and this server does not create them: keep these consistent, or ask the user to set up a class in Elementor and then attach it.', $repeated ) );
		}

		foreach ( array_slice( $facts['small_text'], 0, 2 ) as $element_id ) {
			$add( 'small_text', 3, sprintf( 'Text smaller than %dpx is hard to read on a phone: make body text 15-18px.', self::MIN_TEXT_SIZE ), $element_id );
		}
	}

	private static function check_phone( array $facts, callable $add ) {
		foreach ( array_slice( $facts['fixed_widths'], 0, 3 ) as $element_id ) {
			$add( 'fixed_width', 6, sprintf( 'A width of %dpx or more with no phone value runs off a phone screen: use a percentage or max-width, or set a @media (--mobile) width.', self::PHONE_WIDTH ), $element_id );
		}

		foreach ( array_slice( $facts['row_on_phone'], 0, 3 ) as $element_id ) {
			$add( 'columns_on_phone', 6, 'Columns side by side with no phone layout squeeze on a phone: set flex-direction column under @media (--mobile) (or flex-wrap wrap).', $element_id );
		}

		foreach ( array_slice( $facts['big_headings'], 0, 2 ) as $element_id ) {
			$add( 'heading_on_phone', 3, sprintf( 'A heading above %dpx with no phone size overflows a phone: set a smaller font-size under @media (--mobile).', self::MAX_PHONE_HEADING ), $element_id );
		}
	}

	/* ------------------------------------------------------------ facts */

	/**
	 * What the checks read, gathered in one walk of the element tree.
	 *
	 * @param array $elements Element tree.
	 *
	 * @return array
	 */
	private static function facts( array $elements ) {
		$facts = array(
			'classic'          => array(),
			'headlines'        => 0,
			'h1'               => 0,
			'placeholders'     => array(),
			'prices'           => array(),
			'dead_buttons'     => array(),
			'next_links'       => 0,
			'sample_images'    => array(),
			'fonts'            => array(),
			'text_colours'     => array(),
			'contrast'         => array(),
			'contrast_assumed' => array(),
			'contrast_unknown' => array(),
			'small_text'       => array(),
			'fixed_widths'     => array(),
			'row_on_phone'     => array(),
			'big_headings'     => array(),
			'step_links'       => array(),
			'text_links'       => array(),
			'icon_lines'       => array(),
			'ratings'          => array(),
			'global_used'      => 0,
			'style_sigs'       => array(),
			'icon_rows'        => array(),
		);
		$home  = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		// $assumed: the background is the page default, not one the page sets.
		$walk = static function ( $nodes, $background, $assumed ) use ( &$walk, &$facts, $home ) {
			foreach ( (array) $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}

				$id     = isset( $node['id'] ) ? (string) $node['id'] : '';
				$el     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
				$widget = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
				$type   = 'widget' === $el ? $widget : $el;
				$styles = self::styles( $node );
				$own_bg = self::colour( $styles['desktop']['background'] ?? null, 'background' );
				$bg     = $background;
				$guess  = $assumed;

				if ( $own_bg ) {
					$bg    = $own_bg;
					$guess = false;
				} else {
					// A global class that paints a background is what the text sits on.
					$class_bg = self::class_background( $node );

					if ( null !== $class_bg ) {
						$bg    = $class_bg;
						$guess = false;
					}
				}

				if ( 'widget' === $el && 0 !== strpos( $type, 'e-' ) ) {
					$facts['classic'][ $widget ][ $id ] = isset( $node['settings'] ) ? $node['settings'] : array();
				} elseif ( 0 === strpos( $type, 'e-' ) ) {
					self::atomic_facts( $node, $type, $styles, $bg, $guess, $home, $facts );
				}

				if ( ! empty( $node['elements'] ) ) {
					$rows = self::icon_rows( (array) $node['elements'] );

					if ( $rows >= 3 ) {
						$facts['icon_rows'][ $id ] = $rows;
					}

					$walk( $node['elements'], $bg, $guess );
				}
			}
		};

		$walk( $elements, self::page_background(), true );

		return $facts;
	}

	private static function atomic_facts( array $node, $type, array $styles, $bg, $assumed, $home, array &$facts ) {
		$id       = (string) $node['id'];
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$text     = '';

		if ( 'e-heading' === $type ) {
			$tag  = (string) self::value( $settings['tag'] ?? null );
			$text = (string) self::value( $settings['title'] ?? null );

			if ( in_array( $tag, array( 'h1', 'h2' ), true ) ) {
				++$facts['headlines'];
			}
			if ( 'h1' === $tag ) {
				++$facts['h1'];
			}
		} elseif ( 'e-paragraph' === $type ) {
			$text = (string) self::value( $settings['paragraph'] ?? null );
		} elseif ( 'e-button' === $type ) {
			$text = (string) self::value( $settings['text'] ?? null );
		}

		$link = self::link( $settings['link'] ?? null );

		foreach ( (array) self::value( $settings['classes'] ?? null ) as $class_id ) {
			// Local styles are e-<element id>-<hash>; anything else is a global class.
			if ( is_string( $class_id ) && 0 !== strpos( $class_id, 'e-' ) ) {
				++$facts['global_used'];
			}
		}

		// A Step's or the checkout's address typed into a link, on the element or in its text.
		$hrefs = '' !== $link ? array( $link ) : array();

		if ( '' !== $text && preg_match_all( '/href\s*=\s*["\']([^"\']+)/i', $text, $found ) ) {
			$hrefs = array_merge( $hrefs, $found[1] );
		}
		foreach ( $hrefs as $href ) {
			if ( self::is_step_url( $href ) ) {
				$facts['step_links'][] = $id;
				break;
			}
		}

		if ( 'e-button' === $type && ( '' === $link || '#' === $link ) ) {
			$facts['dead_buttons'][] = $id;
		}
		if ( false !== strpos( $link, 'wffn-next-link' ) ) {
			++$facts['next_links'];
		}

		if ( '' !== $text ) {
			$plain = wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES ) );

			if ( preg_match( self::PLACEHOLDER, $plain ) ) {
				$facts['placeholders'][] = $id;
			}
			if ( preg_match( self::PRICE, $plain ) ) {
				$facts['prices'][] = $id;
			}

			if ( in_array( $type, array( 'e-paragraph', 'e-heading' ), true ) ) {
				if ( '' === $link && false === stripos( $text, '<a ' ) && preg_match( self::LINK_WORDS, $plain ) ) {
					$facts['text_links'][] = $id;
				}
				if ( preg_match( self::STARS, $plain ) ) {
					$facts['ratings'][] = $id;
				} elseif ( preg_match( self::ICON_CHAR, $plain ) ) {
					$facts['icon_lines'][] = $id;
				}
			}

			// The same hand-typed look on several texts is what a class is for.
			if ( ! empty( $styles['desktop'] ) ) {
				$look = $styles['desktop'];
				ksort( $look );
				$sig                         = md5( (string) wp_json_encode( $look ) );
				$facts['style_sigs'][ $sig ] = ( $facts['style_sigs'][ $sig ] ?? 0 ) + 1;
			}

			self::text_facts( $id, $type, $styles, $bg, $assumed, $facts );
		}

		if ( 'e-image' === $type ) {
			$src  = self::value( self::value( self::value( $settings['image'] ?? null )['src'] ?? null )['url'] ?? null );
			$host = is_string( $src ) ? (string) wp_parse_url( $src, PHP_URL_HOST ) : '';

			if ( '' !== $host && $host !== $home ) {
				$facts['sample_images'][] = $id;
			}
		}

		self::layout_facts( $node, $type, $styles, $facts );
	}

	private static function text_facts( $id, $type, array $styles, $bg, $assumed, array &$facts ) {
		$desktop = $styles['desktop'];
		$font    = self::value( $desktop['font-family'] ?? null );

		if ( is_string( $font ) && '' !== $font ) {
			$facts['fonts'][ $font ] = true;
		}

		$colour = self::colour( $desktop['color'] ?? null );

		if ( $colour ) {
			$facts['text_colours'][ $colour ] = true;
		}

		$size = self::px( $desktop['font-size'] ?? null );

		if ( null !== $size && $size < self::MIN_TEXT_SIZE && 'e-heading' !== $type ) {
			$facts['small_text'][] = $id;
		}
		if ( 'e-heading' === $type && null !== $size && $size > self::MAX_PHONE_HEADING && ! isset( $styles['mobile']['font-size'] ) ) {
			$facts['big_headings'][] = $id;
		}

		if ( $colour && $bg && 'e-button' !== $type ) {
			if ( self::UNKNOWN === $bg ) {
				$facts['contrast_unknown'][] = $id;

				return;
			}

			$ratio = self::contrast( $colour, $bg );
			$large = 'e-heading' === $type || ( null !== $size && $size >= 24 );
			$needs = $large ? 3.0 : 4.5;

			if ( $ratio < $needs ) {
				$facts[ $assumed ? 'contrast_assumed' : 'contrast' ][] = array(
					'element_id' => $id,
					'color'      => $colour,
					'background' => $bg,
					'ratio'      => $ratio,
					'needs'      => $needs,
				);
			}
		}
	}

	private static function layout_facts( array $node, $type, array $styles, array &$facts ) {
		$id = (string) $node['id'];

		foreach ( array( 'width', 'min-width' ) as $prop ) {
			$px = self::px( $styles['desktop'][ $prop ] ?? null );

			if ( null !== $px && $px >= self::PHONE_WIDTH && ! isset( $styles['mobile'][ $prop ] ) && ! isset( $styles['tablet'][ $prop ] ) ) {
				$facts['fixed_widths'][] = $id;
				break;
			}
		}

		if ( ! in_array( $type, array( 'e-flexbox', 'e-div-block' ), true ) ) {
			return;
		}

		// What applies on a phone: its own value, else the tablet's, else the desktop's.
		$phone = static function ( $prop ) use ( $styles ) {
			return (string) self::value( $styles['mobile'][ $prop ] ?? $styles['tablet'][ $prop ] ?? $styles['desktop'][ $prop ] ?? null );
		};

		if ( 'row' !== $phone( 'flex-direction' ) || 'wrap' === $phone( 'flex-wrap' ) ) {
			return;
		}

		$columns = 0;

		foreach ( (array) ( $node['elements'] ?? array() ) as $child ) {
			$child_type = isset( $child['widgetType'] ) ? (string) $child['widgetType'] : (string) ( $child['elType'] ?? '' );

			if ( in_array( $child_type, array( 'e-flexbox', 'e-div-block' ), true ) || ( 'widget' === ( $child['elType'] ?? '' ) && 0 !== strpos( $child_type, 'e-' ) ) ) {
				++$columns;
			}
		}

		if ( $columns >= 2 ) {
			$facts['row_on_phone'][] = $id;
		}
	}

	/* ------------------------------------------------------------ reading styles */

	/**
	 * An atomic element's own style props, by breakpoint (desktop, tablet,
	 * mobile), from its local styles in their normal state.
	 *
	 * @param array $node Element.
	 *
	 * @return array breakpoint => prop => value
	 */
	private static function styles( array $node ) {
		$out = array(
			'desktop' => array(),
			'tablet'  => array(),
			'mobile'  => array(),
		);

		foreach ( (array) ( $node['styles'] ?? array() ) as $style ) {
			foreach ( (array) ( $style['variants'] ?? array() ) as $variant ) {
				$meta       = isset( $variant['meta'] ) && is_array( $variant['meta'] ) ? $variant['meta'] : array();
				$breakpoint = isset( $meta['breakpoint'] ) ? (string) $meta['breakpoint'] : 'desktop';

				if ( ! empty( $meta['state'] ) || ! isset( $out[ $breakpoint ] ) ) {
					continue;
				}

				$out[ $breakpoint ] = array_merge( $out[ $breakpoint ], (array) ( $variant['props'] ?? array() ) );
			}
		}

		return $out;
	}

	/** A prop's plain value: {$$type, value} unwrapped. */
	private static function value( $prop ) {
		return is_array( $prop ) && array_key_exists( '$$type', $prop ) ? ( $prop['value'] ?? null ) : $prop;
	}

	/** A link prop's destination, '' when none. */
	private static function link( $prop ) {
		$link = self::value( $prop );

		if ( ! is_array( $link ) ) {
			return '';
		}

		$destination = self::value( $link['destination'] ?? null );

		return is_string( $destination ) ? trim( $destination ) : ( is_numeric( $destination ) ? (string) $destination : '' );
	}

	/** A size prop in px (rem and em at 16px), null for other units or none. */
	private static function px( $prop ) {
		$size = self::value( $prop );

		if ( ! is_array( $size ) || ! isset( $size['size'] ) || ! is_numeric( $size['size'] ) ) {
			return null;
		}

		$unit = isset( $size['unit'] ) ? (string) $size['unit'] : 'px';

		if ( 'px' === $unit ) {
			return (float) $size['size'];
		}

		return in_array( $unit, array( 'rem', 'em' ), true ) ? 16 * (float) $size['size'] : null;
	}

	/**
	 * A colour prop as #rrggbb: a colour, a colour variable of the site, or a
	 * background's colour. Null when unset or not opaque enough to judge; for
	 * a background with an image or gradient, self::UNKNOWN - text on it
	 * cannot be judged against a colour.
	 *
	 * @param mixed  $prop Prop.
	 * @param string $kind 'color' or 'background'.
	 *
	 * @return string|null
	 */
	private static function colour( $prop, $kind = 'color' ) {
		if ( 'background' === $kind ) {
			$value = self::value( $prop );

			if ( ! is_array( $value ) ) {
				return null;
			}

			// An image or gradient behind the text: its colour cannot be judged.
			if ( ! empty( $value['background-overlay'] ) ) {
				return self::UNKNOWN;
			}

			$prop = $value['color'] ?? null;
		}

		if ( is_array( $prop ) && 'global-color-variable' === ( $prop['$$type'] ?? '' ) ) {
			$variables = self::variables();
			$prop      = $variables[ (string) $prop['value'] ] ?? null;
		}

		return self::hex( self::value( $prop ) );
	}

	/** #rgb, #rrggbb, #rrggbbaa or rgb()/rgba() as #rrggbb; null when unknown or mostly transparent. */
	private static function hex( $colour ) {
		if ( ! is_string( $colour ) ) {
			return null;
		}

		$colour = strtolower( trim( $colour ) );

		if ( preg_match( '/^#([0-9a-f]{3})$/', $colour, $m ) ) {
			return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		if ( preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $colour, $m ) ) {
			return isset( $m[2] ) && hexdec( $m[2] ) < 230 ? null : '#' . $m[1];
		}
		if ( preg_match( '/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $colour, $m ) ) {
			return isset( $m[4] ) && (float) $m[4] < 0.9 ? null : sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}

		return null;
	}

	/** WCAG contrast ratio of two #rrggbb colours. */
	private static function contrast( $a, $b ) {
		$luminance = static function ( $hex ) {
			$channels = array_map(
				static function ( $pair ) {
					$c = hexdec( $pair ) / 255;

					return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
				},
				str_split( substr( $hex, 1 ), 2 )
			);

			return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		};

		$l1 = $luminance( $a );
		$l2 = $luminance( $b );

		return round( ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 ), 2 );
	}

	/** The site's colour variables, id => colour, from the active kit. */
	private static function variables() {
		if ( null !== self::$variables ) {
			return self::$variables;
		}

		self::$variables = array();
		$kit             = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) ? \Elementor\Plugin::$instance->kits_manager->get_active_kit() : null;
		$stored          = $kit ? get_post_meta( $kit->get_id(), '_elementor_global_variables', true ) : '';
		$stored          = is_string( $stored ) ? json_decode( $stored, true ) : $stored;

		foreach ( (array) ( $stored['data'] ?? array() ) as $id => $variable ) {
			if ( is_array( $variable ) && 'global-color-variable' === ( $variable['type'] ?? '' ) && empty( $variable['deleted'] ) ) {
				self::$variables[ (string) $id ] = $variable['value'] ?? null;
			}
		}

		return self::$variables;
	}

	/**
	 * How many children are an icon-and-text row: a flexbox holding an e-svg
	 * and one text element - the shape of a list built by hand.
	 *
	 * @param array $children Elements.
	 *
	 * @return int
	 */
	private static function icon_rows( array $children ) {
		$rows = 0;

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) || ! in_array( $child['elType'] ?? '', array( 'e-flexbox', 'e-div-block' ), true ) ) {
				continue;
			}

			$kinds = array_map(
				static function ( $kid ) {
					return is_array( $kid ) ? (string) ( $kid['widgetType'] ?? $kid['elType'] ?? '' ) : '';
				},
				(array) ( $child['elements'] ?? array() )
			);
			sort( $kinds );

			if ( array( 'e-paragraph', 'e-svg' ) === $kinds || array( 'e-heading', 'e-svg' ) === $kinds ) {
				++$rows;
			}
		}

		return $rows;
	}

	/**
	 * Whether a link goes to one of this site's funnel Steps, or to the
	 * WooCommerce checkout, by its address - which next_step_link should do.
	 *
	 * @param string $url Link destination.
	 *
	 * @return bool
	 */
	private static function is_step_url( $url ) {
		static $seen = array();

		$url = trim( (string) $url );

		if ( isset( $seen[ $url ] ) ) {
			return $seen[ $url ];
		}

		$seen[ $url ] = self::resolve_step_url( $url );

		return $seen[ $url ];
	}

	/**
	 * The lookup behind is_step_url(), once per address: url_to_postid() runs
	 * the rewrite rules and a query for each one.
	 *
	 * @param string $url Link destination, trimmed.
	 *
	 * @return bool
	 */
	private static function resolve_step_url( $url ) {
		if ( '' === $url || '#' === $url[0] || false !== strpos( $url, 'wffn-next-link' ) ) {
			return false;
		}

		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( '' !== $host && (string) wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
			return false;
		}

		$absolute = '' === $host ? home_url( '/' . ltrim( $url, '/' ) ) : $url;
		$post_id  = function_exists( 'url_to_postid' ) ? url_to_postid( $absolute ) : 0;

		if ( $post_id && isset( FKMCP_Pack_Elementor_Design::types()[ (string) get_post_type( $post_id ) ] ) ) {
			return true;
		}

		if ( function_exists( 'wc_get_checkout_url' ) ) {
			$checkout = (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH );
			$path     = (string) wp_parse_url( $absolute, PHP_URL_PATH );

			return '' !== $checkout && '/' !== $checkout && untrailingslashit( $path ) === untrailingslashit( $checkout );
		}

		return false;
	}

	/** The active kit's global classes, id => class, as Elementor stores them. */
	private static function class_items() {
		if ( null !== self::$class_items ) {
			return self::$class_items;
		}

		self::$class_items = array();

		if ( ! class_exists( '\Elementor\Modules\GlobalClasses\Global_Classes_Repository' ) ) {
			return self::$class_items;
		}

		try {
			self::$class_items = (array) \Elementor\Modules\GlobalClasses\Global_Classes_Repository::make()->all()->get_items()->all();
		} catch ( \Throwable $e ) {
			self::$class_items = array();
		}

		return self::$class_items;
	}

	/** The site's global classes, id => label, from the active kit. */
	private static function global_classes() {
		if ( null !== self::$global_classes ) {
			return self::$global_classes;
		}

		self::$global_classes = array();

		foreach ( self::class_items() as $id => $class ) {
			self::$global_classes[ (string) $id ] = is_array( $class ) && isset( $class['label'] ) ? (string) $class['label'] : (string) $id;
		}

		return self::$global_classes;
	}

	/** Global class id => the background its desktop style paints (see $class_backgrounds). */
	private static function class_backgrounds() {
		if ( null !== self::$class_backgrounds ) {
			return self::$class_backgrounds;
		}

		self::$class_backgrounds = array();

		foreach ( self::class_items() as $id => $class ) {
			$styles = self::styles( array( 'styles' => array( (array) $class ) ) );

			if ( ! isset( $styles['desktop']['background'] ) ) {
				// No background of its own: the class leaves what is behind it, and contrast is measured.
				self::$class_backgrounds[ (string) $id ] = '';
				continue;
			}

			$colour = self::colour( $styles['desktop']['background'], 'background' );

			// A background it cannot read as one opaque colour - a variable it cannot resolve, a see-through colour: unknown, never guessed.
			self::$class_backgrounds[ (string) $id ] = is_string( $colour ) && '' !== $colour ? $colour : self::UNKNOWN;
		}

		return self::$class_backgrounds;
	}

	/**
	 * The background an element's global classes paint: #rrggbb; self::UNKNOWN
	 * for an image or gradient, a class this check cannot read, or classes that
	 * paint different ones; null when none paints one - a class that only sets
	 * type or spacing leaves the contrast check on. Local styles are
	 * e-<element id>-<hash>; any other class is a global class.
	 *
	 * @param array $node Element.
	 *
	 * @return string|null
	 */
	private static function class_background( array $node ) {
		$painted = array();

		foreach ( (array) self::value( $node['settings']['classes'] ?? null ) as $class_id ) {
			if ( ! is_string( $class_id ) || 0 === strpos( $class_id, 'e-' ) ) {
				continue;
			}

			$backgrounds = self::class_backgrounds();

			if ( ! array_key_exists( $class_id, $backgrounds ) ) {
				return self::UNKNOWN;
			}

			if ( '' !== $backgrounds[ $class_id ] ) {
				$painted[ $backgrounds[ $class_id ] ] = true;
			}
		}

		if ( ! $painted ) {
			return null;
		}

		return 1 === count( $painted ) ? (string) key( $painted ) : self::UNKNOWN;
	}

	/**
	 * What a page with no background of its own is assumed to sit on: white,
	 * as the funnel templates' canvas is. An assumption - the page settings,
	 * kit or theme may differ - so contrast against it is only a note.
	 */
	private static function page_background() {
		return '#ffffff';
	}

	/** Whether a widget setting holds a value. */
	private static function is_set( $value ) {
		return ! ( null === $value || '' === $value || array() === $value );
	}
}
