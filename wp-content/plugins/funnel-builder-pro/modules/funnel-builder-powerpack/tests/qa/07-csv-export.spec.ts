import { test, expect } from '@playwright/test';
import fs from 'fs';
import { AUTH_STATE_PATH, FIXTURE_PATH } from './global-setup';
import { gotoConversionsOrders, searchOrders } from './helpers/analytics';

/**
 * Assertion 7 (MEDIUM): CSV export from Conversions -> Orders has a
 * "Customer Journey" column; for the test order it reads like
 * `Title (/path) > Title (/path) > Conversion completed`.
 */
test.use( { storageState: AUTH_STATE_PATH } );

test.describe( 'Assertion 7: Orders CSV export includes Customer Journey column', () => {
	test( 'exported CSV has a Customer Journey column formatted as Title (/path) > ... > Conversion completed', async ( {
		page,
	} ) => {
		const order: { orderId: string; email: string } = JSON.parse(
			fs.readFileSync( FIXTURE_PATH, 'utf-8' )
		);

		await gotoConversionsOrders( page );
		await searchOrders( page, order.email );

		let exportStatus: any = null;
		page.on( 'response', async ( r ) => {
			if ( r.url().includes( '/funnels/export/' ) && r.request().method() === 'GET' ) {
				try {
					exportStatus = await r.json();
				} catch {
					/* ignore non-JSON responses on this route pattern */
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
		expect( exportStatus.export.fields ).toHaveProperty( 'journey_text', 'Customer Journey' );

		const fileUrl: string = exportStatus.export.file;
		const response = await page.request.get( fileUrl );
		expect( response.status() ).toBe( 200 );
		const csv = await response.text();

		fs.writeFileSync( 'tests/qa/report/artifacts/07-export.csv', csv );

		const headerLine = csv.split( '\n' )[ 0 ];
		expect( headerLine ).toContain( 'Customer Journey' );

		const dataLine = csv
			.split( '\n' )
			.find( ( line ) => line.startsWith( `${ order.orderId },` ) );
		expect( dataLine, `CSV should contain a row for order #${ order.orderId }` ).toBeTruthy();

		expect( dataLine ).toContain( 'Landing Page (/sp/landing-page-54/)' );
		expect( dataLine ).toContain( 'Checkout Page (/checkoutsssss/checkout-page-32/)' );
		expect( dataLine ).toContain( 'Conversion completed' );
		expect( dataLine ).toMatch(
			/Landing Page \(\/sp\/landing-page-54\/\) > Checkout Page \(\/checkoutsssss\/checkout-page-32\/\) > .* > Conversion completed/
		);
	} );
} );
