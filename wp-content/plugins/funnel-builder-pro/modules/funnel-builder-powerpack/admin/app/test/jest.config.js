/**
 * Jest config for the admin app's unit tests (npm run test:unit). Jest,
 * babel-jest and the babel preset come with @wordpress/scripts; the node
 * environment replaces its jsdom preset, which these tests do not need.
 */
module.exports = {
	rootDir: '../../..',
	roots: [ '<rootDir>/admin/app/test' ],
	testEnvironment: 'node',
	transform: {
		'\\.js$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
};
