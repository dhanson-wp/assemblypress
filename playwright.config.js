const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: './tests/e2e',
	use: {
		...baseConfig.use,
		baseURL: 'http://localhost:8890',
	},
};
