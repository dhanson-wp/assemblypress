<?php
/**
 * Member maintenance and composable directory regressions.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Blocks;
use AssemblyPress\Profile_Fields;

class MemberMaintenanceTest extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		Profile_Fields::seed_defaults();
		$_POST = array();
		$_FILES = array();
		$_GET = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		Blocks::maybe_handle_edit_profile_form_submission();
	}

	public function tear_down() {
		$_POST = array();
		$_FILES = array();
		$_GET = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		Blocks::maybe_handle_edit_profile_form_submission();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_directory_card_inherits_each_member_and_excludes_staff_by_default() {
		$first = self::factory()->user->create( array( 'display_name' => 'Card Alpha', 'role' => 'subscriber' ) );
		$second = self::factory()->user->create( array( 'display_name' => 'Card Beta', 'role' => 'subscriber' ) );
		self::factory()->user->create( array( 'display_name' => 'Staff Hidden', 'role' => 'administrator' ) );
		$output = do_blocks( '<!-- wp:assemblypress/member-directory --><!-- wp:group --><div class="wp-block-group"><!-- wp:assemblypress/profile-field {"fieldKey":"display_name"} /--></div><!-- /wp:group --><!-- /wp:assemblypress/member-directory -->' );
		$this->assertStringContainsString( '<dd>Card Alpha</dd>', $output );
		$this->assertStringContainsString( '<dd>Card Beta</dd>', $output );
		$this->assertStringNotContainsString( 'Staff Hidden', $output );
		$this->assertStringContainsString( 'Staff Hidden', do_blocks( '<!-- wp:assemblypress/member-directory {"includeAllAccounts":true} /-->' ) );
		$_GET['member_search'] = 'Card Beta';
		$this->assertStringNotContainsString( 'Card Alpha', do_blocks( '<!-- wp:assemblypress/member-directory /-->' ) );
		$_GET['member_search'] = get_userdata( $first )->user_email;
		$this->assertStringContainsString( 'No members found', do_blocks( '<!-- wp:assemblypress/member-directory /-->' ) );
	}

	public function test_invalid_upload_preserves_text_and_does_not_change_avatar() {
		$id = self::factory()->user->create();
		wp_set_current_user( $id );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array( 'assemblypress_profile_nonce' => wp_create_nonce( 'assemblypress_update_profile' ), 'assemblypress_display_name' => 'Retained name', 'assemblypress_location' => 'Unsaved location' );
		$_FILES['assemblypress_avatar'] = array( 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 3 * MB_IN_BYTES );
		Blocks::maybe_handle_edit_profile_form_submission();
		$output = do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' );
		$this->assertStringContainsString( 'role="alert"', $output );
		$this->assertStringContainsString( 'value="Unsaved location"', $output );
		$this->assertSame( '', get_user_meta( $id, 'assemblypress_location', true ) );
		$this->assertSame( '', get_user_meta( $id, 'assemblypress_avatar_id', true ) );
		$this->assertStringContainsString( 'type="file"', $output );
		$this->assertStringNotContainsString( 'type="number"', $output );
	}

	public function test_pending_removal_survives_unrelated_validation_failure() {
		$id = self::factory()->user->create();
		wp_set_current_user( $id );
		update_user_meta( $id, 'assemblypress_avatar_id', 123 );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array( 'assemblypress_profile_nonce' => wp_create_nonce( 'assemblypress_update_profile' ), 'assemblypress_display_name' => '', 'assemblypress_avatar_remove' => '1' );
		Blocks::maybe_handle_edit_profile_form_submission();
		$output = do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' );
		$this->assertStringContainsString( 'role="alert"', $output );
		$this->assertStringContainsString( 'data-removed="true"', $output );
		$this->assertStringContainsString( "checked='checked'", $output );
		$this->assertSame( 123, (int) get_user_meta( $id, 'assemblypress_avatar_id', true ) );
	}

	public function test_invalid_nonce_cannot_remove_an_existing_avatar() {
		$id = self::factory()->user->create();
		wp_set_current_user( $id );
		update_user_meta( $id, 'assemblypress_avatar_id', 123 );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array( 'assemblypress_profile_nonce' => 'invalid', 'assemblypress_avatar_remove' => '1' );
		Blocks::maybe_handle_edit_profile_form_submission();
		$this->assertSame( 123, (int) get_user_meta( $id, 'assemblypress_avatar_id', true ) );
	}
}
