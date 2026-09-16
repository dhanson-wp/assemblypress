/**
 * Shared card editing and accessible directory navigation.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test( 'member card blocks survive an editor save and reload', async ( {
	admin,
	editor,
	page,
} ) => {
	await admin.createNewPost( {
		postType: 'page',
		title: 'Member card layout check',
	} );
	await editor.insertBlock( { name: 'assemblypress/member-directory' } );
	await expect(
		editor.canvas.getByText( 'Member card layout.', { exact: false } )
	).toBeVisible();
	await editor.saveDraft();
	await page.reload();
	await expect(
		editor.canvas.getByText( 'Member card layout.', { exact: false } )
	).toBeVisible();
	const blocks = await editor.getBlocks();
	expect( blocks[ 0 ].name ).toBe( 'assemblypress/member-directory' );
	expect(
		blocks[ 0 ].innerBlocks[ 0 ].innerBlocks.map( ( block ) => block.name )
	).toEqual( [
		'assemblypress/profile-field',
		'assemblypress/profile-field',
	] );
	await expect(
		page.getByText( 'This block contains unexpected or invalid content.' )
	).toHaveCount( 0 );
} );

test( 'directory search, pagination, and empty states work on mobile with a keyboard', async ( {
	requestUtils,
	page,
} ) => {
	const tag = `Card${ Date.now() }`;
	for ( const name of [ 'Alpha', 'Beta' ] ) {
		await requestUtils.createUser( {
			username: `${ tag }${ name }`,
			email: `${ tag }${ name }@example.test`,
			password: 'card-test-password',
			roles: [ 'subscriber' ],
		} );
	}
	const directory = await requestUtils.createPage( {
		title: 'Directory navigation check',
		status: 'publish',
		content:
			'<!-- wp:assemblypress/member-directory {"perPage":1} --><!-- wp:group --><div class="wp-block-group"><!-- wp:assemblypress/profile-field {"fieldKey":"display_name","linkToProfile":true} /--></div><!-- /wp:group --><!-- /wp:assemblypress/member-directory -->',
	} );
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( 'about:blank' );
	await page.context().clearCookies();
	await page.goto( directory.link );
	await page.getByRole( 'searchbox', { name: 'Search members' } ).fill( tag );
	await page
		.getByRole( 'searchbox', { name: 'Search members' } )
		.press( 'Enter' );
	await expect(
		page.getByRole( 'link', { name: `${ tag }Alpha`, exact: true } )
	).toBeVisible();
	await page
		.getByRole( 'navigation', { name: 'Member directory pages' } )
		.getByRole( 'link', { name: '2', exact: true } )
		.focus();
	await page.keyboard.press( 'Enter' );
	await expect(
		page.getByRole( 'link', { name: `${ tag }Beta`, exact: true } )
	).toBeVisible();
	await page
		.getByRole( 'searchbox', { name: 'Search members' } )
		.fill( 'NoSuchPublicMember' );
	await page
		.getByRole( 'searchbox', { name: 'Search members' } )
		.press( 'Enter' );
	await expect(
		page.getByText( 'No members found. Try a different name.' )
	).toBeVisible();
	expect(
		await page.evaluate(
			() => document.documentElement.scrollWidth <= window.innerWidth
		)
	).toBe( true );
} );

test( 'composable directory survives editor reload and retains search when returning from a profile', async ( {
	admin,
	editor,
	requestUtils,
	page,
} ) => {
	const tag = `Flexible${ Date.now() }`;
	await requestUtils.createUser( {
		username: tag,
		email: `${ tag }@example.test`,
		password: 'test-flexible-password',
		roles: [ 'subscriber' ],
	} );
	await admin.createNewPost( {
		postType: 'page',
		title: 'Composable directory',
	} );
	await editor.insertBlock( {
		name: 'assemblypress/member-directory',
		attributes: { perPage: 1 },
		innerBlocks: [
			{ name: 'assemblypress/member-search' },
			{ name: 'assemblypress/member-count' },
			{
				name: 'assemblypress/member-results',
				attributes: { columns: 3 },
				innerBlocks: [
					{
						name: 'assemblypress/profile-field',
						attributes: {
							fieldKey: 'display_name',
							showLabel: false,
							linkToProfile: true,
						},
					},
				],
			},
			{ name: 'assemblypress/member-empty' },
			{ name: 'assemblypress/member-pagination' },
		],
	} );
	await editor.saveDraft();
	await page.reload();
	await expect(
		page.getByText( 'This block contains unexpected or invalid content.' )
	).toHaveCount( 0 );
	const blocks = await editor.getBlocks();
	expect( blocks[ 0 ].innerBlocks.map( ( block ) => block.name ) ).toContain(
		'assemblypress/member-results'
	);
	const directory = await requestUtils.createPage( {
		title: 'Flexible discovery',
		status: 'publish',
		content:
			'<!-- wp:assemblypress/member-directory {"perPage":1} --><!-- wp:assemblypress/member-search /--><!-- wp:assemblypress/member-count /--><!-- wp:assemblypress/member-results {"columns":3} --><!-- wp:assemblypress/profile-field {"fieldKey":"display_name","showLabel":false,"linkToProfile":true} /--><!-- /wp:assemblypress/member-results --><!-- wp:assemblypress/member-empty /--><!-- wp:assemblypress/member-pagination /--><!-- /wp:assemblypress/member-directory -->',
	} );
	await page.goto( 'about:blank' );
	await page.context().clearCookies();
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( directory.link );
	await page.getByRole( 'searchbox', { name: 'Search members' } ).fill( tag );
	await page
		.getByRole( 'searchbox', { name: 'Search members' } )
		.press( 'Enter' );
	await expect( page.getByRole( 'status' ) ).toHaveText( '1 member found' );
	const returnUrl = page.url();
	await page.getByRole( 'link', { name: tag, exact: true } ).click();
	await expect(
		page.getByRole( 'heading', { name: tag, exact: true } )
	).toBeVisible();
	await page.getByRole( 'link', { name: 'Back to members' } ).click();
	await expect( page ).toHaveURL( returnUrl );
	await expect( page.getByRole( 'searchbox' ) ).toHaveValue( tag );
	expect(
		await page.evaluate(
			() => document.documentElement.scrollWidth <= window.innerWidth
		)
	).toBe( true );
	await page
		.getByRole( 'link', { name: 'Clear search and filters' } )
		.click();
	await expect( page.getByRole( 'searchbox' ) ).toHaveValue( '' );
} );

test( 'Design opens the shared profile in the native template editor', async ( {
	admin,
	page,
	editor,
} ) => {
	await admin.visitAdminPage( 'admin.php?page=assemblypress-design' );
	await page
		.getByRole( 'link', {
			name: 'Edit member profile template',
			exact: true,
		} )
		.click();
	await expect( page ).toHaveURL( /site-editor\.php/ );
	await expect(
		editor.canvas.locator( 'article.assemblypress-member-profile' )
	).toBeVisible();
	await expect(
		page.getByText( 'This block contains unexpected or invalid content.' )
	).toHaveCount( 0 );
} );
