import { test, expect } from '@playwright/test';
import { AUTH_STATE_PATH } from './global-setup';

/**
 * Assertion 1 (MEDIUM): Settings -> First Party Tracking shows "Record
 * customer journey" checked by default; unchecking "Enable First Party
 * Conversion Tracking" hides it.
 *
 * This test only exercises the UI toggle interaction locally in the page
 * (no Save click), so it cannot leave the lane in a modified state.
 */
test.use( { storageState: AUTH_STATE_PATH } );

test.describe( 'Assertion 1: First Party Tracking settings toggle', () => {
	test( 'Record customer journey is checked by default and is hidden when the parent toggle is off', async ( {
		page,
	} ) => {
		const consoleErrors: string[] = [];
		page.on( 'pageerror', ( e ) => consoleErrors.push( e.message ) );

		await page.goto(
			'/wp-admin/admin.php?page=bwf&path=/settings/funnelkit_first_party_tracking'
		);
		await page.waitForSelector( 'text=First Party Tracking' );

		const parentGroup = page.locator( '.bwf-checkbox-control', {
			hasText: 'Enable First Party Conversion Tracking',
		} );
		const parentCheckbox = parentGroup.locator( 'input[type=checkbox]' );
		const childGroup = page.locator( '.bwf-checkbox-control', {
			hasText: 'Record customer journey',
		} );
		const childCheckbox = childGroup.locator( 'input[type=checkbox]' );

		// Default state: both present, both checked.
		await expect( parentCheckbox ).toBeChecked();
		await expect( childCheckbox ).toHaveCount( 1 );
		await expect( childCheckbox ).toBeChecked();

		// Uncheck the parent -> the child field must disappear entirely.
		await parentCheckbox.click();
		await page.waitForTimeout( 500 );
		await expect(
			page.locator( '.bwf-checkbox-control', {
				hasText: 'Record customer journey',
			} )
		).toHaveCount( 0 );

		await page.screenshot( {
			path: 'tests/qa/report/artifacts/01-parent-off-child-hidden.png',
			fullPage: true,
		} );

		// Re-check the parent -> the child field reappears (still checked,
		// confirming its state wasn't lost while hidden).
		await parentCheckbox.click();
		await page.waitForTimeout( 500 );
		const childAfterRestore = page.locator( '.bwf-checkbox-control', {
			hasText: 'Record customer journey',
		} ).locator( 'input[type=checkbox]' );
		await expect( childAfterRestore ).toHaveCount( 1 );
		await expect( childAfterRestore ).toBeChecked();

		// No Save was clicked at any point in this test - nothing to restore.
		const unexpectedErrors = consoleErrors.filter(
			( m ) => ! /ResizeObserver/.test( m )
		);
		if ( unexpectedErrors.length ) {
			console.log( 'Console pageerrors seen on settings page:', unexpectedErrors );
		}
	} );
} );
