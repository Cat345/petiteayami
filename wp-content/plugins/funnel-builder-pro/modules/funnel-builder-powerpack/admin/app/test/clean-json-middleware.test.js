/**
 * Unit tests for the admin app's clean-json middleware.
 *
 * Kept outside admin/app/src: generate:lite copies src into Lite and lints it
 * with jest's globals undefined.
 *
 * Run: npm run test:unit
 */
import {
	recoverJSON,
	cleanJSONMiddleware,
} from '../src/utils/clean-json-middleware';

const PAYLOAD = {
	success: true,
	msg: 'Data is saved',
	data: {
		products: { k: { discount_amount: 11, nested: { deep: [ 1, 2 ] } } },
	},
};
const JSON_BODY = JSON.stringify( PAYLOAD );

// Elementor 4.3.3 prints a post's CSS as one rule (one "{") per element.
const elementorStyle = ( rules ) =>
	'<style id="elementor-post-1322">' +
	Array.from(
		{ length: rules },
		( _, i ) =>
			`.elementor-1322 .elementor-element.elementor-element-h${ i } .elementor-heading-title{font-size:${
				12 + i
			}px;color:#0${ i }a;}`
	).join( '' ) +
	'</style>';

const NOTICE =
	'<br />\n<b>Deprecated</b>:  Class WCS_Autoloader is deprecated in <b>/x.php</b> on line <b>3</b><br />\n';

describe( 'recoverJSON', () => {
	it( 'parses a clean body strictly', () => {
		expect( recoverJSON( JSON_BODY ) ).toEqual( {
			ok: true,
			data: PAYLOAD,
		} );
		expect( recoverJSON( '[1,2]' ) ).toEqual( {
			ok: true,
			data: [ 1, 2 ],
		} );
		expect( recoverJSON( 'null' ) ).toEqual( { ok: true, data: null } );
	} );

	it( 'rejects empty, non-string and garbage bodies', () => {
		expect( recoverJSON( '' ).ok ).toBe( false );
		expect( recoverJSON( undefined ).ok ).toBe( false );
		expect( recoverJSON( 'garbage' ).ok ).toBe( false );
	} );

	it( 'recovers JSON behind a leading <style> block with more than 32 braces', () => {
		const style = elementorStyle( 300 );
		expect( style.split( '{' ).length - 1 ).toBeGreaterThan( 32 );
		expect( recoverJSON( style + JSON_BODY ) ).toEqual( {
			ok: true,
			data: PAYLOAD,
		} );
	} );

	it( 'recovers JSON followed by a trailing <style> or <script> block', () => {
		expect( recoverJSON( JSON_BODY + elementorStyle( 40 ) ) ).toEqual( {
			ok: true,
			data: PAYLOAD,
		} );
		expect(
			recoverJSON( JSON_BODY + '<script>var x = { a: [ 1 ] };</script>' )
		).toEqual( { ok: true, data: PAYLOAD } );
		expect( recoverJSON( '[1,[2]]<script>f([3]);</script>' ) ).toEqual( {
			ok: true,
			data: [ 1, [ 2 ] ],
		} );
	} );

	it( 'recovers JSON between leading and trailing markup', () => {
		const body =
			elementorStyle( 40 ) +
			NOTICE +
			JSON_BODY +
			'\n' +
			elementorStyle( 40 );
		expect( recoverJSON( body ) ).toEqual( { ok: true, data: PAYLOAD } );
	} );

	it( 'keeps <style> text inside a payload value', () => {
		const payload = { html: '<style>a{color:red}</style>' };
		// PHP escapes "/" by default, so "</style>" arrives as "<\/style>".
		const encoded = JSON.stringify( payload ).replace( /\//g, '\\/' );
		expect( recoverJSON( elementorStyle( 40 ) + encoded ) ).toEqual( {
			ok: true,
			data: payload,
		} );
	} );

	it( 'recovers a payload holding <script> text when a closing tag follows it', () => {
		const payload = { head: '<script>gtag()</script>' };
		const encoded = JSON.stringify( payload ).replace( /\//g, '\\/' );
		expect(
			recoverJSON( NOTICE + encoded + '<script>gtag()</script>' )
		).toEqual( { ok: true, data: payload } );
		expect( recoverJSON( NOTICE + encoded + '\n</script>' ) ).toEqual( {
			ok: true,
			data: payload,
		} );
	} );

	it( 'recovers JSON around PHP notices and wpdb prefixes', () => {
		expect( recoverJSON( NOTICE + JSON_BODY + NOTICE ) ).toEqual( {
			ok: true,
			data: PAYLOAD,
		} );
		expect(
			recoverJSON( "[Table 'wp_x' doesn't exist]\n" + JSON_BODY )
		).toEqual( { ok: true, data: PAYLOAD } );
	} );

	it( 'does not carve JSON out of a non-JSON content-type', () => {
		expect(
			recoverJSON( elementorStyle( 40 ) + JSON_BODY, false )
		).toEqual( { ok: false, data: null } );
	} );
} );

describe( 'cleanJSONMiddleware', () => {
	const response = ( body, status = 200, type = 'application/json' ) => ( {
		status,
		headers: { get: () => type },
		text: () => Promise.resolve( body ),
	} );
	const run = ( result, options = {} ) =>
		cleanJSONMiddleware( { path: '/x', ...options }, () => result );

	it( 'resolves a save-layout body behind a leading Elementor <style>', async () => {
		await expect(
			run(
				Promise.resolve( response( elementorStyle( 300 ) + JSON_BODY ) )
			)
		).resolves.toEqual( PAYLOAD );
	} );

	it( 'rejects a non-2xx body with its parsed WP error', async () => {
		const error = { code: 'rest_forbidden', data: { status: 403 } };
		await expect(
			run(
				Promise.reject(
					response( NOTICE + JSON.stringify( error ), 403 )
				)
			)
		).rejects.toEqual( error );
	} );

	it( 'rejects an HTML page as invalid_json', async () => {
		await expect(
			run(
				Promise.resolve(
					response(
						'<html>{"success":true}</html>',
						200,
						'text/html'
					)
				)
			)
		).rejects.toMatchObject( {
			code: 'invalid_json',
			data: { status: 200 },
		} );
	} );

	it( 'resolves 204 to null and leaves parse:false untouched', async () => {
		await expect(
			run( Promise.resolve( response( '', 204 ) ) )
		).resolves.toBe( null );
		const raw = response( JSON_BODY );
		await expect(
			run( Promise.resolve( raw ), { parse: false } )
		).resolves.toBe( raw );
	} );
} );
