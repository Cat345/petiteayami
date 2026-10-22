import type { Locator, Page } from '@playwright/test';

/** Navigates to FunnelKit -> Analytics -> Conversions -> Orders. */
export async function gotoConversionsOrders( page: Page ) {
	await page.goto( '/wp-admin/admin.php?page=bwf&path=/analytics/orders' );
	await page.waitForSelector( 'text=Conversions' );
	await page.waitForTimeout( 1500 );
}

/** Scopes the search box to a specific unique value (e.g. order email). */
export async function searchOrders( page: Page, term: string ) {
	await page.fill( '#inspector-text-control-0', term );
	await page.waitForTimeout( 1500 );
}

/**
 * Returns the <tr> for a given Order ID, scoped by the Order ID cell's
 * link href (`...&action=edit&id=<orderId>`) rather than visible text.
 * The cell renders the id inside a <span> alongside an icon <svg>, which
 * breaks Playwright's exact-text matching on the surrounding <a> - and
 * matching on the href is a stronger DOM-identity anchor anyway (unique
 * per order, unlike title/label text that's reused across this lane).
 */
export function orderRow( page: Page, orderId: string ): Locator {
	return page.locator( 'tr', {
		has: page.locator( `a[href$="id=${ orderId }"]` ),
	} );
}

/** Opens the Journey modal for a given order row and waits for it to render. */
export async function openJourneyModal( page: Page, orderId: string ) {
	await orderRow( page, orderId ).locator( '.bwf-journey-count' ).click();
	await page.waitForSelector( '.bwf-journey-modal' );
	await page.waitForTimeout( 800 );
	return page.locator( '.bwf-journey-modal' );
}
