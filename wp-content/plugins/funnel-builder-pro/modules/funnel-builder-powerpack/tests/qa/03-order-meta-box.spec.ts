import { test, expect } from '@playwright/test';
import fs from 'fs';
import { AUTH_STATE_PATH, FIXTURE_PATH } from './global-setup';

/**
 * Assertion 3 (HIGH): the order's WooCommerce edit screen shows a
 * "Conversion Tracking" box with a Customer Journey timeline (no
 * filter/setting required to see it beyond the default-on toggle) ending
 * in a "Conversion completed" node.
 */
test.use( { storageState: AUTH_STATE_PATH } );

test.describe( 'Assertion 3: order edit screen Conversion Tracking meta box', () => {
	test( 'shows a Customer Journey timeline ending in Conversion completed', async ( {
		page,
	} ) => {
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.orderId }`
		);
		await page.waitForSelector( '#bwfan_utm_info_box' );

		const metaBox = page.locator( '#bwfan_utm_info_box' );
		await expect( metaBox.locator( '.hndle' ) ).toHaveText( 'Conversion Tracking' );

		const journeyOl = metaBox.locator( 'ol.bwf-ct-journey' );
		await expect( journeyOl ).toHaveCount( 1 );

		// DOM-identity checks, not shared-text checks: the journey items must
		// link to THIS order's own visited URLs, scoped by the anchor's href.
		await expect(
			journeyOl.locator( 'a[href*="/sp/landing-page-54/"]' )
		).toHaveCount( 1 );
		await expect(
			journeyOl.locator( 'a[href*="/checkoutsssss/checkout-page-32/"]' )
		).toHaveCount( 1 );

		// Final node: non-link "Conversion completed" tick, must be the LAST
		// <li> in the ordered list.
		const lastLi = journeyOl.locator( 'li' ).last();
		await expect( lastLi ).toHaveClass( /bwf-ct-journey-done/ );
		await expect( lastLi ).toContainText( 'Conversion completed' );
		await expect( lastLi.locator( 'a' ) ).toHaveCount( 0 );

		await page.screenshot( {
			path: 'tests/qa/report/artifacts/03-order-meta-box.png',
			fullPage: true,
		} );
	} );
} );
