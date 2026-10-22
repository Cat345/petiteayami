import { test, expect } from '@playwright/test';
import fs from 'fs';
import { AUTH_STATE_PATH, FIXTURE_PATH } from './global-setup';
import { gotoConversionsOrders, searchOrders, orderRow, openJourneyModal } from './helpers/analytics';

/**
 * Assertion 4 (HIGH): FunnelKit -> Analytics -> Conversions -> Orders shows
 * a Journey column with an "N pages" pill for that order; clicking opens a
 * modal listing the same steps in visit order, ending with the conversion
 * node.
 */
test.use( { storageState: AUTH_STATE_PATH } );

test.describe( 'Assertion 4: Analytics Journey column + modal', () => {
	test( 'Journey column shows an N pages pill and the modal lists steps in visit order', async ( {
		page,
	} ) => {
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		await gotoConversionsOrders( page );
		// Wait for a known header's TEXT to paint (not just the <th> node
		// existing - React can mount an empty header cell before the label
		// text renders into it).
		await page.waitForSelector( 'table th:has-text("Order ID")' );

		// $$eval reads live textContent in one round-trip; allTextContents()
		// on this React-rendered header row was observed to sometimes read
		// back empty strings if queried before the header text painted.
		const headers = await page.$$eval( 'table th', ( els ) =>
			els.map( ( el ) => el.textContent?.trim() ?? '' )
		);
		expect( headers.join( '|' ), 'Journey column header must be present' ).toContain( 'Journey' );

		await searchOrders( page, order.email );

		const row = orderRow( page, order.orderId );
		await expect( row ).toHaveCount( 1 );

		const pill = row.locator( '.bwf-journey-count' );
		await expect( pill ).toHaveCount( 1 );
		await expect( pill ).toHaveText( /\d+ pages?/ );

		const modal = await openJourneyModal( page, order.orderId );
		await expect( modal.locator( '.bwf-journey-modal-order' ) ).toContainText( order.orderId );

		const steps = modal.locator( 'ol.bwf-journey-timeline > li.bwf-journey-step' );
		const count = await steps.count();
		expect( count, 'modal should list 3+ steps in visit order' ).toBeGreaterThanOrEqual( 3 );

		// Same DOM-identity scoped checks as the meta box - by href, not by
		// shared/reused visible title text.
		await expect(
			modal.locator( 'a[href*="/sp/landing-page-54/"]' )
		).toHaveCount( 1 );
		await expect(
			modal.locator( 'a[href*="/checkoutsssss/checkout-page-32/"]' )
		).toHaveCount( 1 );

		// Steps are in visit order in the DOM: landing before checkout.
		const stepHrefs = await modal.locator( 'a.bwf-journey-step-title' ).evaluateAll( ( els ) =>
			els.map( ( el ) => el.getAttribute( 'href' ) )
		);
		const landingIdx = stepHrefs.findIndex( ( h ) => h?.includes( 'landing-page-54' ) );
		const checkoutIdx = stepHrefs.findIndex( ( h ) => h?.includes( 'checkout-page-32' ) );
		expect( landingIdx ).toBeGreaterThanOrEqual( 0 );
		expect( checkoutIdx ).toBeGreaterThan( landingIdx );

		// Final step: converted node, no link, last in the ordered list.
		const lastStep = modal.locator( 'ol.bwf-journey-timeline > li' ).last();
		await expect( lastStep ).toHaveClass( /bwf-journey-converted/ );
		await expect( lastStep ).toContainText( 'Conversion completed' );

		await modal.screenshot( { path: 'tests/qa/report/artifacts/04-journey-modal.png' } );
	} );
} );
