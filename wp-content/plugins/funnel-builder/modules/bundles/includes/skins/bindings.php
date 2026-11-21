<?php
/** Bindings — the PHP stamper. */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stamps a skin template’s data-wffn-bundles-* directives against a view-model.
 */
class Bindings {

	/**
	 * Stamp a template against a view-model and return clean HTML.
	 *
	 * @param string $template_html Authored template markup.
	 * @param array  $scope         View-model scope.
	 * @return string
	 */
	public static function stamp( $template_html, array $scope ) {
		$template_html = (string) $template_html;
		if ( '' === trim( $template_html ) ) {
			return '';
		}

		$dom = self::load( $template_html );
		$root = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( null === $root ) {
			return '';
		}

		self::process_node( $root, $scope, $dom );

		return self::inner_html( $root );
	}

	/**
	 * Recursively process a node and its subtree against a scope.
	 *
	 * Order per node is load-bearing: repeat (expands first, owns its subtree) →
	 * if (prune) → leaf binds → recurse into surviving children.
	 *
	 * @param \DOMNode     $node  Node to process.
	 * @param array        $scope View-model scope.
	 * @param \DOMDocument $dom   Owning document.
	 * @return void
	 */
	private static function process_node( \DOMNode $node, array $scope, \DOMDocument $dom ) {
		// Snapshot children up front: the live NodeList mutates as we expand repeats.
		$children = array();
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			/** @var \DOMElement $child */

			if ( $child->hasAttribute( 'data-wffn-bundles-repeat' ) ) {
				self::expand_repeat( $child, $scope, $dom );
				continue;
			}

			// Conditional: drop the node entirely when falsy.
			if ( $child->hasAttribute( 'data-wffn-bundles-if' ) ) {
				$key = $child->getAttribute( 'data-wffn-bundles-if' );
				if ( ! self::truthy( self::resolve( $scope, $key ) ) ) {
					$child->parentNode->removeChild( $child );
					continue;
				}
				$child->removeAttribute( 'data-wffn-bundles-if' );
			}

			self::bind_element( $child, $scope );

			// A text/html bind above replaced the content, so do not re-walk it.
			$has_content_bind = $child->hasAttribute( 'data-wffn-bundles-text-pending' ) || $child->hasAttribute( 'data-wffn-bundles-html-pending' );
			if ( ! $has_content_bind ) {
				self::process_node( $child, $scope, $dom );
			}
			$child->removeAttribute( 'data-wffn-bundles-text-pending' );
			$child->removeAttribute( 'data-wffn-bundles-html-pending' );
		}
	}

	/**
	 * Expand a data-wffn-bundles-repeat node: clone once per collection item, bind each
	 * clone against the item scope, insert in place, drop the template node.
	 *
	 * @param \DOMElement  $tmpl  Template node carrying the directive.
	 * @param array        $scope View-model scope.
	 * @param \DOMDocument $dom   Owning document.
	 * @return void
	 */
	private static function expand_repeat( \DOMElement $tmpl, array $scope, \DOMDocument $dom ) {
		$key   = $tmpl->getAttribute( 'data-wffn-bundles-repeat' );
		$items = self::resolve( $scope, $key );
		$items = is_array( $items ) ? $items : array();
		$parent = $tmpl->parentNode;

		foreach ( $items as $item ) {
			$item_scope = is_array( $item ) ? $item : array();
			$clone      = $tmpl->cloneNode( true );
			$clone->removeAttribute( 'data-wffn-bundles-repeat' );
			// Recurse only when a bind on the root did not replace its content.
			self::bind_element( $clone, $item_scope );
			$has_content_bind = $clone->hasAttribute( 'data-wffn-bundles-text-pending' ) || $clone->hasAttribute( 'data-wffn-bundles-html-pending' );
			if ( ! $has_content_bind ) {
				self::process_node( $clone, $item_scope, $dom );
			}
			$clone->removeAttribute( 'data-wffn-bundles-text-pending' );
			$clone->removeAttribute( 'data-wffn-bundles-html-pending' );
			$parent->insertBefore( $clone, $tmpl );
		}

		$parent->removeChild( $tmpl );
	}

	/**
	 * Apply the leaf directives (attr, class, text, html) on a single element,
	 * marking text/html so the caller skips recursing into replaced content.
	 *
	 * @param \DOMElement $el    Element to bind.
	 * @param array       $scope View-model scope.
	 * @return void
	 */
	private static function bind_element( \DOMElement $el, array $scope ) {
		if ( $el->hasAttribute( 'data-wffn-bundles-attr' ) ) {
			$spec = $el->getAttribute( 'data-wffn-bundles-attr' );
			foreach ( self::parse_pairs( $spec ) as $attr => $key ) {
				$name = strtolower( (string) $attr );
				if ( 0 === strpos( $name, 'on' ) ) {
					continue;
				}
				$value = (string) self::scalar( self::resolve( $scope, $key ) );
				// Raw form: the DOM serialiser entity-encodes the attribute itself.
				if ( in_array( $name, array( 'src', 'href', 'action', 'formaction', 'poster' ), true ) ) {
					$value = sanitize_url( $value );
				}
				$el->setAttribute( $attr, $value );
			}
			$el->removeAttribute( 'data-wffn-bundles-attr' );
		}

		if ( $el->hasAttribute( 'data-wffn-bundles-bool-attr' ) ) {
			$spec = $el->getAttribute( 'data-wffn-bundles-bool-attr' );
			foreach ( self::parse_pairs( $spec ) as $attr => $key ) {
				if ( self::truthy( self::resolve( $scope, $key ) ) ) {
					$el->setAttribute( $attr, $attr );
				} else {
					$el->removeAttribute( $attr );
				}
			}
			$el->removeAttribute( 'data-wffn-bundles-bool-attr' );
		}

		if ( $el->hasAttribute( 'data-wffn-bundles-class' ) ) {
			$key   = $el->getAttribute( 'data-wffn-bundles-class' );
			$extra = trim( (string) self::scalar( self::resolve( $scope, $key ) ) );
			if ( '' !== $extra ) {
				$existing = trim( $el->getAttribute( 'class' ) );
				$el->setAttribute( 'class', '' === $existing ? $extra : $existing . ' ' . $extra );
			}
			$el->removeAttribute( 'data-wffn-bundles-class' );
		}

		if ( $el->hasAttribute( 'data-wffn-bundles-text' ) ) {
			$key = $el->getAttribute( 'data-wffn-bundles-text' );
			$el->removeAttribute( 'data-wffn-bundles-text' );
			while ( $el->firstChild ) {
				$el->removeChild( $el->firstChild );
			}
			$el->appendChild( $el->ownerDocument->createTextNode( (string) self::scalar( self::resolve( $scope, $key ) ) ) );
			$el->setAttribute( 'data-wffn-bundles-text-pending', '1' );
		}

		if ( $el->hasAttribute( 'data-wffn-bundles-html' ) ) {
			$key = $el->getAttribute( 'data-wffn-bundles-html' );
			$el->removeAttribute( 'data-wffn-bundles-html' );
			self::set_inner_html( $el, wp_kses_post( (string) self::scalar( self::resolve( $scope, $key ) ) ) );
			$el->setAttribute( 'data-wffn-bundles-html-pending', '1' );
		}
	}

	/**
	 * Resolve a possibly dotted key against a scope array.
	 *
	 * @param array  $scope View-model scope.
	 * @param string $key   Key, optionally dotted.
	 * @return mixed Resolved value, or null.
	 */
	private static function resolve( array $scope, $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return null;
		}
		if ( false === strpos( $key, '.' ) ) {
			return array_key_exists( $key, $scope ) ? $scope[ $key ] : null;
		}
		$cur = $scope;
		foreach ( explode( '.', $key ) as $part ) {
			if ( is_array( $cur ) && array_key_exists( $part, $cur ) ) {
				$cur = $cur[ $part ];
			} else {
				return null;
			}
		}
		return $cur;
	}

	/**
	 * "a:x, b:y" → [a=>x, b=>y].
	 *
	 * @param string $spec Directive value.
	 * @return array
	 */
	private static function parse_pairs( $spec ) {
		$out = array();
		foreach ( explode( ',', (string) $spec ) as $pair ) {
			$pair = trim( $pair );
			if ( '' === $pair || false === strpos( $pair, ':' ) ) {
				continue;
			}
			list( $name, $key ) = explode( ':', $pair, 2 );
			$out[ trim( $name ) ] = trim( $key );
		}
		return $out;
	}

	/**
	 * Is a bound value truthy for a conditional or boolean attribute?
	 *
	 * @param mixed $v Bound value.
	 * @return bool
	 */
	private static function truthy( $v ) {
		if ( is_array( $v ) ) {
			return ! empty( $v );
		}
		return ! ( null === $v || false === $v || '' === $v || 0 === $v || '0' === $v );
	}

	/**
	 * Coerce a bound value to a string scalar for attr/text output.
	 *
	 * @param mixed $v Bound value.
	 * @return string
	 */
	private static function scalar( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? '1' : '';
		}
		if ( null === $v ) {
			return '';
		}
		return is_scalar( $v ) ? $v : '';
	}

	/**
	 * Load an HTML fragment into a DOMDocument without doctype/html wrapping.
	 *
	 * @param string $html HTML fragment.
	 * @return \DOMDocument
	 */
	private static function load( $html ) {
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$prev = libxml_use_internal_errors( true );
		// Wrap so multiple top-level nodes survive; the prolog forces UTF-8.
		$wrapped = '<?xml encoding="UTF-8"><body>' . $html . '</body>';
		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $dom;
	}

	/**
	 * Serialize the inner HTML of a node.
	 *
	 * @param \DOMNode $node Node to serialize.
	 * @return string
	 */
	private static function inner_html( \DOMNode $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return trim( $html );
	}

	/**
	 * Replace an element's children with parsed HTML.
	 *
	 * @param \DOMElement $el   Element to fill.
	 * @param string      $html HTML fragment.
	 * @return void
	 */
	private static function set_inner_html( \DOMElement $el, $html ) {
		while ( $el->firstChild ) {
			$el->removeChild( $el->firstChild );
		}
		if ( '' === trim( (string) $html ) ) {
			return;
		}
		$frag = self::load( $html );
		$body = $frag->getElementsByTagName( 'body' )->item( 0 );
		if ( null === $body ) {
			return;
		}
		foreach ( iterator_to_array( $body->childNodes ) as $child ) {
			$imported = $el->ownerDocument->importNode( $child, true );
			$el->appendChild( $imported );
		}
	}
}
