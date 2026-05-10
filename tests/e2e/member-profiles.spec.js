/**
 * AssemblyPress first-slice E2E placeholders.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'AssemblyPress member profiles', () => {
	test( 'admin can open the AssemblyPress screen', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'admin.php?page=assemblypress' );
		await expect( page.getByRole( 'heading', { name: 'AssemblyPress' } ) ).toBeVisible();
	} );
} );
