<?php
/**
 * Profile field tests.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Profile_Fields;
use AssemblyPress\Profile_Field_Groups;
use AssemblyPress\Rest_Controller;

/**
 * Profile field registry tests.
 */
class ProfileFieldsTest extends WP_UnitTestCase {
	/**
	 * Reset request state after block form tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_POST['assemblypress_profile_nonce'] );
		unset( $_POST['assemblypress_display_name'] );
		unset( $_POST['assemblypress_bio'] );
		unset( $_POST['assemblypress_location'] );
		unset( $_POST['assemblypress_website'] );
		unset( $_POST['assemblypress_avatar'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Default profile fields include required community identity fields.
	 *
	 * @return void
	 */
	public function test_default_fields_include_member_identity_fields() {
		$keys = wp_list_pluck( Profile_Fields::default_fields(), 'key' );

		$this->assertContains( 'display_name', $keys );
		$this->assertContains( 'bio', $keys );
		$this->assertContains( 'location', $keys );
		$this->assertContains( 'website', $keys );
		$this->assertContains( 'social_links', $keys );
	}

	/**
	 * Protected identity fields expose their system status.
	 *
	 * @return void
	 */
	public function test_user_backed_default_fields_are_system_fields() {
		Profile_Fields::seed_defaults();

		$display_name = Profile_Fields::get_field( 'display_name' );
		$bio          = Profile_Fields::get_field( 'bio' );
		$website      = Profile_Fields::get_field( 'website' );
		$location     = Profile_Fields::get_field( 'location' );

		$this->assertTrue( $display_name['is_system'] );
		$this->assertTrue( $bio['is_system'] );
		$this->assertTrue( $website['is_system'] );
		$this->assertFalse( $location['is_system'] );
	}

	/**
	 * Starter groups are created and default fields are assigned by product area.
	 *
	 * @return void
	 */
	public function test_starter_groups_and_default_field_assignments() {
		Profile_Fields::seed_defaults();

		$groups = Profile_Field_Groups::get_groups();
		$keys   = wp_list_pluck( $groups, 'key' );

		$this->assertContains( 'base', $keys );
		$this->assertContains( 'contact', $keys );
		$this->assertContains( 'about', $keys );
		$this->assertContains( 'social', $keys );

		$groups_by_key = array();
		foreach ( $groups as $group ) {
			$groups_by_key[ $group['key'] ] = $group;
		}

		$this->assertSame( (int) $groups_by_key['base']['id'], (int) Profile_Fields::get_field( 'display_name' )['group_id'] );
		$this->assertSame( (int) $groups_by_key['base']['id'], (int) Profile_Fields::get_field( 'avatar' )['group_id'] );
		$this->assertSame( (int) $groups_by_key['contact']['id'], (int) Profile_Fields::get_field( 'website' )['group_id'] );
		$this->assertSame( (int) $groups_by_key['about']['id'], (int) Profile_Fields::get_field( 'bio' )['group_id'] );
		$this->assertSame( (int) $groups_by_key['about']['id'], (int) Profile_Fields::get_field( 'location' )['group_id'] );
		$this->assertSame( (int) $groups_by_key['social']['id'], (int) Profile_Fields::get_field( 'social_links' )['group_id'] );
	}

	/**
	 * Field groups support CRUD and duplicate key protection.
	 *
	 * @return void
	 */
	public function test_field_group_crud_and_duplicate_keys() {
		$created = Profile_Field_Groups::create_group(
			array(
				'key'         => 'membership',
				'label'       => 'Membership',
				'description' => 'Membership details.',
				'order'       => 90,
			)
		);

		$this->assertNotWPError( $created );
		$this->assertSame( 'membership', $created['key'] );
		$this->assertSame( 'Membership', $created['label'] );
		$this->assertSame( 90, $created['order'] );

		$duplicate = Profile_Field_Groups::create_group(
			array(
				'key'   => 'membership',
				'label' => 'Duplicate Membership',
			)
		);

		$this->assertWPError( $duplicate );
		$this->assertSame( 'assemblypress_duplicate_group_key', $duplicate->get_error_code() );

		$updated = Profile_Field_Groups::update_group(
			$created['id'],
			array_merge(
				$created,
				array(
					'label' => 'Member details',
					'order' => 95,
				)
			)
		);

		$this->assertNotWPError( $updated );
		$this->assertSame( 'Member details', $updated['label'] );
		$this->assertSame( 95, $updated['order'] );

		$deleted = Profile_Field_Groups::delete_group( $created['id'] );

		$this->assertSame( array( 'deleted' => true ), $deleted );
		$this->assertNull( get_post( $created['id'] ) );
	}

	/**
	 * Groups with assigned fields cannot be deleted.
	 *
	 * @return void
	 */
	public function test_group_delete_is_blocked_when_fields_are_assigned() {
		Profile_Fields::seed_defaults();

		$base   = Profile_Field_Groups::get_group( 'base' );
		$result = Profile_Field_Groups::delete_group( $base['id'] );

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_group_not_empty', $result->get_error_code() );
		$this->assertNotNull( get_post( $base['id'] ) );
	}

	/**
	 * The earlier Identity starter group migrates to the BuddyPress-familiar Base group.
	 *
	 * @return void
	 */
	public function test_legacy_identity_group_migrates_to_base() {
		$identity = Profile_Field_Groups::create_group(
			array(
				'key'   => 'identity',
				'label' => 'Identity',
				'order' => 10,
			)
		);

		$this->assertNotWPError( $identity );

		Profile_Field_Groups::ensure_starter_groups();

		$this->assertNull( Profile_Field_Groups::get_group( 'identity' ) );

		$base = Profile_Field_Groups::get_group( 'base' );

		$this->assertNotNull( $base );
		$this->assertSame( (int) $identity['id'], (int) $base['id'] );
		$this->assertSame( 'Base', $base['label'] );
	}

	/**
	 * The earliest empty Profile placeholder group is removed.
	 *
	 * @return void
	 */
	public function test_empty_legacy_profile_group_is_removed() {
		$profile = Profile_Field_Groups::create_group(
			array(
				'key'   => 'profile',
				'label' => 'Profile',
				'order' => 5,
			)
		);

		$this->assertNotWPError( $profile );

		Profile_Field_Groups::ensure_starter_groups();

		$this->assertNull( Profile_Field_Groups::get_group( 'profile' ) );
		$this->assertNull( get_post( $profile['id'] ) );
	}

	/**
	 * Protected identity fields cannot be deleted.
	 *
	 * @return void
	 */
	public function test_system_field_delete_is_blocked() {
		Profile_Fields::seed_defaults();

		$field  = Profile_Fields::get_field( 'display_name' );
		$result = Profile_Fields::delete_field( $field['id'] );

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_system_field_delete_forbidden', $result->get_error_code() );
		$this->assertNotNull( get_post( $field['id'] ) );
	}

	/**
	 * Protected identity fields preserve their storage contract on update.
	 *
	 * @return void
	 */
	public function test_system_field_update_preserves_structural_properties() {
		Profile_Fields::seed_defaults();

		$field  = Profile_Fields::get_field( 'display_name' );
		$result = Profile_Fields::update_field(
			$field['id'],
			array(
				'key'         => 'renamed_display_name',
				'label'       => 'Public name',
				'type'        => 'email',
				'source'      => 'meta',
				'user_key'    => 'user_email',
				'visibility'  => 'private',
				'editable'    => false,
				'required'    => false,
				'description' => 'Shown on member profiles.',
				'order'       => 99,
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 'display_name', $result['key'] );
		$this->assertSame( 'text', $result['type'] );
		$this->assertSame( 'user', $result['source'] );
		$this->assertSame( 'display_name', $result['user_key'] );
		$this->assertTrue( $result['is_system'] );
		$this->assertSame( 'Public name', $result['label'] );
		$this->assertSame( 'private', $result['visibility'] );
		$this->assertFalse( $result['editable'] );
		$this->assertFalse( $result['required'] );
		$this->assertSame( 'Shown on member profiles.', $result['description'] );
		$this->assertSame( 99, $result['order'] );
	}

	/**
	 * Custom fields support CRUD, select options, and ordering.
	 *
	 * @return void
	 */
	public function test_custom_field_crud_select_options_and_ordering() {
		Profile_Field_Groups::ensure_starter_groups();
		$contact_group = Profile_Field_Groups::get_group( 'contact' );

		$created = Profile_Fields::create_field(
			array(
				'key'        => 'preferred_contact',
				'label'      => 'Preferred Contact',
				'type'       => 'select',
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 70,
				'group_id'   => $contact_group['id'],
				'options'    => array(
					array(
						'value' => 'email',
						'label' => 'Email',
					),
					array(
						'value' => 'phone',
						'label' => 'Phone',
					),
				),
			)
		);

		$this->assertNotWPError( $created );
		$this->assertFalse( $created['is_system'] );
		$this->assertSame( 'preferred_contact', $created['key'] );
		$this->assertSame( 70, $created['order'] );
		$this->assertSame( (int) $contact_group['id'], (int) $created['group_id'] );
		$this->assertSame( 'email', $created['options'][0]['value'] );

		$updated = Profile_Fields::update_field(
			$created['id'],
			array_merge(
				$created,
				array(
					'order'   => 20,
					'options' => array(
						array(
							'value' => 'sms',
							'label' => 'SMS',
						),
					),
				)
			)
		);

		$this->assertNotWPError( $updated );
		$this->assertSame( 20, $updated['order'] );
		$this->assertSame( (int) $contact_group['id'], (int) $updated['group_id'] );
		$this->assertSame( 'sms', $updated['options'][0]['value'] );

		$deleted = Profile_Fields::delete_field( $created['id'] );

		$this->assertSame( array( 'deleted' => true ), $deleted );
		$this->assertNull( get_post( $created['id'] ) );
	}

	/**
	 * Member profiles expose grouped fields while preserving the flat fields array.
	 *
	 * @return void
	 */
	public function test_member_profile_includes_grouped_fields_and_flat_fields() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Ada Lovelace',
				'description'  => 'Mathematician',
				'user_url'     => 'https://example.com',
			)
		);

		update_user_meta( $user_id, 'description', 'Mathematician' );

		$profile = Profile_Fields::get_member_profile( $user_id, true );

		$this->assertNotWPError( $profile );
		$this->assertArrayHasKey( 'fields', $profile );
		$this->assertArrayHasKey( 'groups', $profile );
		$this->assertNotEmpty( $profile['fields'] );
		$this->assertNotEmpty( $profile['groups'] );

		$group_keys = wp_list_pluck( $profile['groups'], 'key' );

		$this->assertContains( 'base', $group_keys );
		$this->assertContains( 'contact', $group_keys );

		foreach ( $profile['groups'] as $group ) {
			$this->assertNotEmpty( $group['fields'] );
		}
	}

	/**
	 * Empty image fields do not render as zero in frontend profile blocks.
	 *
	 * @return void
	 */
	public function test_empty_profile_image_field_does_not_render_zero() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create( array( 'display_name' => 'Empty Image Member' ) );

		$profile = do_blocks( '<!-- wp:assemblypress/member-profile {"userId":' . (int) $user_id . '} /-->' );
		$field   = do_blocks( '<!-- wp:assemblypress/profile-field {"fieldKey":"avatar","userId":' . (int) $user_id . '} /-->' );

		$this->assertStringNotContainsString( 'Profile image', $profile );
		$this->assertStringNotContainsString( '<dd>0</dd>', $profile );
		$this->assertStringNotContainsString( '<dd>0</dd>', $field );
	}

	/**
	 * Profile blocks render field values inside semantic definition lists.
	 *
	 * @return void
	 */
	public function test_profile_blocks_render_semantic_definition_lists() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Semantic Member',
			)
		);
		update_user_meta( $user_id, 'assemblypress_location', 'Semantic Place' );

		$profile = do_blocks( '<!-- wp:assemblypress/member-profile {"userId":' . (int) $user_id . '} /-->' );
		$field   = do_blocks( '<!-- wp:assemblypress/profile-field {"fieldKey":"location","userId":' . (int) $user_id . '} /-->' );

		$this->assertStringContainsString( '<section class="assemblypress-member-profile__group">', $profile );
		$this->assertStringContainsString( '<dl class="assemblypress-member-profile__fields">', $profile );
		$this->assertStringContainsString( '<dt>Location</dt><dd>Semantic Place</dd>', $profile );

		$this->assertStringContainsString( '<dl class="assemblypress-profile-field__fields">', $field );
		$this->assertStringContainsString( '<dt>Location</dt><dd>Semantic Place</dd>', $field );
	}

	/**
	 * Image fields still render when a real attachment ID exists.
	 *
	 * @return void
	 */
	public function test_profile_image_field_renders_attachment() {
		Profile_Fields::seed_defaults();

		$user_id       = self::factory()->user->create( array( 'display_name' => 'Image Member' ) );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Profile image attachment',
				'post_status'    => 'inherit',
			),
			'profile-image.jpg'
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 1,
				'height' => 1,
				'file'   => 'profile-image.jpg',
				'sizes'  => array(),
			)
		);

		update_user_meta( $user_id, 'assemblypress_avatar_id', $attachment_id );

		$output = do_blocks( '<!-- wp:assemblypress/profile-field {"fieldKey":"avatar","userId":' . (int) $user_id . '} /-->' );

		$this->assertStringContainsString( 'Profile image', $output );
		$this->assertStringContainsString( '<img', $output );
		$this->assertStringNotContainsString( '<dd>0</dd>', $output );
	}

	/**
	 * The current-member profile route writes editable user-backed and meta-backed values.
	 *
	 * @return void
	 */
	public function test_current_member_profile_route_writes_profile_values() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Original Name',
				'user_url'     => '',
			)
		);

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/assemblypress/v1/me/profile' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'fields' => array(
						'display_name' => 'Grace Hopper',
						'bio'          => 'Compiler pioneer',
						'location'     => 'Arlington',
						'website'      => 'https://example.org',
					),
				)
			)
		);

		$result = Rest_Controller::update_my_profile( $request );

		$this->assertNotWPError( $result );

		$user = get_userdata( $user_id );

		$this->assertSame( 'Grace Hopper', $user->display_name );
		$this->assertSame( 'https://example.org', $user->user_url );
		$this->assertSame( 'Compiler pioneer', get_user_meta( $user_id, 'description', true ) );
		$this->assertSame( 'Arlington', get_user_meta( $user_id, 'assemblypress_location', true ) );
	}

	/**
	 * Private profile fields are hidden from public member reads.
	 *
	 * @return void
	 */
	public function test_private_fields_are_hidden_from_public_member_reads() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			array(
				'key'        => 'secret_code',
				'label'      => 'Secret Code',
				'type'       => 'text',
				'visibility' => 'private',
				'editable'   => true,
				'required'   => false,
				'group_id'   => $about_group['id'],
				'order'      => 80,
			)
		);

		$this->assertNotWPError( $field );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'assemblypress_secret_code', 'hidden' );

		$public_profile  = Profile_Fields::get_member_profile( $user_id, false );
		$private_profile = Profile_Fields::get_member_profile( $user_id, true );

		$this->assertNotContains( 'secret_code', wp_list_pluck( $public_profile['fields'], 'key' ) );
		$this->assertContains( 'secret_code', wp_list_pluck( $private_profile['fields'], 'key' ) );
	}

	/**
	 * Required editable fields reject empty values.
	 *
	 * @return void
	 */
	public function test_required_editable_fields_reject_empty_values() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create();
		$result  = Profile_Fields::update_member_profile(
			$user_id,
			array(
				'display_name' => '',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_required_field', $result->get_error_code() );
	}

	/**
	 * Profile updates validate the submitted batch before persisting any values.
	 *
	 * @return void
	 */
	public function test_profile_updates_validate_before_persisting_values() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			array(
				'key'        => 'required_note',
				'label'      => 'Required Note',
				'type'       => 'text',
				'visibility' => 'private',
				'editable'   => true,
				'required'   => true,
				'group_id'   => $about_group['id'],
				'order'      => 120,
			)
		);

		$this->assertNotWPError( $field );

		$user_id = self::factory()->user->create( array( 'display_name' => 'Original Name' ) );

		$result = Profile_Fields::update_member_profile(
			$user_id,
			array(
				'display_name'  => 'Partial Update',
				'location'      => 'Should Not Save',
				'required_note' => '',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_required_field', $result->get_error_code() );
		$this->assertSame( 'Original Name', get_userdata( $user_id )->display_name );
		$this->assertSame( '', get_user_meta( $user_id, 'assemblypress_location', true ) );
	}

	/**
	 * Profile updates reject non-object value payloads.
	 *
	 * @return void
	 */
	public function test_profile_updates_reject_non_array_values() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create();
		$result  = Profile_Fields::update_member_profile( $user_id, 'display_name=Nope' );

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_invalid_profile_values', $result->get_error_code() );
	}

	/**
	 * Non-editable profile fields ignore submitted member values.
	 *
	 * @return void
	 */
	public function test_non_editable_fields_ignore_submitted_values() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			array(
				'key'        => 'staff_note',
				'label'      => 'Staff Note',
				'type'       => 'text',
				'visibility' => 'private',
				'editable'   => false,
				'required'   => false,
				'group_id'   => $about_group['id'],
				'order'      => 90,
			)
		);

		$this->assertNotWPError( $field );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'assemblypress_staff_note', 'original' );

		$result = Profile_Fields::update_member_profile(
			$user_id,
			array(
				'staff_note' => 'changed',
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 'original', get_user_meta( $user_id, 'assemblypress_staff_note', true ) );
	}

	/**
	 * The temporary profile editor reports successful saves and reads updated values.
	 *
	 * @return void
	 */
	public function test_edit_profile_form_reports_success_and_reads_updated_values() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create( array( 'display_name' => 'Before Save' ) );
		wp_set_current_user( $user_id );

		$_SERVER['REQUEST_METHOD']          = 'POST';
		$_POST['assemblypress_profile_nonce'] = wp_create_nonce( 'assemblypress_update_profile' );
		$_POST['assemblypress_display_name']  = 'After Save';
		$_POST['assemblypress_bio']           = 'Updated bio';
		$_POST['assemblypress_location']      = 'Updated location';
		$_POST['assemblypress_website']       = 'https://example.org';
		$_POST['assemblypress_avatar']        = '0';

		$output = do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' );

		$this->assertStringContainsString( 'Profile saved.', $output );
		$this->assertStringContainsString( 'value="After Save"', $output );
		$this->assertSame( 'After Save', get_userdata( $user_id )->display_name );
		$this->assertSame( 'Updated location', get_user_meta( $user_id, 'assemblypress_location', true ) );
	}

	/**
	 * The temporary profile editor reports validation errors without partial writes.
	 *
	 * @return void
	 */
	public function test_edit_profile_form_reports_validation_errors_without_partial_writes() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create( array( 'display_name' => 'Before Error' ) );
		wp_set_current_user( $user_id );

		$_SERVER['REQUEST_METHOD']          = 'POST';
		$_POST['assemblypress_profile_nonce'] = wp_create_nonce( 'assemblypress_update_profile' );
		$_POST['assemblypress_display_name']  = '';
		$_POST['assemblypress_location']      = 'Should Not Save';

		$output = do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' );

		$this->assertStringContainsString( 'Display name is required.', $output );
		$this->assertSame( 'Before Error', get_userdata( $user_id )->display_name );
		$this->assertSame( '', get_user_meta( $user_id, 'assemblypress_location', true ) );
	}
}
