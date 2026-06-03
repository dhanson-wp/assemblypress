<?php
/**
 * Profile form adapter tests.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Profile_Field_Groups;
use AssemblyPress\Profile_Form_Adapter;
use AssemblyPress\Profile_Fields;

/**
 * Jetpack-shaped profile form adapter tests.
 */
class ProfileFormAdapterTest extends WP_UnitTestCase {
	/**
	 * Jetpack text and select field blocks map to AssemblyPress profile schema.
	 *
	 * @return void
	 */
	public function test_jetpack_field_blocks_map_to_profile_field_schema() {
		Profile_Fields::seed_defaults();

		$fields = Profile_Form_Adapter::profile_fields_from_blocks(
			$this->jetpack_profile_form_blocks(),
			array(
				'group_id' => Profile_Field_Groups::get_default_group_id(),
				'order'    => 100,
			)
		);

		$this->assertNotWPError( $fields );

		$fields_by_key = $this->fields_by_key( $fields );

		$this->assertArrayHasKey( 'display_name', $fields_by_key );
		$this->assertSame( 'user', $fields_by_key['display_name']['source'] );
		$this->assertSame( 'display_name', $fields_by_key['display_name']['user_key'] );
		$this->assertTrue( $fields_by_key['display_name']['required'] );

		$this->assertArrayHasKey( 'location', $fields_by_key );
		$this->assertSame( 'meta', $fields_by_key['location']['source'] );
		$this->assertSame( 'assemblypress_location', $fields_by_key['location']['meta_key'] );
		$this->assertSame( 'text', $fields_by_key['location']['type'] );

		$this->assertArrayHasKey( 'bio', $fields_by_key );
		$this->assertSame( 'user', $fields_by_key['bio']['source'] );
		$this->assertSame( 'description', $fields_by_key['bio']['user_key'] );
		$this->assertSame( 'textarea', $fields_by_key['bio']['type'] );

		$this->assertArrayHasKey( 'preferred_contact', $fields_by_key );
		$this->assertSame( 'select', $fields_by_key['preferred_contact']['type'] );
		$this->assertSame( 'email', $fields_by_key['preferred_contact']['options'][0]['value'] );
	}

	/**
	 * Jetpack-style submission values become the current /me/profile payload.
	 *
	 * @return void
	 */
	public function test_jetpack_submission_maps_to_member_profile_values() {
		Profile_Fields::seed_defaults();

		$contact_group = Profile_Field_Groups::get_group( 'contact' );
		$field         = Profile_Fields::create_field(
			array(
				'key'        => 'preferred_contact',
				'label'      => 'Preferred contact',
				'type'       => 'select',
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'group_id'   => $contact_group['id'],
				'order'      => 70,
				'options'    => array(
					array(
						'value' => 'email',
						'label' => 'Email',
					),
					array(
						'value' => 'sms',
						'label' => 'SMS',
					),
				),
			)
		);

		$this->assertNotWPError( $field );

		$values = Profile_Form_Adapter::profile_values_from_submission(
			$this->jetpack_profile_form_blocks(),
			array(
				'display_name'      => 'Jetpack Mapper',
				'bio'               => 'Mapped bio',
				'location'          => 'Adapter City',
				'preferred_contact' => 'sms',
			)
		);

		$this->assertNotWPError( $values );
		$this->assertSame(
			array(
				'display_name'      => 'Jetpack Mapper',
				'bio'               => 'Mapped bio',
				'location'          => 'Adapter City',
				'preferred_contact' => 'sms',
			),
			$values
		);

		$user_id = self::factory()->user->create( array( 'display_name' => 'Before Adapter' ) );
		$profile = Profile_Fields::update_member_profile( $user_id, $values );

		$this->assertNotWPError( $profile );
		$this->assertSame( 'Jetpack Mapper', get_userdata( $user_id )->display_name );
		$this->assertSame( 'Mapped bio', get_user_meta( $user_id, 'description', true ) );
		$this->assertSame( 'Adapter City', get_user_meta( $user_id, 'assemblypress_location', true ) );
		$this->assertSame( 'sms', get_user_meta( $user_id, 'assemblypress_preferred_contact', true ) );
	}

	/**
	 * Upload fields stay deferred instead of silently becoming profile fields.
	 *
	 * @return void
	 */
	public function test_jetpack_upload_fields_are_deferred_for_this_slice() {
		$result = Profile_Form_Adapter::profile_fields_from_blocks(
			array(
				array(
					'blockName' => 'jetpack/field-file',
					'attrs'     => array(
						'id'       => 'avatar',
						'required' => false,
					),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_profile_uploads_deferred', $result->get_error_code() );
	}

	/**
	 * Reserved field IDs are rejected before they become profile value keys.
	 *
	 * @return void
	 */
	public function test_reserved_jetpack_field_ids_are_rejected() {
		$result = Profile_Form_Adapter::profile_fields_from_blocks(
			array(
				array(
					'blockName' => 'jetpack/field-text',
					'attrs'     => array(
						'id' => 'action',
					),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'assemblypress_reserved_profile_field_key', $result->get_error_code() );
	}

	/**
	 * Build Jetpack-shaped profile form blocks for adapter tests.
	 *
	 * @return array
	 */
	private function jetpack_profile_form_blocks() {
		return array(
			array(
				'blockName'   => 'jetpack/contact-form',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'jetpack/field-text',
						'attrs'       => array(
							'id'       => 'display_name',
							'required' => true,
						),
						'innerBlocks' => array(
							array(
								'blockName' => 'jetpack/label',
								'attrs'     => array(
									'label' => 'Display name',
								),
							),
							array(
								'blockName' => 'jetpack/input',
								'attrs'     => array(
									'type' => 'text',
								),
							),
						),
					),
					array(
						'blockName'   => 'jetpack/field-textarea',
						'attrs'       => array(
							'id'       => 'bio',
							'required' => false,
						),
						'innerBlocks' => array(
							array(
								'blockName' => 'jetpack/label',
								'attrs'     => array(
									'label' => 'Bio',
								),
							),
						),
					),
					array(
						'blockName'   => 'jetpack/field-text',
						'attrs'       => array(
							'id'       => 'location',
							'required' => false,
						),
						'innerBlocks' => array(
							array(
								'blockName' => 'jetpack/label',
								'attrs'     => array(
									'label' => 'Location',
								),
							),
						),
					),
					array(
						'blockName' => 'jetpack/field-select',
						'attrs'     => array(
							'id'       => 'preferred_contact',
							'label'    => 'Preferred contact',
							'required' => false,
							'options'  => array( 'Email', 'SMS' ),
						),
					),
					array(
						'blockName' => 'core/button',
						'attrs'     => array(
							'tagName' => 'button',
							'type'    => 'submit',
						),
					),
				),
			),
		);
	}

	/**
	 * Key fields by profile field key.
	 *
	 * @param array $fields Profile fields.
	 * @return array
	 */
	private function fields_by_key( $fields ) {
		$keyed = array();

		foreach ( $fields as $field ) {
			$keyed[ $field['key'] ] = $field;
		}

		return $keyed;
	}
}
