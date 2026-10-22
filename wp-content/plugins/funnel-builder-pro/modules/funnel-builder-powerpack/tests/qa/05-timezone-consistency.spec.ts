import { test, expect } from '@playwright/test';
import fs from 'fs';
import { AUTH_STATE_PATH, FIXTURE_PATH } from './global-setup';
import { gotoConversionsOrders, searchOrders, openJourneyModal } from './helpers/analytics';

/**
 * Assertion 5 (HIGH, timezone-consistency regression check): the journey
 * step times shown in the Analytics modal and in the WooCommerce order
 * meta box, for the SAME order, must be identical strings - not just both
 * present. This is a live behavioral check (a code read can't fully close
 * this out because it depends on runtime site timezone/offset handling).
 */
test.use( { storageState: AUTH_STATE_PATH } );

test.describe( 'Assertion 5: timezone consistency between meta box and Analytics modal', () => {
	test( 'per-step times and the Conversion completed time match exactly across both surfaces', async ( {
		page,
	} ) => {
		test.setTimeout( 60_000 );
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		// --- Meta box times ---
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.orderId }`
		);
		await page.waitForSelector( '#bwfan_utm_info_box ol.bwf-ct-journey' );
		const metaBoxItems = page.locator( '#bwfan_utm_info_box ol.bwf-ct-journey > li' );
		const metaBoxCount = await metaBoxItems.count();
		const metaBoxTimes: string[] = [];
		for ( let i = 0; i < metaBoxCount; i++ ) {
			const li = metaBoxItems.nth( i );
			if ( ( await li.getAttribute( 'class' ) ) === 'bwf-ct-journey-day' ) {
				continue; // day-separator li, no time
			}
			metaBoxTimes.push(
				( await li.locator( '.bwf-ct-journey-time' ).textContent() )?.trim() ?? ''
			);
		}

		// --- Analytics modal times ---
		await gotoConversionsOrders( page );
		await searchOrders( page, order.email );
		const modal = await openJourneyModal( page, order.orderId );
		const modalSteps = modal.locator( 'ol.bwf-journey-timeline > li' );
		const modalCount = await modalSteps.count();
		const modalTimes: string[] = [];
		for ( let i = 0; i < modalCount; i++ ) {
			const li = modalSteps.nth( i );
			if ( ( await li.getAttribute( 'class' ) ) === 'bwf-journey-day' ) {
				continue;
			}
			modalTimes.push(
				( await li.locator( '.bwf-journey-time' ).textContent() )?.trim() ?? ''
			);
		}

		fs.writeFileSync(
			'tests/qa/report/artifacts/05-timezone-times.json',
			JSON.stringify( { orderId: order.orderId, metaBoxTimes, modalTimes }, null, 2 )
		);

		expect(
			metaBoxTimes.length,
			`meta box and modal should list the same number of timed nodes (meta box: ${ JSON.stringify(
				metaBoxTimes
			) }, modal: ${ JSON.stringify( modalTimes ) })`
		).toBe( modalTimes.length );

		// Per-node exact string comparison, node by node (index-aligned since
		// both surfaces render the same journey in the same visit order).
		for ( let i = 0; i < metaBoxTimes.length; i++ ) {
			expect(
				metaBoxTimes[ i ],
				`journey node #${ i } time mismatch between meta box ("${ metaBoxTimes[ i ] }") and Analytics modal ("${ modalTimes[ i ] }") for order #${ order.orderId }`
			).toBe( modalTimes[ i ] );
		}
	} );
} );
