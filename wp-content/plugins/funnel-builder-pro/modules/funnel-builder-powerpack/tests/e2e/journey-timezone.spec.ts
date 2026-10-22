import { test, expect } from '@playwright/test';

/**
 * Regression for the "Conversion completed" / "First Interaction" timezone
 * double-conversion bug found during independent QA of fix/9504 (Customer
 * Journey in Conversion Tracking, ClickUp 86bbu65n5).
 *
 * bwf_conversion_tracking.first_click / .timestamp are already site-local
 * MySQL strings (written via current_time('mysql')). The order meta box used
 * to run them through strtotime() + wp_date(), which double-converts (the
 * string gets parsed as UTC by strtotime(), then shifted to site-local again
 * by wp_date()). Fixed via mysql2date(), which parses the string in the
 * site's own timezone to begin with.
 *
 * Uses a known, already-recorded test order (12802, funnel 293, this lane's
 * pre-existing QA fixture data) rather than placing a fresh order, since this
 * is purely a display-formatting regression, not a recording-pipeline one.
 */

const ORDER_ID = 12802;
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'FkQaTemp2026!';

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

test( 'meta box and Analytics modal show the same journey/conversion times for the same order', async ( { page } ) => {
	await login( page );

	// Meta box on the WooCommerce order edit screen.
	await page.goto( `/wp-admin/post.php?post=${ ORDER_ID }&action=edit` );
	const box = page.locator( '#bwfan_utm_info_box' );
	await expect( box ).toBeVisible();
	const boxText = await box.innerText();

	const firstInteractionMatch = boxText.match( /First Interaction\n([^\n]+)/ );
	const conversionDoneMatch = boxText.match( /([\d:]+ (?:am|pm))\nConversion completed/i );
	expect( firstInteractionMatch, 'First Interaction row must be present' ).toBeTruthy();
	expect( conversionDoneMatch, 'Conversion completed node must be present' ).toBeTruthy();

	// Analytics -> Conversions -> Orders modal for the same order.
	await page.goto( '/wp-admin/admin.php?page=bwf&path=/analytics/orders' );
	const row = page.locator( 'table tbody tr', { hasText: String( ORDER_ID ) } );
	await expect( row ).toBeVisible();
	await row.getByText( /\d+ pages/ ).click();

	const modal = page.locator( '[role="dialog"].bwf-journey-modal' );
	await expect( modal ).toBeVisible();
	const modalText = await modal.innerText();
	const modalConversionMatch = modalText.match( /([\d:]+ (?:am|pm))\nConversion completed/i );
	expect( modalConversionMatch, 'Modal Conversion completed node must be present' ).toBeTruthy();

	// The regression: these used to differ by the site's UTC offset.
	expect( conversionDoneMatch![ 1 ].toLowerCase() ).toBe( modalConversionMatch![ 1 ].toLowerCase() );

	// Every per-step time in the modal must also appear verbatim in the meta
	// box's own journey timeline (catches the same class of bug on the
	// per-step path, not just the synthetic conversion node).
	const modalStepTimes = [ ...modalText.matchAll( /(\d{1,2}:\d{2} (?:am|pm))/gi ) ].map( ( m ) => m[ 1 ].toLowerCase() );
	for ( const stepTime of modalStepTimes ) {
		expect( boxText.toLowerCase() ).toContain( stepTime );
	}
} );
