import { test, expect } from '@playwright/test';
import fs from 'fs';
import { FIXTURE_PATH } from './global-setup';
import { wpCli } from './helpers/wp-cli';

/**
 * Assertion 2 (HIGH): guest browses Landing -> Checkout, completes a real
 * order (COD) -> Thank You. The resulting order's journey has 3+ ordered
 * entries.
 *
 * The order itself was placed once, in global-setup, by a guest (cookie-
 * only, no admin session) browser context so the journey is not skipped
 * via wffn_journey_skip_for_user. This spec verifies the persisted
 * `bwf_conversion_tracking.journey` payload directly against the database,
 * which is the source of truth independent of any rendering surface.
 */
test.describe( 'Assertion 2: guest journey recorded end-to-end', () => {
	test( 'order created via Landing -> Checkout -> Thank You has an ordered 3+ step journey', async () => {
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		const out = wpCli( [
			'db',
			'query',
			`SELECT journey FROM wpls_bwf_conversion_tracking WHERE order_number='${ order.orderId }';`,
		] );

		const lines = out.trim().split( '\n' );
		expect( lines.length, 'expected exactly one matching conversion_tracking row' ).toBe( 2 );
		const journeyRaw = lines[ 1 ];
		expect( journeyRaw.length, 'journey column should not be empty' ).toBeGreaterThan( 0 );

		const journey = JSON.parse( journeyRaw );
		expect( journey ).toHaveProperty( 'j' );

		const steps = Object.entries( journey.j ) as [ string, { u: string; i: string | number; s: number } ][];

		// Ordered by ms-epoch key, ascending (visit order).
		steps.sort( ( a, b ) => Number( a[ 0 ] ) - Number( b[ 0 ] ) );

		expect(
			steps.length,
			`expected 3+ journey entries for order #${ order.orderId }, got: ${ JSON.stringify( steps ) }`
		).toBeGreaterThanOrEqual( 3 );

		// Sanity-check the known funnel path shape: landing, checkout, then a
		// thank-you/confirmation page - scoped to THIS order's own data, not
		// any shared/reused page title.
		const paths = steps.map( ( [ , v ] ) => v.u );
		expect( paths[ 0 ] ).toContain( '/sp/landing-page-54/' );
		expect( paths[ 1 ] ).toContain( '/checkoutsssss/checkout-page-32/' );
		expect( paths[ paths.length - 1 ] ).toMatch( /thank-you|order-confirmed/ );

		fs.writeFileSync(
			'tests/qa/report/artifacts/02-journey-db-row.json',
			JSON.stringify( { orderId: order.orderId, journey }, null, 2 )
		);
	} );
} );
