process.env.WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8890';
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: './tests/e2e',
	use: {
		...baseConfig.use,
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8890',
	},
};
