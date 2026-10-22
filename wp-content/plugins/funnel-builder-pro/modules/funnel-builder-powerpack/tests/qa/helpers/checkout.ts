import type { BrowserContext, Page } from '@playwright/test';

/**
 * Fixed funnel used for the guest-journey QA fixture:
 * "fk-dev QA Attributed Revenue Test" (funnel id 293).
 *   Landing  -> post 12782 -> /sp/landing-page-54/
 *   Checkout -> post 12783 -> /checkoutsssss/checkout-page-32/ (Aero)
 * The checkout page's `_wfacp_selected_products` meta was wired to a real
 * simple WooCommerce product (T-Shirt, id 19) for this QA pass since it
 * previously had no product configured (empty-cart checkout).
 */
export const LANDING_PATH = '/sp/landing-page-54/';
export const CHECKOUT_PATH = '/checkoutsssss/checkout-page-32/';

export interface PlacedOrder {
	orderId: string;
	email: string;
}

/**
 * Drives a guest visitor through Landing -> Checkout -> COD order placement
 * on a fresh (no admin cookies) browser context, so the journey cookie is
 * attributed to a real "guest" rather than skipped for a logged-in admin
 * (see wffn_journey_skip_for_user).
 */
export async function placeGuestOrder(
	page: Page,
	emailPrefix: string
): Promise< PlacedOrder > {
	await page.goto( LANDING_PATH, { waitUntil: 'load' } );
	await page.waitForTimeout( 1200 );

	await page.goto( CHECKOUT_PATH, { waitUntil: 'load' } );
	await page.waitForTimeout( 3000 );

	const email = `${ emailPrefix }-${ Date.now() }@example.com`;
	await page.fill( 'input[name="billing_email"]', email );
	await page.fill( 'input[name="billing_first_name"]', 'QAJourney' );
	await page.fill( 'input[name="billing_last_name"]', 'Tester' );

	// The Aero checkout on this lane syncs billing address from the
	// (visually separate) shipping_* fields rather than billing_* ones.
	await page.fill( 'input[name="shipping_address_1"]', '123 QA Street' );
	await page.fill( 'input[name="shipping_city"]', 'Warsaw' );
	await page.fill( 'input[name="shipping_postcode"]', '00-001' );
	await page.waitForTimeout( 500 );

	await page.locator( 'text=Cash on delivery' ).first().click();
	await page.waitForTimeout( 800 );

	await page
		.locator( 'text=I have read and agree' )
		.locator( 'xpath=preceding-sibling::input[@type="checkbox"]' )
		.click();
	await page.waitForTimeout( 500 );

	await page.getByRole( 'button', { name: 'Place order' } ).click();
	await page.waitForURL( /order-confirmed|thank-you/, { timeout: 30_000 } );
	await page.waitForTimeout( 2000 );

	const orderText = await page
		.locator( 'text=/Order #\\d+/' )
		.first()
		.textContent();
	const match = orderText?.match( /Order #(\d+)/ );
	if ( ! match ) {
		throw new Error(
			`Could not determine order id from Thank You page text: "${ orderText }"`
		);
	}

	return { orderId: match[ 1 ], email };
}

/** Opens a fresh, cookie-less browser context (true guest). */
export async function newGuestContext(
	browser: import( '@playwright/test' ).Browser
): Promise< BrowserContext > {
	return browser.newContext( { ignoreHTTPSErrors: true } );
}
