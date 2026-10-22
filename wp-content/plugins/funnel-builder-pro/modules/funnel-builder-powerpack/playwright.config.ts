import { defineConfig, devices } from '@playwright/test';
import path from 'path';

/**
 * QA regression config for the "Customer Journey in Conversion Tracking"
 * feature (fix/9504). Targets the shared fk-lane-1.local QA lane.
 *
 * Uses a pinned local Chromium build because the environment's macOS
 * version is too old for the Chromium build that ships with the
 * installed Playwright version (mac13-arm64 unsupported).
 */
const PINNED_CHROME =
	'/Users/amansaxena/Library/Caches/ms-playwright/chromium-1208/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';

export default defineConfig({
	testDir: './tests/qa',
	timeout: 90_000,
	expect: { timeout: 15_000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'tests/qa/report/html', open: 'never' } ],
		[ 'junit', { outputFile: 'tests/qa/report/junit.xml' } ],
	],
	globalSetup: require.resolve( './tests/qa/global-setup.ts' ),
	use: {
		baseURL: 'https://fk-lane-1.local',
		ignoreHTTPSErrors: true,
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
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
	outputDir: path.join( 'tests', 'qa', 'report', 'artifacts' ),
} );
