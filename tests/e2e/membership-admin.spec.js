/** Owner configuration, membership lifecycle, and composable discovery. */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { execFileSync } from 'node:child_process';
import { join as joinPath } from 'node:path';

function confirmationLink( id ) {
	const code = `$mail = ''; add_filter('pre_wp_mail', function($r, $a) use (&$mail) { $mail = $a['message']; return true; }, 10, 2); $record = AssemblyPress\\Membership::get(${ Number(
		id
	) }); AssemblyPress\\Membership::send_token(${ Number(
		id
	) }, $record['state'] === 'invited' ? 'invite' : 'activate'); preg_match('~https?://[^\\s]+~', $mail, $matches); echo 'AP_LINK=' . $matches[0];`;
	const output = execFileSync(
		'npx',
		[ 'wp-env', 'run', 'cli', 'wp', 'eval', code ],
		{
			encoding: 'utf8',
			env: {
				...process.env,
				PATH: process.env.PATH,
			},
		}
	);
	return output.match( /AP_LINK=(https?:\/\/[^\s\x1b]+)/ )[ 1 ];
}

const mailFixture = `<?php
add_action('template_redirect', static function() {
 $email = $_POST['ap_email'] ?? '';
 if (!empty($_POST['ap_record'])) {
  $record = AssemblyPress\\Membership::get((int) $_POST['ap_record']);
  $email = is_wp_error($record) ? '' : $record['email'];
 }
 if (is_string($email) && preg_match('/^(joining_|invitation_)[^@]+@example\\.test$/', $email)) {
  $_SERVER['REMOTE_ADDR'] = '2001:db8:' . implode(':', str_split(substr(hash('sha256', $email), 0, 24), 4));
 }
}, -1);
add_filter('pre_wp_mail', static function($result, $mail) {
 foreach ((array) $mail['to'] as $to) {
  if (preg_match('/^(joining_|invitation_)[^@]+@example\\.test$/', $to)) { return true; }
 }
 return $result;
}, 10, 2);
`;
function mailCapture( install ) {
	const code = install
		? `if (file_exists(WPMU_PLUGIN_DIR . '/assemblypress-membership-e2e.php')) { throw new Exception('Mail fixture already exists'); } wp_mkdir_p(WPMU_PLUGIN_DIR); file_put_contents(WPMU_PLUGIN_DIR . '/assemblypress-membership-e2e.php', base64_decode('${ Buffer.from(
				mailFixture
		  ).toString( 'base64' ) }'));`
		: `if (file_exists(WPMU_PLUGIN_DIR . '/assemblypress-membership-e2e.php')) { unlink(WPMU_PLUGIN_DIR . '/assemblypress-membership-e2e.php'); }`;
	execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', 'eval', code ], {
		env: {
			...process.env,
			PATH: process.env.PATH,
		},
	} );
}

test.describe( 'Membership owner workflows', () => {
	let original;
	test.beforeAll( () => mailCapture( true ) );
	test.afterAll( () => mailCapture( false ) );
	test.beforeEach( async ( { requestUtils } ) => {
		original = (
			await requestUtils.rest( { path: '/assemblypress/v1/settings' } )
		).settings;
	} );
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			path: '/assemblypress/v1/settings',
			method: 'POST',
			data: original,
		} );
	} );

	test( 'owner saves settings, finds a member, and edits profile values', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const suffix = Date.now();
		const member = await requestUtils.createUser( {
			username: `ownercheck_${ suffix }`,
			email: `ownercheck_${ suffix }@example.test`,
			password: 'test-only-password',
			roles: [ 'subscriber' ],
		} );
		await admin.visitAdminPage( 'admin.php?page=assemblypress-settings' );
		await page
			.getByLabel( 'How can people join?' )
			.selectOption( 'approval' );
		await page
			.getByRole( 'button', { name: 'Save settings', exact: true } )
			.click();
		await expect(
			page
				.locator( '#assemblypress-admin-root' )
				.getByText( 'Settings saved.', { exact: true } )
		).toBeVisible();
		expect(
			(
				await requestUtils.rest( {
					path: '/assemblypress/v1/settings',
				} )
			).settings.joining
		).toBe( 'approval' );
		await page.screenshot( {
			path: joinPath(
				process.env.WP_ARTIFACTS_PATH || test.info().outputDir,
				'settings.png'
			),
			fullPage: true,
		} );
		await admin.visitAdminPage( 'admin.php?page=assemblypress' );
		await expect(
			page.getByRole( 'heading', { name: 'Members', exact: true } )
		).toBeVisible();
		await page.getByRole( 'searchbox' ).fill( `ownercheck_${ suffix }` );
		const row = page
			.getByRole( 'row' )
			.filter( { hasText: `ownercheck_${ suffix }` } );
		await expect( row ).toBeVisible();
		await page.screenshot( {
			path: joinPath(
				process.env.WP_ARTIFACTS_PATH || test.info().outputDir,
				'members.png'
			),
			fullPage: true,
		} );
		await row
			.getByRole( 'button', { name: 'Actions', exact: true } )
			.click();
		await page
			.getByRole( 'menuitem', { name: 'Edit profile', exact: true } )
			.click();
		await page
			.getByRole( 'dialog' )
			.getByLabel( 'Location', { exact: true } )
			.fill( 'Owner edited location' );
		await page
			.getByRole( 'button', { name: 'Save profile', exact: true } )
			.click();
		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
		const profile = await requestUtils.rest( {
			path: `/assemblypress/v1/admin/members/${ member.id }`,
		} );
		expect(
			profile.groups
				.flatMap( ( group ) => group.fields )
				.find( ( field ) => field.key === 'location' ).value
		).toBe( 'Owner edited location' );
	} );

	test( 'registration waits for email and approval, then creates a member', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const suffix = Date.now();
		const adminCookies = await page.context().cookies();
		await requestUtils.rest( {
			path: '/assemblypress/v1/settings',
			method: 'POST',
			data: { joining: 'approval' },
		} );
		const join = await requestUtils.createPage( {
			title: 'Join test',
			status: 'publish',
			content: '<!-- wp:assemblypress/registration /-->',
		} );
		await page.context().clearCookies();
		await page.goto( join.link );
		await page
			.getByLabel( 'Email address', { exact: true } )
			.first()
			.fill( `joining_${ suffix }@example.test` );
		await page
			.getByLabel( 'Username', { exact: true } )
			.fill( `joining_${ suffix }` );
		await page
			.getByLabel( 'Display name', { exact: true } )
			.fill( 'Joining person' );
		await page
			.getByRole( 'button', { name: 'Request membership' } )
			.click();
		await expect( page.getByRole( 'status' ) ).toContainText(
			'If these details can be registered'
		);
		const records = await requestUtils.rest( {
			path: '/assemblypress/v1/admin/membership',
			params: { search: `joining_${ suffix }@example.test` },
		} );
		expect( records.items[ 0 ].state ).toBe( 'pending_email' );
		const link = confirmationLink( records.items[ 0 ].id );
		await page.goto( link );
		await page
			.getByRole( 'button', { name: 'Confirm membership' } )
			.click();
		await expect( page.getByRole( 'status' ) ).toContainText(
			'site owner will review'
		);
		await page.context().addCookies( adminCookies );
		await admin.visitAdminPage( 'admin.php?page=assemblypress-membership' );
		await page
			.getByRole( 'searchbox' )
			.fill( `joining_${ suffix }@example.test` );
		const row = page
			.getByRole( 'row' )
			.filter( { hasText: `joining_${ suffix }@example.test` } );
		await expect( row ).toContainText( 'Awaiting approval' );
		await row
			.getByRole( 'button', { name: 'Actions', exact: true } )
			.click();
		await page
			.getByRole( 'menuitem', { name: 'Approve', exact: true } )
			.click();
		await expect( row ).toContainText( 'Account created' );
		await page.screenshot( {
			path: joinPath(
				process.env.WP_ARTIFACTS_PATH || test.info().outputDir,
				'requests.png'
			),
			fullPage: true,
		} );
	} );

	test( 'owner invitation is accepted once for its intended email', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const suffix = Date.now();
		await admin.visitAdminPage( 'admin.php?page=assemblypress-membership' );
		await page
			.getByRole( 'button', { name: 'Invite member', exact: true } )
			.click();
		await page
			.getByRole( 'dialog' )
			.getByLabel( 'Email address' )
			.fill( `invitation_${ suffix }@example.test` );
		await page.getByRole( 'button', { name: 'Send invitation' } ).click();
		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
		const records = await requestUtils.rest( {
			path: '/assemblypress/v1/admin/membership',
			params: { search: `invitation_${ suffix }@example.test` },
		} );
		const link = confirmationLink( records.items[ 0 ].id );
		await page.context().clearCookies();
		await page.goto( link );
		await page
			.getByLabel( 'Username', { exact: true } )
			.fill( `invitation_${ suffix }` );
		await page
			.getByLabel( 'Display name', { exact: true } )
			.fill( 'Invited person' );
		await page
			.getByRole( 'button', { name: 'Confirm membership' } )
			.click();
		await expect( page.getByRole( 'status' ) ).toContainText(
			'membership is ready'
		);
		await page.goto( link );
		await expect(
			page.getByText( 'This link has expired or has already been used.', {
				exact: false,
			} )
		).toBeVisible();
	} );
} );
