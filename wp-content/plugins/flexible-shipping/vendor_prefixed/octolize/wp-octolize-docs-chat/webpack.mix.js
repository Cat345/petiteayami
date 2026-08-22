let mix = require( 'laravel-mix' );

mix.setPublicPath( 'assets/dist' );

mix.options( {
	processCssUrls: false,
	cssNano: {
		discardComments: {
			removeAll: true,
		},
	},
	manifest: false,
	terser: {
		extractComments: false,
		terserOptions: {
			compress: {
				drop_console: true
			},
			output: {
				comments: false,
			},
		}
	}
} );

// Docs chat
mix.js( [ 'assets-src/js/publicPath.js', 'assets-src/js/OctolizeDocsChat.jsx' ], 'OctolizeDocsChat.js' );

// Theme CSS,
mix.sass( 'assets-src/scss/OctolizeDocsChat.scss', 'OctolizeDocsChat.css' );
