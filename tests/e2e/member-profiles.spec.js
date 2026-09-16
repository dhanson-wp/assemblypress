/**
 * AssemblyPress first viable version E2E tests.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

function uniqueKey( prefix, testInfo ) {
	return `${ prefix }_${ testInfo.workerIndex }_${ Date.now() }`;
}

async function createProfileField( requestUtils, payload ) {
	return requestUtils.rest( {
		path: '/assemblypress/v1/profile-fields',
		method: 'POST',
		data: payload,
	} );
}

async function getProfileGroupByKey( requestUtils, key ) {
	const groups = await requestUtils.rest( {
		path: '/assemblypress/v1/profile-field-groups',
	} );

	return groups.find( ( group ) => group.key === key );
}

async function loginAsUser( page, user ) {
	await page.context().clearCookies();
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( user.username );
	await page.getByLabel( 'Password', { exact: true } ).fill( user.password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
}

test.describe( 'AssemblyPress member profiles', () => {
	test( 'admin can open the AssemblyPress screen', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'admin.php?page=assemblypress-fields' );
		await expect(
			page.getByRole( 'heading', { name: 'Profile Fields' } )
		).toBeVisible();
	} );

	test( 'admin can create a profile section and field', async ( {
		admin,
		page,
		requestUtils,
	}, testInfo ) => {
		const groupKey = uniqueKey( 'community', testInfo );
		const groupLabel = `Community ${ groupKey }`;
		const suffix = uniqueKey( 'e2e', testInfo );
		const fieldKey = `favorite_topic_${ suffix }`;
		const fieldLabel = `Favorite topic ${ suffix }`;

		await admin.visitAdminPage( 'admin.php?page=assemblypress-fields' );
		await expect(
			page.getByRole( 'heading', { name: 'Profile Fields' } )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Add section' } ).click();

		const sectionDetail = page.locator( '.assemblypress-section-detail' );
		await sectionDetail.getByLabel( 'Label' ).fill( groupLabel );
		await sectionDetail.getByLabel( 'Key' ).fill( groupKey );
		await sectionDetail
			.getByLabel( 'Description' )
			.fill( 'Community metadata.' );
		await sectionDetail.getByRole( 'button', { name: 'Create' } ).click();

		await expect(
			page.getByRole( 'button', { name: new RegExp( groupLabel ) } )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await page
			.getByRole( 'menuitem', { name: 'Text', exact: true } )
			.click();

		const fieldDialog = page.getByRole( 'dialog', {
			name: 'Create profile field',
		} );
		await fieldDialog.getByLabel( 'Label' ).fill( fieldLabel );
		await fieldDialog.getByRole( 'button', { name: 'Create' } ).click();

		await expect( fieldDialog ).toBeHidden();
		await expect( page.getByText( fieldLabel ) ).toBeVisible();

		const fields = await requestUtils.rest( {
			path: '/assemblypress/v1/profile-fields',
		} );
		expect(
			fields.some(
				( field ) =>
					field.key === fieldKey && field.label === fieldLabel
			)
		).toBe( true );
	} );

	test( 'member edit and public profile blocks complete the profile loop', async ( {
		page,
		requestUtils,
	}, testInfo ) => {
		const suffix = uniqueKey( 'loop', testInfo );
		const privateKey = `${ suffix }_private_note`;
		const privateLabel = `Private note ${ suffix }`;
		const password = 'password';
		const memberCredentials = {
			username: `${ suffix }_member`,
			email: `${ suffix }_member@example.test`,
			password,
			roles: [ 'subscriber' ],
		};
		const member = await requestUtils.createUser( memberCredentials );
		const aboutGroup = await getProfileGroupByKey( requestUtils, 'about' );

		await createProfileField( requestUtils, {
			key: privateKey,
			label: privateLabel,
			type: 'text',
			visibility: 'private',
			editable: true,
			required: false,
			group_id: aboutGroup.id,
			order: 900,
		} );

		const profilePage = await requestUtils.createPage( {
			status: 'publish',
			title: `AssemblyPress loop ${ suffix }`,
			content: [
				'<!-- wp:assemblypress/edit-profile-form /-->',
				`<!-- wp:assemblypress/member-profile {"userId":${ member.id }} /-->`,
				`<!-- wp:assemblypress/profile-field {"fieldKey":"${ privateKey }","userId":${ member.id }} /-->`,
			].join( '\n\n' ),
		} );

		await loginAsUser( page, memberCredentials );
		await page.goto( `/?page_id=${ profilePage.id }` );

		await page.getByLabel( 'Display name' ).fill( 'Mina Profile' );
		await page.getByLabel( 'Bio' ).fill( 'AssemblyPress member loop.' );
		await page.getByLabel( 'Location' ).fill( 'Toronto' );
		await page
			.getByLabel( privateLabel, { exact: true } )
			.fill( 'Only the member can read this.' );
		await page.getByRole( 'button', { name: 'Save profile' } ).click();

		await expect( page.getByRole( 'status' ) ).toContainText(
			'Profile saved.'
		);
		await expect( page.getByLabel( 'Location' ) ).toHaveValue( 'Toronto' );
		await expect(
			page
				.getByRole( 'article' )
				.getByText( 'AssemblyPress member loop.' )
		).toBeVisible();

		await page.context().clearCookies();
		await page.goto( `/?page_id=${ profilePage.id }` );

		await expect( page.getByText( 'Mina Profile' ) ).toBeVisible();
		await expect( page.getByText( 'Toronto' ) ).toBeVisible();
		await expect(
			page.getByText( 'Only the member can read this.' )
		).toHaveCount( 0 );
		await expect(
			page.getByText( 'Log in to edit your profile.' )
		).toBeVisible();
		await expect(
			page.getByLabel( 'Username or Email Address' )
		).toBeVisible();
		await expect(
			page.getByLabel( 'Password', { exact: true } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Save profile' } )
		).toHaveCount( 0 );
		const editProfileUrl = page.url();
		await page
			.getByLabel( 'Username or Email Address' )
			.fill( memberCredentials.username );
		await page.getByLabel( 'Password', { exact: true } ).fill( password );
		await page
			.getByRole( 'button', { name: 'Log In', exact: true } )
			.click();
		await expect( page ).toHaveURL( editProfileUrl );
		await expect( page.getByLabel( 'Location' ) ).toHaveValue( 'Toronto' );
		await expect(
			page.getByRole( 'button', { name: 'Log In', exact: true } )
		).toHaveCount( 0 );
	} );
} );
