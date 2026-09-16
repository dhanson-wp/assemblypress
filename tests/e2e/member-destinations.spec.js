/**
 * Member destinations work without per-member pages or a demo helper.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test( 'member destinations resolve context, login, editing, and missing users', async ( {
	page,
	requestUtils,
}, testInfo ) => {
	const suffix = `${ testInfo.workerIndex }_${ Date.now() }`;
	const credentials = {
		username: `destination_${ suffix }`,
		email: `destination_${ suffix }@example.test`,
		password: 'test-destination-password',
		roles: [ 'subscriber' ],
	};
	const member = await requestUtils.createUser( credentials );
	const other = await requestUtils.createUser( {
		username: `other_${ suffix }`,
		email: `other_${ suffix }@example.test`,
		password: 'test-other-password',
		roles: [ 'subscriber' ],
	} );
	const profile = await requestUtils.rest( {
		path: `/assemblypress/v1/members/${ member.id }`,
	} );
	const otherProfile = await requestUtils.rest( {
		path: `/assemblypress/v1/members/${ other.id }`,
	} );
	const editUrl = ( url ) => {
		const parsed = new URL( url );
		if ( parsed.searchParams.has( 'assemblypress_profile' ) ) {
			parsed.searchParams.set( 'assemblypress_edit', '1' );
		} else {
			parsed.pathname = `${ parsed.pathname.replace( /\/$/, '' ) }/edit/`;
		}
		return parsed.href;
	};
	await page.context().clearCookies();
	let response = await page.goto( profile.profile_url );
	expect( response.status() ).toBe( 200 );
	await expect(
		page.getByRole( 'heading', { name: credentials.username, exact: true } )
	).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: 'Edit my profile', exact: true } )
	).toHaveCount( 0 );
	await page.goto( editUrl( profile.profile_url ) );
	await expect(
		page.getByRole( 'button', { name: 'Save profile' } )
	).toHaveCount( 0 );
	await page
		.getByLabel( 'Username or Email Address' )
		.fill( credentials.username );
	await page
		.getByLabel( 'Password', { exact: true } )
		.fill( credentials.password );
	await page.getByRole( 'button', { name: 'Log In', exact: true } ).click();
	await expect( page ).toHaveURL( editUrl( profile.profile_url ) );
	await page
		.getByLabel( 'Location', { exact: true } )
		.fill( 'Destination location' );
	await page
		.locator( '.assemblypress-profile-image input[type="file"]' )
		.setInputFiles( {
			name: 'invalid.png',
			mimeType: 'image/png',
			buffer: Buffer.from( 'This is not an image' ),
		} );
	await page
		.getByRole( 'button', { name: 'Save profile', exact: true } )
		.click();
	await expect( page.getByRole( 'alert' ) ).toContainText( 'Choose a JPEG' );
	await expect( page.getByLabel( 'Location', { exact: true } ) ).toHaveValue(
		'Destination location'
	);
	await page
		.locator( '.assemblypress-profile-image input[type="file"]' )
		.setInputFiles( {
			name: 'member-pixel.png',
			mimeType: 'image/png',
			buffer: Buffer.from(
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6QAAAABJRU5ErkJggg==',
				'base64'
			),
		} );

	await page
		.getByRole( 'button', { name: 'Save profile', exact: true } )
		.click();
	await expect( page.getByRole( 'status' ) ).toContainText(
		'Profile saved.'
	);
	await expect(
		page.getByRole( 'img', { name: 'Current profile image' } )
	).toBeVisible();
	await page
		.getByRole( 'link', { name: 'View my profile', exact: true } )
		.click();
	await expect(
		page.getByText( 'Destination location', { exact: true } )
	).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: 'Edit my profile', exact: true } )
	).toBeVisible();

	await page.goto( editUrl( profile.profile_url ) );
	await page.getByRole( 'button', { name: 'Remove', exact: true } ).click();
	await expect( page.getByRole( 'status' ) ).toContainText(
		'Image will be removed when you save'
	);
	await page.getByLabel( 'Display name', { exact: false } ).fill( '' );
	await page
		.locator( 'form.assemblypress-edit-profile-form' )
		.evaluate( ( form ) => {
			form.noValidate = true;
		} );
	await page
		.getByRole( 'button', { name: 'Save profile', exact: true } )
		.click();
	await expect( page.getByRole( 'alert' ) ).toContainText(
		'Display name is required'
	);
	await expect( page.getByRole( 'status' ) ).toContainText(
		'Image will be removed when you save'
	);
	await page.getByRole( 'button', { name: 'Undo', exact: true } ).click();
	await expect(
		page.getByRole( 'img', { name: 'Current profile image' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'button', {
			name: 'Replace Profile image',
			exact: true,
		} )
	).toBeFocused();
	await page
		.getByLabel( 'Display name', { exact: false } )
		.fill( credentials.username );
	await page.getByRole( 'button', { name: 'Remove', exact: true } ).click();
	await page
		.getByRole( 'button', { name: 'Save profile', exact: true } )
		.click();
	await expect( page.getByRole( 'status' ) ).toContainText(
		'Profile saved.'
	);
	await expect(
		page.getByRole( 'img', { name: 'Current profile image' } )
	).toHaveCount( 0 );
	response = await page.goto( editUrl( otherProfile.profile_url ) );
	expect( response.status() ).toBe( 403 );
	await expect(
		page.getByText( 'You can only edit your own profile.', {
			exact: false,
		} )
	).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Save profile' } )
	).toHaveCount( 0 );
	const missing = new URL( profile.profile_url );
	missing.pathname = '/';
	missing.search =
		'?author_name=assemblypress-missing-member&assemblypress_profile=1';
	response = await page.goto( missing.href );
	expect( response.status() ).toBe( 404 );
	await expect(
		page.getByRole( 'heading', { name: credentials.username, exact: true } )
	).toHaveCount( 0 );
} );

test( 'one customized template applies to different members and field context', async ( {
	page,
	requestUtils,
}, testInfo ) => {
	const suffix = `${ testInfo.workerIndex }_${ Date.now() }`;
	const templates = await requestUtils.rest( {
		path: '/wp/v2/templates',
		params: { context: 'edit' },
	} );
	const template = templates.find(
		( item ) => item.slug === 'assemblypress-member-profile'
	);
	expect( template ).toBeTruthy();
	test.skip(
		template.source !== 'plugin',
		'Preserve existing user template customizations.'
	);
	const members = [];
	for ( const label of [ 'first', 'second' ] ) {
		const member = await requestUtils.createUser( {
			username: `${ label }_${ suffix }`,
			email: `${ label }_${ suffix }@example.test`,
			password: 'test-template-password',
			roles: [ 'subscriber' ],
		} );
		members.push(
			await requestUtils.rest( {
				path: `/assemblypress/v1/members/${ member.id }`,
			} )
		);
	}
	try {
		await requestUtils.rest( {
			path: `/wp/v2/templates/${ template.id }`,
			method: 'POST',
			data: {
				content:
					'<!-- wp:paragraph --><p>Shared template proof</p><!-- /wp:paragraph -->\n<!-- wp:assemblypress/member-profile /-->\n<!-- wp:assemblypress/profile-field {"fieldKey":"display_name"} /-->',
			},
		} );
		await page.context().clearCookies();
		for ( const member of members ) {
			await page.goto( member.profile_url );
			await expect(
				page.getByText( 'Shared template proof', { exact: true } )
			).toBeVisible();
			await expect(
				page.getByRole( 'heading', {
					name: member.display_name,
					exact: true,
				} )
			).toBeVisible();
			await expect(
				page.locator(
					'.wp-block-assemblypress-profile-field .assemblypress-profile-field__item dd'
				)
			).toHaveText( member.display_name );
		}
	} finally {
		await requestUtils.rest( {
			path: `/wp/v2/templates/${ template.id }`,
			params: { force: true },
			method: 'DELETE',
		} );
	}
} );
