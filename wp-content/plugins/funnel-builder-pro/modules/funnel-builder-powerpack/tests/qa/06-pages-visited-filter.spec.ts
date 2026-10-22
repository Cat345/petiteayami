import { test, expect } from '@playwright/test';
import fs from 'fs';
import { AUTH_STATE_PATH, FIXTURE_PATH } from './global-setup';
import { gotoConversionsOrders, orderRow } from './helpers/analytics';

/**
 * Assertion 6 (MEDIUM): filtering Orders by a page the test journey
 * visited (operator "Visited any of") returns that order; adding a second
 * page not visited with operator "Visited all of" excludes it.
 *
 * The "Pages Visited" filter's option list only exposes step TITLES (e.g.
 * "Landing Page"), which are reused across many funnels on this shared
 * lane. We select by that necessarily-shared label (it's the only thing
 * the UI exposes), but every assertion below is scoped to our order's own
 * Order ID, never to the shared label text.
 */
test.use( { storageState: AUTH_STATE_PATH } );

async function openPagesVisitedChip( page: import( '@playwright/test' ).Page ) {
	await page.getByText( 'Pages Visited', { exact: true } ).click();
	await page.waitForTimeout( 800 );
}

async function pickPage( page: import( '@playwright/test' ).Page, label: string ) {
	await page.locator( '.bwf-filter-search .bwf-condition-input' ).click( { force: true } );
	await page.waitForTimeout( 500 );
	await page.locator( 'li.active-result', { hasText: label } ).first().click();
	await page.waitForTimeout( 400 );
}

test.describe( 'Assertion 6: Pages Visited filter', () => {
	test( '"Visited any of" a visited page includes the order; "Visited all of" plus an unvisited page excludes it', async ( {
		page,
	} ) => {
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		await gotoConversionsOrders( page );

		// --- "Visited any of" Landing Page -> our order is included. ---
		await openPagesVisitedChip( page );
		await pickPage( page, 'Landing Page' );
		await page.locator( '.bwf-filter-apply-btn' ).click();
		await page.waitForTimeout( 1500 );

		await expect( orderRow( page, order.orderId ) ).toHaveCount( 1 );
		await page.screenshot( {
			path: 'tests/qa/report/artifacts/06-filter-any-of-included.png',
			fullPage: true,
		} );

		// --- Switch to "Visited all of" + add an unvisited page ("Upsell")
		// -> the result set must exclude our order (it never visited an
		// Upsell/Offer step). ---
		await page.locator( 'text=Pages Visited: Visited any of' ).click();
		await page.waitForTimeout( 600 );
		await page.locator( '.bwf-filter-operator input[type=button]' ).click();
		await page.waitForTimeout( 400 );
		await page.getByText( 'Visited all of', { exact: true } ).click();
		await page.waitForTimeout( 400 );
		await pickPage( page, 'Upsell' );
		await page.locator( '.bwf-filter-apply-btn' ).click();
		await page.waitForTimeout( 1500 );

		await expect( orderRow( page, order.orderId ) ).toHaveCount( 0 );
		await page.screenshot( {
			path: 'tests/qa/report/artifacts/06-filter-all-of-excluded.png',
			fullPage: true,
		} );
	} );
} );
