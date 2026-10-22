import { test, expect } from '@playwright/test';
import { AUTH_STATE_PATH } from './global-setup';
import { placeGuestOrder } from './helpers/checkout';
import { wpEval } from './helpers/wp-cli';
import { gotoConversionsOrders, searchOrders, openJourneyModal } from './helpers/analytics';

/**
 * Assertion 9 (HIGH, security): a `javascript:`-scheme journey entry must
 * never render as a clickable link with that href, in either the meta box
 * or the Analytics modal.
 *
 * We place a dedicated real order (isolated from the shared fixture used
 * by the other assertions) and then rewrite its persisted
 * `bwf_conversion_tracking.journey` with a crafted `javascript:alert(1)`
 * entry directly in the database, matching the shape described in the
 * test plan. This exercises the SERVER-SIDE render path (safeHref() /
 * esc_url_raw()) for both surfaces without depending on the tracker's own
 * client-side cookie-write logic sanitizing it first.
 */
test.describe( 'Assertion 9: XSS safety of javascript: journey entries', () => {
	test( 'javascript: scheme is neutralized in both the meta box and the Analytics modal', async ( {
		browser,
	} ) => {
		// 1. Place a dedicated real order via a guest context.
		const guestContext = await browser.newContext( { ignoreHTTPSErrors: true } );
		const guestPage = await guestContext.newPage();
		const order = await placeGuestOrder( guestPage, 'qa-xss' );
		await guestContext.close();

		// 2. Rewrite its journey with a javascript: URI entry, matching the
		// shape from the plan's Setup section.
		wpEval( `
			global $wpdb;
			$journey = wp_json_encode( array(
				'j' => array(
					'1789136956474' => array( 'u' => 'javascript:alert(1)', 'i' => '12782', 's' => 1 ),
					'1789136959761' => array( 'u' => '/checkoutsssss/checkout-page-32/', 'i' => '12783', 's' => 1 ),
				),
				's' => array( '1' => 'https://fk-lane-1.local' ),
			) );
			$wpdb->update( $wpdb->prefix . 'bwf_conversion_tracking', array( 'journey' => $journey ), array( 'order_number' => '${ order.orderId }' ) );
		` );

		// 3. Check the meta box (admin, authenticated).
		const adminContext = await browser.newContext( {
			ignoreHTTPSErrors: true,
			storageState: AUTH_STATE_PATH,
		} );
		const page = await adminContext.newPage();
		const dialogMessages: string[] = [];
		page.on( 'dialog', async ( d ) => {
			dialogMessages.push( d.message() );
			await d.dismiss();
		} );

		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.orderId }`
		);
		await page.waitForSelector( '#bwfan_utm_info_box ol.bwf-ct-journey' );

		const metaBoxFirstStep = page.locator(
			'#bwfan_utm_info_box ol.bwf-ct-journey > li'
		).nth( 1 ); // index 0 is the day-separator li
		const metaBoxLink = metaBoxFirstStep.locator( 'a' );
		if ( await metaBoxLink.count() ) {
			const href = await metaBoxLink.getAttribute( 'href' );
			expect( href, 'meta box journey link href must never be a javascript: URI' ).not.toMatch(
				/^javascript:/i
			);
		}
		// The raw payload string must never appear as a bare, unescaped
		// javascript: scheme anywhere in the rendered box HTML.
		const metaBoxHtml = await page.locator( '#bwfan_utm_info_box' ).innerHTML();
		expect( metaBoxHtml ).not.toContain( 'href="javascript:alert(1)"' );

		await page.screenshot( {
			path: 'tests/qa/report/artifacts/09-xss-meta-box.png',
			fullPage: true,
		} );

		// 4. Check the Analytics modal (same order, same admin session).
		await gotoConversionsOrders( page );
		await searchOrders( page, order.email );
		const modal = await openJourneyModal( page, order.orderId );

		const modalFirstStep = modal.locator( 'ol.bwf-journey-timeline > li' ).nth( 1 );
		const modalLink = modalFirstStep.locator( 'a' );
		if ( await modalLink.count() ) {
			const href = await modalLink.getAttribute( 'href' );
			expect( href, 'Analytics modal journey link href must never be a javascript: URI' ).not.toMatch(
				/^javascript:/i
			);
		}
		const modalHtml = await modal.innerHTML();
		expect( modalHtml ).not.toContain( 'href="javascript:alert(1)"' );

		await modal.screenshot( { path: 'tests/qa/report/artifacts/09-xss-modal.png' } );

		// No `javascript:` dialog was ever triggered by rendering either
		// surface (would indicate the payload actually executed).
		expect( dialogMessages ).toEqual( [] );

		await adminContext.close();
	} );
} );
