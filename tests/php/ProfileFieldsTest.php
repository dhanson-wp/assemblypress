<?php
/**
 * Profile field tests.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Profile_Fields;

/**
 * Profile field registry tests.
 */
class ProfileFieldsTest extends WP_UnitTestCase {
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
		$created = Profile_Fields::create_field(
			array(
				'key'        => 'preferred_contact',
				'label'      => 'Preferred Contact',
				'type'       => 'select',
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 70,
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
		$this->assertSame( 'sms', $updated['options'][0]['value'] );

		$deleted = Profile_Fields::delete_field( $created['id'] );

		$this->assertSame( array( 'deleted' => true ), $deleted );
		$this->assertNull( get_post( $created['id'] ) );
	}
}
