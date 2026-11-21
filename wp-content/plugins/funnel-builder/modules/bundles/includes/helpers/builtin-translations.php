<?php
/**
 * Built-in words for the store's language, for shopper text the store has no translation of.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translations kept in code, by language and by English source string. Callers ask only
 * after their own gettext call came back untranslated, so a language pack or Loco
 * Translate always wins.
 */
class Builtin_Translations {

	/**
	 * Parsed plural rules by expression.
	 *
	 * @var array<string,\Plural_Forms|false>
	 */
	private static $rules = array();

	/**
	 * The words for a counted string in the store's language, '' when there are none.
	 *
	 * @param string $source The English singular source, as `_n()` gets it (e.g. 'Hide %d item').
	 * @param int    $count  The count; `%d` in the words stands for it.
	 * @return string
	 */
	public static function plural( $source, $count ) {
		$entry = self::entry();
		$forms = isset( $entry[ $source ] ) ? array_values( (array) $entry[ $source ] ) : array();
		if ( empty( $forms ) ) {
			return '';
		}
		$index = min( self::plural_index( $entry, (int) $count, self::language() ), count( $forms ) - 1 );
		$form  = (string) $forms[ max( 0, $index ) ];

		return false === strpos( $form, '%d' ) ? $form : sprintf( $form, (int) $count );
	}

	/**
	 * The words for a plain string in the store's language, '' when there are none.
	 *
	 * @param string $source The English source string.
	 * @return string
	 */
	public static function text( $source ) {
		$entry = self::entry();
		$value = $entry[ $source ] ?? '';

		return (string) ( is_array( $value ) ? reset( $value ) : $value );
	}

	/**
	 * The store language's entry: full locale first ('de_CH'), then the language ('de').
	 *
	 * @return array
	 */
	private static function entry() {
		$locale = (string) determine_locale();
		$lang   = self::language();
		$all    = self::all();
		$entry  = $all[ $locale ] ?? ( $all[ $lang ] ?? array() );

		return is_array( $entry ) ? $entry : array();
	}

	/**
	 * The store's language code ('de' for de_CH).
	 *
	 * @return string
	 */
	private static function language() {
		return strtolower( (string) strtok( (string) determine_locale(), '_' ) );
	}

	/**
	 * Every language's words.
	 *
	 * @return array
	 */
	private static function all() {
		$two   = 'nplurals=2; plural=(n != 1);';
		$above = 'nplurals=2; plural=(n > 1);';
		$all   = array(
			'ja' => array(
				'plural-forms' => 'nplurals=1; plural=0;',
				'Hide %d item' => array( '%d個の項目を非表示' ),
				'Show %d item' => array( '%d個の項目を表示' ),
			),
			'es' => array(
				'plural-forms' => $two,
				'Hide %d item' => array( 'Ocultar %d artículo', 'Ocultar %d artículos' ),
				'Show %d item' => array( 'Mostrar %d artículo', 'Mostrar %d artículos' ),
			),
			'de' => array(
				'plural-forms' => $two,
				'Hide %d item' => array( '%d Produkt ausblenden', '%d Produkte ausblenden' ),
				'Show %d item' => array( '%d Produkt anzeigen', '%d Produkte anzeigen' ),
			),
			'fr' => array(
				'plural-forms' => $above,
				'Hide %d item' => array( 'Masquer %d article', 'Masquer %d articles' ),
				'Show %d item' => array( 'Afficher %d article', 'Afficher %d articles' ),
			),
			'pt' => array(
				'plural-forms' => $above,
				'Hide %d item' => array( 'Ocultar %d item', 'Ocultar %d itens' ),
				'Show %d item' => array( 'Mostrar %d item', 'Mostrar %d itens' ),
			),
			'it' => array(
				'plural-forms' => $two,
				'Hide %d item' => array( 'Nascondi %d prodotto', 'Nascondi %d prodotti' ),
				'Show %d item' => array( 'Mostra %d prodotto', 'Mostra %d prodotti' ),
			),
			'nl' => array(
				'plural-forms' => $two,
				'Hide %d item' => array( '%d item verbergen', '%d items verbergen' ),
				'Show %d item' => array( '%d item tonen', '%d items tonen' ),
			),
			'pl' => array(
				'plural-forms' => 'nplurals=3; plural=(n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
				'Hide %d item' => array( 'Ukryj %d element', 'Ukryj %d elementy', 'Ukryj %d elementów' ),
				'Show %d item' => array( 'Pokaż %d element', 'Pokaż %d elementy', 'Pokaż %d elementów' ),
			),
		);

		/**
		 * Built-in words for shopper text, used where the store has no translation of it
		 * (a language pack or Loco Translate always wins).
		 *
		 * Keyed by language code ('de') or full locale ('de_CH'), then by the English source
		 * string. A counted string ('Hide %d item') lists its forms in the language's plural
		 * order, `%d` standing for the count; a plain string maps to its words. An optional
		 * 'plural-forms' entry takes the gettext Plural-Forms header ('nplurals=3; plural=...;');
		 * without it the site's own WordPress translation supplies the rule, else n != 1.
		 *
		 * @param array $all Words by language.
		 */
		$all = apply_filters( 'wffn_bundles_builtin_translations', $all );

		return is_array( $all ) ? $all : array();
	}

	/**
	 * Which plural form a count takes: the entry's rule, else the rule of the site's own
	 * WordPress translation when it is in the same language, else n != 1.
	 *
	 * @param array  $entry The language's entry.
	 * @param int    $count The count.
	 * @param string $lang  The store's language code.
	 * @return int
	 */
	private static function plural_index( array $entry, $count, $lang ) {
		$header = (string) ( $entry['plural-forms'] ?? '' );
		if ( '' === $header && class_exists( 'WP_Translation_Controller' ) ) {
			$headers = \WP_Translation_Controller::get_instance()->get_headers( 'default' );
			$site    = strtolower( (string) strtok( (string) ( $headers['Language'] ?? '' ), '_' ) );
			$header  = $site === $lang ? (string) ( $headers['Plural-Forms'] ?? '' ) : '';
		}
		$rule = self::rule( $header );

		return $rule ? (int) $rule->get( $count ) : ( 1 === $count ? 0 : 1 );
	}

	/**
	 * A Plural-Forms header as WordPress's own evaluator, false when it does not parse.
	 *
	 * @param string $header Plural-Forms header.
	 * @return \Plural_Forms|false
	 */
	private static function rule( $header ) {
		if ( ! preg_match( '/plural\s*=\s*(.+?)\s*;?\s*$/s', $header, $m ) || ! class_exists( 'Plural_Forms' ) ) {
			return false;
		}
		$expression = $m[1];
		if ( ! array_key_exists( $expression, self::$rules ) ) {
			try {
				self::$rules[ $expression ] = new \Plural_Forms( $expression );
			} catch ( \Throwable $e ) {
				self::$rules[ $expression ] = false;
			}
		}

		return self::$rules[ $expression ];
	}
}
