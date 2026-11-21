const path = require( 'path' );
const { defineConfig, transformWithOxc } = require( 'vite' );

const rootDir = __dirname;
const outputDir = path.resolve( rootDir, 'assets' );

const scriptEntries = [
	{
		input: 'assets-src/rules-settings/js/index.jsx',
		name: 'FlexibleShippingRulesSettings',
		output: 'js/rules-settings.js',
	},
	{
		input: 'assets-src/onboarding/js/index.jsx',
		name: 'FlexibleShippingOnboarding',
		output: 'js/onboarding.js',
	},
	{
		input: 'assets-src/js/new-rules-table-popup.js',
		name: 'FlexibleShippingNewRulesTablePopup',
		output: 'js/new-rules-table-popup.js',
	},
];

const styleEntries = [
	{
		input: 'assets-src/rules-settings/scss/style.scss',
		output: 'css/rules-settings.css',
	},
	{
		input: 'assets-src/onboarding/scss/style.scss',
		output: 'css/onboarding.css',
	},
	{
		input: 'assets-src/admin/scss/admin.scss',
		output: 'css/admin.css',
	},
	{
		input: 'assets-src/scss/new-rules-table-popup.scss',
		output: 'css/new-rules-table-popup.css',
	},
	{
		input: 'assets-src/free-shipping/scss/style.scss',
		output: 'dist/css/free-shipping.css',
	},
];

function createBuildConfig( entry, options ) {
	const isScript = Boolean( entry.name );
	const production = Boolean( options.production );
	const watch = Boolean( options.watch );

	return defineConfig( {
		root: rootDir,
		publicDir: false,
		mode: production ? 'production' : 'development',
		plugins: [
			{
				name: 'flexible-shipping-jsx-in-js',
				async transform( code, id ) {
					if ( ! id.includes( `${ path.sep }assets-src${ path.sep }` ) || ! id.endsWith( '.js' ) ) {
						return null;
					}

					if ( id.includes( `${ path.sep }assets-src${ path.sep }blocks${ path.sep }` ) ) {
						return null;
					}

					return transformWithOxc( code, id.replace( /\.js$/, '.jsx' ), {
						loader: 'jsx',
					} );
				},
			},
			{
				name: 'flexible-shipping-remove-empty-style-chunks',
				generateBundle( outputOptions, bundle ) {
					if ( isScript ) {
						return;
					}

					Object.keys( bundle ).forEach( ( fileName ) => {
						const output = bundle[ fileName ];

						if ( output.type === 'chunk' && output.code.trim() === '' ) {
							delete bundle[ fileName ];
						}
					} );
				},
			},
		],
		css: {
			preprocessorOptions: {
				scss: {
					includePaths: [
						path.resolve( rootDir, 'node_modules' ),
					],
				},
			},
		},
		build: {
			copyPublicDir: false,
			cssCodeSplit: true,
			cssMinify: production,
			emptyOutDir: false,
			manifest: false,
			minify: production ? 'terser' : false,
			outDir: outputDir,
			rollupOptions: {
				external: isScript ? [ '@wordpress/i18n' ] : [],
				input: path.resolve( rootDir, entry.input ),
				output: {
					assetFileNames: entry.output,
					entryFileNames: isScript ? entry.output : '_empty/[name].js',
					format: isScript ? 'iife' : 'es',
					globals: {
						'@wordpress/i18n': 'wp.i18n',
					},
					name: entry.name,
				},
			},
			terserOptions: production ? {
				compress: {
					drop_console: true,
					pure_funcs: [
						'console.debug',
						'console.error',
						'console.info',
						'console.log',
						'console.warn',
						'window.console.debug',
						'window.console.error',
						'window.console.info',
						'window.console.log',
						'window.console.warn',
					],
				},
				format: {
					comments: false,
				},
			} : undefined,
			watch: watch ? {} : null,
		},
	} );
}

function createViteBuilds( options = {} ) {
	return [
		...scriptEntries,
		...styleEntries,
	].map( ( entry ) => createBuildConfig( entry, options ) );
}

module.exports = createBuildConfig( scriptEntries[0], {
	production: process.env.NODE_ENV === 'production',
} );
module.exports.createViteBuilds = createViteBuilds;
