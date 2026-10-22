import { defineConfig, devices } from '@playwright/test';
import path from 'path';

/**
 * /fk-dev's own regression config, separate from tests/qa (that's /fk-qa's
 * independent suite). Targets the shared fk-lane-1.local QA lane.
 */
const PINNED_CHROME =
	'/Users/amansaxena/Library/Caches/ms-playwright/chromium-1208/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';

export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 90_000,
	expect: { timeout: 15_000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'tests/e2e/report/html', open: 'never' } ],
	],
	use: {
		baseURL: 'https://fk-lane-1.local',
		ignoreHTTPSErrors: true,
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
		launchOptions: {
			executablePath: PINNED_CHROME,
		},
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
	outputDir: path.join( 'tests', 'e2e', 'report', 'artifacts' ),
} );
