<?php
declare( strict_types = 1 );
namespace Automattic\WooCommerce\EmailEditor\Engine\Renderer\ContentRenderer;
if (!defined('ABSPATH')) exit;
class Css_Selector_Normalizer {
private const EMAIL_BUTTON_EXCLUSIONS = ':not(.button-link):not(.product-button-link)';
private const STRING_PATTERN = <<<'REGEX'
"[^"\\]*+(?:\\.[^"\\]*+)*+"|'[^'\\]*+(?:\\.[^'\\]*+)*+'
REGEX;
private const LITERAL_PATTERN = self::STRING_PATTERN . '|/\*.*?\*/|\\\\.';
public static function normalize( string $css ): string {
if ( false === stripos( $css, ':where(' ) && false === strpos( $css, ':not(.wp-element-button)' ) ) {
return $css;
}
$tokens = preg_split( '~(' . self::LITERAL_PATTERN . '|[{};])~s', $css, -1, PREG_SPLIT_DELIM_CAPTURE );
$result = '';
$prelude = '';
if ( false === $tokens ) {
return $css;
}
foreach ( $tokens as $token ) {
if ( '{' === $token ) {
$prelude = self::normalize_rule( $prelude );
}
$prelude .= $token;
if ( in_array( $token, array( '{', '}', ';' ), true ) ) {
$result .= $prelude;
$prelude = '';
}
}
return $result . $prelude;
}
private static function normalize_rule( string $prelude ): string {
$selector = ltrim( (string) preg_replace( '~/\*.*?\*/~s', '', $prelude ) );
if ( 0 === strpos( $selector, '@' ) ) {
return $prelude;
}
if ( false !== stripos( $prelude, ':where(' ) ) {
$selectors = array();
foreach ( self::split_selectors( $prelude ) as $part ) {
$selectors = array_merge( $selectors, self::expand_where( $part ) );
}
$prelude = implode( ', ', $selectors );
}
return (string) preg_replace( '/:not\(\.wp-element-button\)(?!' . preg_quote( self::EMAIL_BUTTON_EXCLUSIONS, '/' ) . ')/', ':not(.wp-element-button)' . self::EMAIL_BUTTON_EXCLUSIONS, $prelude );
}
private static function expand_where( string $selector ): array {
$pattern = '~(?:' . self::LITERAL_PATTERN . ')(*SKIP)(*F)|:where\((?<inner>(?:[^()"\'\\\\]+|\\\\.|' . self::STRING_PATTERN . '|\((?&inner)\))*)\)~is';
if ( ! preg_match( $pattern, $selector, $matches, PREG_OFFSET_CAPTURE ) || '' === trim( $matches['inner'][0] ) ) {
return array( trim( $selector ) );
}
$before = substr( $selector, 0, $matches[0][1] );
$after = substr( $selector, $matches[0][1] + strlen( $matches[0][0] ) );
$selectors = array();
$parts = self::split_selectors( $matches['inner'][0] );
$before = (string) preg_replace( '/:root\s+$/i', '', $before );
$prefix = (string) preg_replace( '~' . self::LITERAL_PATTERN . '~s', '', $before );
if ( substr_count( $prefix, '(' ) > substr_count( $prefix, ')' ) ) {
if ( count( $parts ) > 1 || ! preg_match( '/\(\s*$/', $prefix ) || ! preg_match( '/^\s*\)/', $after ) ) {
return array( $selector );
}
}
if ( '' !== ltrim( $prefix ) && ! self::can_follow( ltrim( $prefix ), $parts ) ) {
return array( $selector );
}
foreach ( $parts as $part ) {
$universal = preg_match( '/(?:^|[\s>+~])$/', $before ) && 0 === strpos( $part, ':' ) ? '*' : '';
$selectors = array_merge( $selectors, self::expand_where( $before . $universal . $part . $after ) );
}
return $selectors;
}
private static function can_follow( string $prefix, array $parts ): bool {
$joined = ! preg_match( '/[\s>+~(]$/', $prefix );
foreach ( $parts as $part ) {
$outer = (string) preg_replace( '/\[[^\]]*\]|\((?:[^()]++|(?R))*\)/', '', $part );
if ( preg_match( '/[\s>+~]/', trim( $outer ) ) || ( $joined && preg_match( '/^[a-z*|\\\\]/i', $part ) ) ) {
return false;
}
}
return true;
}
private static function split_selectors( string $selector ): array {
$tokens = preg_split( '~(' . self::LITERAL_PATTERN . '|[(),])~s', $selector, -1, PREG_SPLIT_DELIM_CAPTURE );
if ( false === $tokens ) {
return array( $selector );
}
$selectors = array();
$current = '';
$depth = 0;
foreach ( $tokens as $token ) {
if ( '(' === $token ) {
++$depth;
} elseif ( ')' === $token ) {
--$depth;
} elseif ( ',' === $token && 0 === $depth ) {
$selectors[] = trim( $current );
$current = '';
continue;
}
$current .= $token;
}
$selectors[] = trim( $current );
return $selectors;
}
}
