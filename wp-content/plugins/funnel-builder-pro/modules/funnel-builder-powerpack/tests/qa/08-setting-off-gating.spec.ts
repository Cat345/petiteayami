import { test, expect } from '@playwright/test';
import fs from 'fs';
import { AUTH_STATE_PATH, FIXTURE_PATH } from './global-setup';
import { gotoConversionsOrders, searchOrders } from './helpers/analytics';

/**
 * Assertion 8 (HIGH): unchecking "Record customer journey" -> Save, then:
 *  - Conversions -> Orders: no Journey column
 *  - Add Filter menu: no "Pages Visited"
 *  - an order's edit screen: no Customer Journey section (Insights may
 *    remain)
 *  - a fresh export: no Customer Journey column
 * Re-enables the setting afterwards (try/finally) so the lane is not left
 * degraded for anyone else, regardless of pass/fail.
 */
test.use( { storageState: AUTH_STATE_PATH } );

async function setRecordJourney( page: import( '@playwright/test' ).Page, checked: boolean ) {
	await page.goto(
		'/wp-admin/admin.php?page=bwf&path=/settings/funnelkit_first_party_tracking'
	);
	await page.waitForSelector( 'text=First Party Tracking' );
	const childCheckbox = page
		.locator( '.bwf-checkbox-control', { hasText: 'Record customer journey' } )
		.locator( 'input[type=checkbox]' );
	const isChecked = await childCheckbox.isChecked();
	if ( isChecked !== checked ) {
		await childCheckbox.click();
		await page.waitForTimeout( 300 );
	}
	await page.getByRole( 'button', { name: 'Save' } ).click();
	await page.waitForTimeout( 1500 );
}

test.describe( 'Assertion 8: setting-off gating', () => {
	test( 'disabling Record customer journey hides it from every surface, and re-enabling restores it', async ( {
		page,
	} ) => {
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		try {
			await setRecordJourney( page, false );
			await page.screenshot( {
				path: 'tests/qa/report/artifacts/08-setting-off.png',
				fullPage: true,
			} );

			// (a) Orders list: no Journey column.
			await gotoConversionsOrders( page );
			const headers = await page.locator( 'table th' ).allTextContents();
			expect( headers.join( '|' ) ).not.toContain( 'Journey' );

			// (b) Add Filter menu: no "Pages Visited" chip, no option in the menu.
			await expect(
				page.locator( 'button', { hasText: 'Pages Visited' } )
			).toHaveCount( 0 );
			await page.getByText( 'Add Filter', { exact: true } ).click();
			await page.waitForTimeout( 800 );
			const filterMenuText = await page
				.locator( '.components-popover' )
				.first()
				.textContent();
			expect( filterMenuText ).not.toContain( 'Pages Visited' );
			await page.screenshot( {
				path: 'tests/qa/report/artifacts/08-add-filter-menu-off.png',
			} );
			await page.keyboard.press( 'Escape' );

			// (c) Order edit screen: no Customer Journey section (Insights may
			// remain).
			await page.goto(
				`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.orderId }`
			);
			await page.waitForSelector( '#bwfan_utm_info_box' );
			await expect(
				page.locator( '#bwfan_utm_info_box .bwf-ct-col-journey' )
			).toHaveCount( 0 );
			await expect(
				page.locator( '#bwfan_utm_info_box' )
			).toContainText( 'Insights' );
			await page.screenshot( {
				path: 'tests/qa/report/artifacts/08-order-edit-journey-off.png',
				fullPage: true,
			} );

			// (d) Fresh export: no Customer Journey column.
			await gotoConversionsOrders( page );
			await searchOrders( page, order.email );
			let exportStatus: any = null;
			page.on( 'response', async ( r ) => {
				if ( r.url().includes( '/funnels/export/' ) && r.request().method() === 'GET' ) {
					try {
						exportStatus = await r.json();
					} catch {
						/* ignore */
					}
				}
			} );
			await page.getByRole( 'button', { name: 'Export' } ).click();
			await page.waitForTimeout( 1000 );
			await page
				.locator( '.components-modal__content' )
				.getByRole( 'button', { name: 'Export' } )
				.click();
			await page.waitForTimeout( 5000 );

			expect( exportStatus, 'export status response should have been captured' ).toBeTruthy();
			expect( exportStatus.export.fields ).not.toHaveProperty( 'journey_text' );
		} finally {
			// Always restore, even if an assertion above failed.
			await setRecordJourney( page, true );
			const childCheckbox = page
				.locator( '.bwf-checkbox-control', { hasText: 'Record customer journey' } )
				.locator( 'input[type=checkbox]' );
			await expect( childCheckbox ).toBeChecked();
			await page.screenshot( {
				path: 'tests/qa/report/artifacts/08-setting-restored.png',
				fullPage: true,
			} );
		}
	} );
} );
