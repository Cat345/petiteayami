import { chromium, type FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { wpEval } from './helpers/wp-cli';
import { placeGuestOrder } from './helpers/checkout';

export const AUTH_STATE_PATH = path.join( __dirname, '.auth', 'admin.json' );
export const FIXTURE_PATH = path.join(
	__dirname,
	'.fixtures',
	'primary-order.json'
);

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'FkQaTemp2026!';

/**
 * One-time setup for the whole QA run:
 *  1. Log in as admin once and persist storage state for all admin-facing
 *     specs (settings, order edit screen, Analytics).
 *  2. Make sure the funnel-293 checkout page (post 12783) has a real
 *     WooCommerce product wired up — it shipped with an empty cart/no
 *     product configured, so the funnel from the plan's Setup section
 *     could not otherwise complete a real order.
 *  3. Place ONE guest order through Landing -> Checkout -> Thank You as
 *     the shared fixture for assertions 2-7 (journey recording, meta box,
 *     Analytics column/modal, timezone match, Pages Visited filter, CSV
 *     export), so we don't place a fresh order per spec file.
 */
export default async function globalSetup( config: FullConfig ) {
	const baseURL = config.projects[ 0 ].use.baseURL as string;
	const executablePath = ( config.projects[ 0 ].use as any ).launchOptions
		?.executablePath;

	fs.mkdirSync( path.dirname( AUTH_STATE_PATH ), { recursive: true } );
	fs.mkdirSync( path.dirname( FIXTURE_PATH ), { recursive: true } );

	// Wire the checkout page to a real, in-stock simple product (T-Shirt,
	// id 19) so the funnel used by the guest-journey fixture can actually
	// complete a checkout. Idempotent - safe to run every time.
	wpEval( `
		$products = array(
			'wfacp_qa9504' => array(
				'title' => 'T-Shirt',
				'discount_type' => 'percent_discount_sale',
				'discount_amount' => '0',
				'discount_price' => '0',
				'quantity' => '1',
				'plan_id' => '0',
				'type' => 'simple',
				'id' => '19',
				'parent_product_id' => '0',
				'stock' => '1',
				'is_sold_individually' => '',
				'products_meta_data' => array( 0 => '19', 1 => array() ),
				'product_image' => '',
				'product_type' => 'simple',
				'product_attribute' => '-',
				'is_on_sale' => '0',
				'currency_symbol' => '&#36;',
				'product_stock_status' => '1',
				'product_stock' => 'in-stock',
				'price_range' => '',
				'product_status' => 'publish',
				'product_meta_data' => array(),
			),
		);
		update_post_meta( 12783, '_wfacp_selected_products', $products );
	` );

	const browser = await chromium.launch( { executablePath } );

	// 1. Admin auth state.
	const adminContext = await browser.newContext( {
		baseURL,
		ignoreHTTPSErrors: true,
	} );
	const adminPage = await adminContext.newPage();
	await adminPage.goto( '/wp-login.php' );
	await adminPage.fill( '#user_login', ADMIN_USER );
	await adminPage.fill( '#user_pass', ADMIN_PASS );
	await adminPage.click( '#wp-submit' );
	await adminPage.waitForURL( /wp-admin/ );

	// Make sure both First Party Tracking checkboxes are ON before the run
	// (the spec suite restores them at the end regardless).
	await adminPage.goto(
		'/wp-admin/admin.php?page=bwf&path=/settings/funnelkit_first_party_tracking'
	);
	await adminPage.waitForTimeout( 1500 );
	const parentCb = adminPage
		.locator( '.bwf-checkbox-control', {
			hasText: 'Enable First Party Conversion Tracking',
		} )
		.locator( 'input[type=checkbox]' );
	const childCb = adminPage
		.locator( '.bwf-checkbox-control', { hasText: 'Record customer journey' } )
		.locator( 'input[type=checkbox]' );
	if ( ! ( await parentCb.isChecked() ) ) {
		await parentCb.click();
		await adminPage.waitForTimeout( 300 );
	}
	if ( ( await childCb.count() ) && ! ( await childCb.isChecked() ) ) {
		await childCb.click();
		await adminPage.waitForTimeout( 300 );
	}
	await adminPage.getByRole( 'button', { name: 'Save' } ).click();
	await adminPage.waitForTimeout( 1500 );

	await adminContext.storageState( { path: AUTH_STATE_PATH } );
	await adminContext.close();

	// 2. Guest fixture order (no admin cookies).
	const guestContext = await browser.newContext( {
		baseURL,
		ignoreHTTPSErrors: true,
	} );
	const guestPage = await guestContext.newPage();
	const order = await placeGuestOrder( guestPage, 'qa-journey' );
	await guestContext.close();

	fs.writeFileSync( FIXTURE_PATH, JSON.stringify( order, null, 2 ) );

	await browser.close();
}
