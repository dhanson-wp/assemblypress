<?php
/**
 * REST controller tests.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Capabilities;
use AssemblyPress\Profile_Field_Groups;
use AssemblyPress\Profile_Fields;

/**
 * REST controller tests.
 */
class RestControllerTest extends WP_UnitTestCase {
	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Set up route tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Reset REST server after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Profile schema management capability maps to the current temporary capability.
	 *
	 * @return void
	 */
	public function test_profile_schema_management_capability_maps_to_list_users_for_now() {
		$this->assertSame( 'list_users', Capabilities::manage_profile_schema() );

		wp_set_current_user( $this->subscriber_id );
		$this->assertFalse( Capabilities::current_user_can_manage_profile_schema() );

		wp_set_current_user( $this->admin_id );
		$this->assertTrue( Capabilities::current_user_can_manage_profile_schema() );
	}

	/**
	 * Profile field collection route requires the current management capability.
	 *
	 * @return void
	 */
	public function test_profile_field_collection_route_requires_profile_schema_management_capability() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'GET', '/assemblypress/v1/profile-fields' )->get_status() );

		wp_set_current_user( $this->subscriber_id );
		$this->assertSame( 403, $this->dispatch( 'GET', '/assemblypress/v1/profile-fields' )->get_status() );

		wp_set_current_user( $this->admin_id );
		$this->assertSame( 200, $this->dispatch( 'GET', '/assemblypress/v1/profile-fields' )->get_status() );
	}

	/**
	 * Profile field group collection route requires the current management capability.
	 *
	 * @return void
	 */
	public function test_profile_field_group_collection_route_requires_profile_schema_management_capability() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'GET', '/assemblypress/v1/profile-field-groups' )->get_status() );

		wp_set_current_user( $this->subscriber_id );
		$this->assertSame( 403, $this->dispatch( 'GET', '/assemblypress/v1/profile-field-groups' )->get_status() );

		wp_set_current_user( $this->admin_id );
		$this->assertSame( 200, $this->dispatch( 'GET', '/assemblypress/v1/profile-field-groups' )->get_status() );
	}

	/**
	 * Unauthenticated users cannot mutate profile fields or groups.
	 *
	 * @return void
	 */
	public function test_unauthenticated_users_cannot_mutate_profile_fields_or_groups() {
		Profile_Fields::seed_defaults();

		$field = Profile_Fields::get_field( 'location' );
		$group = Profile_Field_Groups::get_group( 'about' );

		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch( 'POST', '/assemblypress/v1/profile-fields', $this->field_payload( 'timezone', 'Timezone' ) )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'PUT', '/assemblypress/v1/profile-fields/' . $field['id'], array_merge( $field, array( 'label' => 'Place' ) ) )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'DELETE', '/assemblypress/v1/profile-fields/' . $field['id'] )->get_status() );

		$this->assertSame( 401, $this->dispatch( 'POST', '/assemblypress/v1/profile-field-groups', $this->group_payload( 'membership', 'Membership' ) )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'PUT', '/assemblypress/v1/profile-field-groups/' . $group['id'], array_merge( $group, array( 'label' => 'About member' ) ) )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'DELETE', '/assemblypress/v1/profile-field-groups/' . $group['id'] )->get_status() );
	}

	/**
	 * Authorized users can create and update profile fields through REST.
	 *
	 * @return void
	 */
	public function test_authorized_users_can_create_and_update_profile_fields_through_rest() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$created = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-fields',
			$this->field_payload(
				'timezone',
				'Timezone',
				array(
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'central',
							'label' => 'Central',
						),
					),
				)
			)
		);

		$this->assertSame( 200, $created->get_status() );
		$this->assertSame( 'timezone', $created->get_data()['key'] );
		$this->assertSame( 'select', $created->get_data()['type'] );

		$updated_payload          = $created->get_data();
		$updated_payload['label'] = 'Local Timezone';
		$updated                  = $this->dispatch( 'PUT', '/assemblypress/v1/profile-fields/' . $updated_payload['id'], $updated_payload );

		$this->assertSame( 200, $updated->get_status() );
		$this->assertSame( 'Local Timezone', $updated->get_data()['label'] );
	}

	/**
	 * Authorized users can create and update profile field groups through REST.
	 *
	 * @return void
	 */
	public function test_authorized_users_can_create_and_update_profile_field_groups_through_rest() {
		wp_set_current_user( $this->admin_id );

		$created = $this->dispatch( 'POST', '/assemblypress/v1/profile-field-groups', $this->group_payload( 'membership', 'Membership' ) );

		$this->assertSame( 200, $created->get_status() );
		$this->assertSame( 'membership', $created->get_data()['key'] );

		$updated_payload          = $created->get_data();
		$updated_payload['label'] = 'Member Details';
		$updated                  = $this->dispatch( 'PUT', '/assemblypress/v1/profile-field-groups/' . $updated_payload['id'], $updated_payload );

		$this->assertSame( 200, $updated->get_status() );
		$this->assertSame( 'Member Details', $updated->get_data()['label'] );
	}

	/**
	 * Profile field REST routes reject unsupported field types.
	 *
	 * @return void
	 */
	public function test_profile_field_routes_reject_invalid_field_type() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-fields',
			$this->field_payload( 'timezone', 'Timezone', array( 'type' => 'unsupported' ) )
		);

		$this->assertInvalidRestParam( $response );
	}

	/**
	 * Profile field REST routes reject unsupported visibility values.
	 *
	 * @return void
	 */
	public function test_profile_field_routes_reject_invalid_visibility() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-fields',
			$this->field_payload( 'timezone', 'Timezone', array( 'visibility' => 'friends' ) )
		);

		$this->assertInvalidRestParam( $response );
	}

	/**
	 * Profile field REST routes reject missing profile field groups.
	 *
	 * @return void
	 */
	public function test_profile_field_routes_reject_invalid_group_id() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-fields',
			$this->field_payload( 'timezone', 'Timezone', array( 'group_id' => 999999 ) )
		);

		$this->assertInvalidRestParam( $response );
	}

	/**
	 * Select field options must use the structured option shape.
	 *
	 * @return void
	 */
	public function test_profile_field_routes_reject_invalid_select_options() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-fields',
			$this->field_payload(
				'timezone',
				'Timezone',
				array(
					'type'    => 'select',
					'options' => array( 'central' ),
				)
			)
		);

		$this->assertInvalidRestParam( $response );
	}

	/**
	 * Profile field group REST routes reject invalid key and label values.
	 *
	 * @return void
	 */
	public function test_profile_field_group_routes_reject_invalid_key_and_label_shape() {
		wp_set_current_user( $this->admin_id );

		$key_response = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-field-groups',
			$this->group_payload( 'Bad Key', 'Bad Key' )
		);

		$this->assertInvalidRestParam( $key_response );

		$label_response = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-field-groups',
			$this->group_payload( 'membership', '' )
		);

		$this->assertInvalidRestParam( $label_response );
	}

	/**
	 * Duplicate field and group keys return structured REST errors.
	 *
	 * @return void
	 */
	public function test_duplicate_field_and_group_keys_return_structured_rest_errors() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$field_response = $this->dispatch( 'POST', '/assemblypress/v1/profile-fields', $this->field_payload( 'location', 'Duplicate Location' ) );
		$field_data     = $field_response->get_data();

		$this->assertSame( 409, $field_response->get_status() );
		$this->assertSame( 'assemblypress_duplicate_field_key', $field_data['code'] );

		$group_response = $this->dispatch( 'POST', '/assemblypress/v1/profile-field-groups', $this->group_payload( 'about', 'Duplicate About' ) );
		$group_data     = $group_response->get_data();

		$this->assertSame( 409, $group_response->get_status() );
		$this->assertSame( 'assemblypress_duplicate_group_key', $group_data['code'] );
	}

	/**
	 * Deleting a group with assigned fields is blocked through REST.
	 *
	 * @return void
	 */
	public function test_deleting_group_with_assigned_fields_is_blocked_through_rest() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$base     = Profile_Field_Groups::get_group( 'base' );
		$response = $this->dispatch( 'DELETE', '/assemblypress/v1/profile-field-groups/' . $base['id'] );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'assemblypress_group_not_empty', $data['code'] );
	}

	/**
	 * Profile field collection responses expose the stable profile field shape.
	 *
	 * @return void
	 */
	public function test_profile_field_collection_response_shape() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$created = $this->dispatch(
			'POST',
			'/assemblypress/v1/profile-fields',
			$this->field_payload(
				'favorite_color',
				'Favorite Color',
				array(
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'blue',
							'label' => 'Blue',
						),
					),
				)
			)
		);

		$this->assertSame( 200, $created->get_status() );

		$response = $this->dispatch( 'GET', '/assemblypress/v1/profile-fields' );
		$fields   = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertProfileFieldShape( $field );
		}

		$select_field = $this->findFieldByKey( $fields, 'favorite_color' );
		$this->assertSame( 'blue', $select_field['options'][0]['value'] );
		$this->assertSame( 'Blue', $select_field['options'][0]['label'] );
	}

	/**
	 * Profile field collection supports _fields filtering.
	 *
	 * @return void
	 */
	public function test_profile_field_collection_supports_fields_filtering() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch(
			'GET',
			'/assemblypress/v1/profile-fields',
			array(
				'_fields' => 'id,key,label',
			)
		);
		$fields   = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertOnlyKeys( array( 'id', 'key', 'label' ), $field );
		}
	}

	/**
	 * Profile field group collection responses expose the stable group shape.
	 *
	 * @return void
	 */
	public function test_profile_field_group_collection_response_shape() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'GET', '/assemblypress/v1/profile-field-groups' );
		$groups   = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $groups );

		foreach ( $groups as $group ) {
			$this->assertFieldGroupShape( $group );
		}
	}

	/**
	 * Profile field group collection supports _fields filtering.
	 *
	 * @return void
	 */
	public function test_profile_field_group_collection_supports_fields_filtering() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch(
			'GET',
			'/assemblypress/v1/profile-field-groups',
			array(
				'_fields' => 'id,key,label',
			)
		);
		$groups   = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $groups );

		foreach ( $groups as $group ) {
			$this->assertOnlyKeys( array( 'id', 'key', 'label' ), $group );
		}
	}

	/**
	 * Public member collection responses expose public-safe member records.
	 *
	 * @return void
	 */
	public function test_public_member_collection_response_shape() {
		$private_field = $this->create_private_note_field();
		$user_id       = self::factory()->user->create( array( 'display_name' => 'Public Member' ) );

		update_user_meta( $user_id, 'assemblypress_location', 'Public Place' );
		update_user_meta( $user_id, $private_field['meta_key'], 'Private collection note' );

		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', '/assemblypress/v1/members', array( 'per_page' => 100 ) );
		$data     = $response->get_data();
		$member   = $this->findMemberById( $data['members'], $user_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'members', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'totalPages', $data );
		$this->assertMemberShape( $member );
		$this->assertMemberGroupsShape( $member['groups'] );
		$this->assertNotContains( 'private_note', wp_list_pluck( $member['fields'], 'key' ) );
	}

	/**
	 * Public member detail responses expose public-safe member records.
	 *
	 * @return void
	 */
	public function test_public_member_detail_response_shape() {
		$private_field = $this->create_private_note_field();
		$user_id       = self::factory()->user->create( array( 'display_name' => 'Single Member' ) );

		update_user_meta( $user_id, $private_field['meta_key'], 'Private single note' );

		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', '/assemblypress/v1/members/' . $user_id );
		$member   = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertMemberShape( $member );
		$this->assertMemberGroupsShape( $member['groups'] );
		$this->assertNotContains( 'private_note', wp_list_pluck( $member['fields'], 'key' ) );
	}

	/**
	 * Current member profile responses include private fields for the current user.
	 *
	 * @return void
	 */
	public function test_me_profile_response_shape_includes_current_user_private_fields() {
		$private_field = $this->create_private_note_field();
		$user_id       = self::factory()->user->create( array( 'display_name' => 'Current Member' ) );

		update_user_meta( $user_id, $private_field['meta_key'], 'Private me note' );

		wp_set_current_user( $user_id );

		$response = $this->dispatch( 'GET', '/assemblypress/v1/me/profile' );
		$member   = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertMemberShape( $member );
		$this->assertMemberGroupsShape( $member['groups'] );
		$this->assertContains( 'private_note', wp_list_pluck( $member['fields'], 'key' ) );
	}

	/**
	 * OPTIONS exposes profile schema route schemas.
	 *
	 * @return void
	 */
	public function test_options_exposes_profile_schema_route_schemas() {
		wp_set_current_user( $this->admin_id );

		$field_schema = $this->getOptionsSchema( '/assemblypress/v1/profile-fields' );
		$this->assertSame( 'assemblypress_profile_field', $field_schema['title'] );
		$this->assertSchemaProperties(
			$field_schema,
			array( 'id', 'key', 'label', 'type', 'visibility', 'editable', 'required', 'description', 'order', 'group_id', 'options', 'is_system' )
		);
		$this->assertSame( 'array', $field_schema['properties']['options']['type'] );
		$this->assertSchemaProperties( $field_schema['properties']['options']['items'], array( 'value', 'label' ) );

		$field_item_schema = $this->getOptionsSchema( '/assemblypress/v1/profile-fields/123' );
		$this->assertSame( 'assemblypress_profile_field', $field_item_schema['title'] );

		$group_schema = $this->getOptionsSchema( '/assemblypress/v1/profile-field-groups' );
		$this->assertSame( 'assemblypress_profile_field_group', $group_schema['title'] );
		$this->assertSchemaProperties( $group_schema, array( 'id', 'key', 'label', 'description', 'order' ) );
	}

	/**
	 * OPTIONS exposes member route schemas.
	 *
	 * @return void
	 */
	public function test_options_exposes_member_route_schemas() {
		$user_id = self::factory()->user->create();

		$collection_schema = $this->getOptionsSchema( '/assemblypress/v1/members' );
		$this->assertSame( 'assemblypress_members_collection', $collection_schema['title'] );
		$this->assertSchemaProperties( $collection_schema, array( 'members', 'total', 'totalPages' ) );
		$this->assertSame( 'assemblypress_member_profile', $collection_schema['properties']['members']['items']['title'] );

		$collection_args = $this->getOptionsEndpointArgs( '/assemblypress/v1/members' );
		$this->assertSchemaProperties( array( 'properties' => $collection_args ), array( 'per_page', 'page', 'search' ) );
		$this->assertSame( 20, $collection_args['per_page']['default'] );
		$this->assertSame( 1, $collection_args['per_page']['minimum'] );
		$this->assertSame( 100, $collection_args['per_page']['maximum'] );
		$this->assertSame( 1, $collection_args['page']['default'] );
		$this->assertSame( 1, $collection_args['page']['minimum'] );
		$this->assertSame( '', $collection_args['search']['default'] );
		$this->assertSame( 'string', $collection_args['search']['type'] );

		$member_schema = $this->getOptionsSchema( '/assemblypress/v1/members/' . $user_id );
		$this->assertSame( 'assemblypress_member_profile', $member_schema['title'] );
		$this->assertSchemaProperties( $member_schema, array( 'id', 'display_name', 'avatar_url', 'profile_url', 'fields', 'groups' ) );
		$this->assertSame( 'assemblypress_profile_field', $member_schema['properties']['fields']['items']['title'] );
		$this->assertSame( 'assemblypress_grouped_profile_fields', $member_schema['properties']['groups']['items']['title'] );
		$this->assertSchemaProperties( $member_schema['properties']['groups']['items'], array( 'fields' ) );

		wp_set_current_user( $user_id );

		$me_schema = $this->getOptionsSchema( '/assemblypress/v1/me/profile' );
		$this->assertSame( 'assemblypress_member_profile', $me_schema['title'] );
	}

	/**
	 * Members collection rejects invalid per_page values.
	 *
	 * @return void
	 */
	public function test_members_collection_rejects_invalid_per_page() {
		$response = $this->dispatch( 'GET', '/assemblypress/v1/members', array( 'per_page' => 101 ) );

		$this->assertInvalidRestParam( $response );
	}

	/**
	 * Members collection rejects invalid page values.
	 *
	 * @return void
	 */
	public function test_members_collection_rejects_invalid_page() {
		$response = $this->dispatch( 'GET', '/assemblypress/v1/members', array( 'page' => 0 ) );

		$this->assertInvalidRestParam( $response );
	}

	/**
	 * Members collection accepts valid search, page, and per_page values.
	 *
	 * @return void
	 */
	public function test_members_collection_accepts_valid_query_args() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Needle Member',
				'user_login'   => 'needle_member_shape',
			)
		);

		$response = $this->dispatch(
			'GET',
			'/assemblypress/v1/members',
			array(
				'search'   => 'needle',
				'page'     => 1,
				'per_page' => 1,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'members', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'totalPages', $data );
		$this->assertLessThanOrEqual( 1, count( $data['members'] ) );
		$this->assertMemberShape( $this->findMemberById( $data['members'], $user_id ) );
	}

	/**
	 * Members collection supports top-level and nested _fields filtering.
	 *
	 * @return void
	 */
	public function test_members_collection_supports_fields_filtering() {
		Profile_Fields::seed_defaults();
		self::factory()->user->create( array( 'display_name' => 'Filtered Member' ) );

		$response = $this->dispatch(
			'GET',
			'/assemblypress/v1/members',
			array(
				'_fields'  => 'members.id,members.display_name,total,totalPages',
				'per_page' => 100,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertOnlyKeys( array( 'members', 'total', 'totalPages' ), $data );

		foreach ( $data['members'] as $member ) {
			$this->assertOnlyKeys( array( 'id', 'display_name' ), $member );
		}
	}

	/**
	 * Public member profile supports top-level _fields filtering.
	 *
	 * @return void
	 */
	public function test_public_member_profile_supports_fields_filtering() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create( array( 'display_name' => 'Filtered Profile' ) );

		$response = $this->dispatch(
			'GET',
			'/assemblypress/v1/members/' . $user_id,
			array(
				'_fields' => 'id,display_name,profile_url',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertOnlyKeys( array( 'id', 'display_name', 'profile_url' ), $data );
	}

	/**
	 * Current member profile supports top-level _fields filtering.
	 *
	 * @return void
	 */
	public function test_me_profile_supports_fields_filtering() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create( array( 'display_name' => 'Filtered Current Profile' ) );

		wp_set_current_user( $user_id );

		$response = $this->dispatch(
			'GET',
			'/assemblypress/v1/me/profile',
			array(
				'_fields' => 'id,display_name,fields',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertOnlyKeys( array( 'id', 'display_name', 'fields' ), $data );
		$this->assertIsArray( $data['fields'] );
	}

	/**
	 * Public member routes expose only public-safe profile data.
	 *
	 * @return void
	 */
	public function test_public_member_routes_only_expose_public_safe_profile_data() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			$this->field_payload(
				'private_note',
				'Private Note',
				array(
					'visibility' => 'private',
					'group_id'   => $about_group['id'],
				)
			)
		);

		$this->assertNotWPError( $field );

		$user_id = self::factory()->user->create( array( 'display_name' => 'Public Member' ) );
		update_user_meta( $user_id, 'assemblypress_private_note', 'Do not show' );

		wp_set_current_user( 0 );

		$single_response = $this->dispatch( 'GET', '/assemblypress/v1/members/' . $user_id );
		$single_keys     = wp_list_pluck( $single_response->get_data()['fields'], 'key' );

		$this->assertSame( 200, $single_response->get_status() );
		$this->assertNotContains( 'private_note', $single_keys );

		$collection_response = $this->dispatch( 'GET', '/assemblypress/v1/members', array( 'per_page' => 100 ) );
		$members             = $collection_response->get_data()['members'];
		$matched             = null;

		foreach ( $members as $member ) {
			if ( (int) $member['id'] === $user_id ) {
				$matched = $member;
				break;
			}
		}

		$this->assertSame( 200, $collection_response->get_status() );
		$this->assertIsArray( $matched );
		$this->assertNotContains( 'private_note', wp_list_pluck( $matched['fields'], 'key' ) );
	}

	/**
	 * Current member profile route requires login.
	 *
	 * @return void
	 */
	public function test_me_profile_requires_login() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch( 'GET', '/assemblypress/v1/me/profile' )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'POST', '/assemblypress/v1/me/profile', array( 'fields' => array( 'display_name' => 'Nobody' ) ) )->get_status() );
	}

	/**
	 * Current member profile route writes only the current user's editable values.
	 *
	 * @return void
	 */
	public function test_me_profile_writes_only_current_users_editable_values() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			$this->field_payload(
				'staff_note',
				'Staff Note',
				array(
					'editable'   => false,
					'visibility' => 'private',
					'group_id'   => $about_group['id'],
				)
			)
		);

		$this->assertNotWPError( $field );

		$current_user_id = self::factory()->user->create( array( 'display_name' => 'Current User' ) );
		$other_user_id   = self::factory()->user->create( array( 'display_name' => 'Other User' ) );

		update_user_meta( $current_user_id, 'assemblypress_staff_note', 'original current' );
		update_user_meta( $other_user_id, 'assemblypress_location', 'elsewhere' );

		wp_set_current_user( $current_user_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/me/profile',
			array(
				'fields' => array(
					'display_name' => 'Updated Current',
					'location'     => 'Current Place',
					'staff_note'   => 'changed current',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated Current', get_userdata( $current_user_id )->display_name );
		$this->assertSame( 'Current Place', get_user_meta( $current_user_id, 'assemblypress_location', true ) );
		$this->assertSame( 'original current', get_user_meta( $current_user_id, 'assemblypress_staff_note', true ) );
		$this->assertSame( 'Other User', get_userdata( $other_user_id )->display_name );
		$this->assertSame( 'elsewhere', get_user_meta( $other_user_id, 'assemblypress_location', true ) );
	}

	/**
	 * Current member profile updates return the same readable response shape as GET.
	 *
	 * @return void
	 */
	public function test_me_profile_update_response_supports_fields_filtering() {
		Profile_Fields::seed_defaults();

		$user_id = self::factory()->user->create( array( 'display_name' => 'Before Filtered Update' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/assemblypress/v1/me/profile' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_query_params( array( '_fields' => 'id,display_name,fields' ) );
		$request->set_body(
			wp_json_encode(
				array(
					'fields' => array(
						'display_name' => 'After Filtered Update',
						'location'     => 'Filtered Place',
					),
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertOnlyKeys( array( 'id', 'display_name', 'fields' ), $data );
		$this->assertSame( 'After Filtered Update', $data['display_name'] );
		$this->assertSame( 'Filtered Place', get_user_meta( $user_id, 'assemblypress_location', true ) );
	}

	/**
	 * Current member profile route rejects non-object field payloads.
	 *
	 * @return void
	 */
	public function test_me_profile_rejects_invalid_fields_payload() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->subscriber_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/me/profile',
			array(
				'fields' => 'display_name=Nope',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['code'] );
	}

	/**
	 * Current member profile route rejects list-shaped field payloads.
	 *
	 * @return void
	 */
	public function test_me_profile_rejects_list_shaped_fields_payload() {
		Profile_Fields::seed_defaults();

		wp_set_current_user( $this->subscriber_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/me/profile',
			array(
				'fields' => array( 'display_name' ),
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['code'] );
	}

	/**
	 * Current member profile route validates submitted values before writes.
	 *
	 * @return void
	 */
	public function test_me_profile_validates_before_persisting_values() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			$this->field_payload(
				'required_note',
				'Required Note',
				array(
					'visibility' => 'private',
					'required'   => true,
					'group_id'   => $about_group['id'],
				)
			)
		);

		$this->assertNotWPError( $field );

		$user_id = self::factory()->user->create( array( 'display_name' => 'Original Rest Name' ) );
		wp_set_current_user( $user_id );

		$response = $this->dispatch(
			'POST',
			'/assemblypress/v1/me/profile',
			array(
				'fields' => array(
					'display_name'  => 'Partial Rest Name',
					'location'      => 'Partial Rest Place',
					'required_note' => '',
				),
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'assemblypress_required_field', $data['code'] );
		$this->assertSame( 'Original Rest Name', get_userdata( $user_id )->display_name );
		$this->assertSame( '', get_user_meta( $user_id, 'assemblypress_location', true ) );
	}

	/**
	 * Dispatch a REST request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  REST route.
	 * @param array  $body   Optional request body.
	 * @return WP_REST_Response
	 */
	private function dispatch( $method, $route, $body = array() ) {
		$request = new WP_REST_Request( $method, $route );

		if ( ! empty( $body ) ) {
			if ( 'GET' === $method ) {
				foreach ( $body as $key => $value ) {
					$request->set_param( $key, $value );
				}
			} else {
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body( wp_json_encode( $body ) );
			}
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Assert a route-level invalid parameter response.
	 *
	 * @param WP_REST_Response $response REST response.
	 * @return void
	 */
	private function assertInvalidRestParam( $response ) {
		$data = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['code'] );
	}

	/**
	 * Get the OPTIONS schema for a route.
	 *
	 * @param string $route REST route.
	 * @return array
	 */
	private function getOptionsSchema( $route ) {
		$response = $this->dispatch( 'OPTIONS', $route );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'schema', $data );
		$this->assertIsArray( $data['schema'] );

		return $data['schema'];
	}

	/**
	 * Get the OPTIONS args for a route endpoint.
	 *
	 * @param string $route  REST route.
	 * @param string $method HTTP method.
	 * @return array
	 */
	private function getOptionsEndpointArgs( $route, $method = 'GET' ) {
		$response = $this->dispatch( 'OPTIONS', $route );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'endpoints', $data );

		foreach ( $data['endpoints'] as $endpoint ) {
			if ( in_array( $method, $endpoint['methods'], true ) ) {
				$this->assertArrayHasKey( 'args', $endpoint );
				return $endpoint['args'];
			}
		}

		$this->fail( 'Expected OPTIONS endpoint was not found.' );
	}

	/**
	 * Assert schema properties exist.
	 *
	 * @param array $schema   Schema.
	 * @param array $expected Expected property keys.
	 * @return void
	 */
	private function assertSchemaProperties( $schema, $expected ) {
		$this->assertArrayHasKey( 'properties', $schema );

		foreach ( $expected as $property ) {
			$this->assertArrayHasKey( $property, $schema['properties'] );
		}
	}

	/**
	 * Assert that an array has exactly the expected keys.
	 *
	 * @param array $expected Expected keys.
	 * @param array $data     Response data.
	 * @return void
	 */
	private function assertOnlyKeys( $expected, $data ) {
		$actual = array_keys( $data );
		sort( $actual );
		sort( $expected );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Assert a profile field response shape.
	 *
	 * @param array $field     Field data.
	 * @param bool  $has_value Whether the field should include a member value.
	 * @return void
	 */
	private function assertProfileFieldShape( $field, $has_value = false ) {
		foreach ( array( 'id', 'key', 'label', 'type', 'visibility', 'editable', 'required', 'description', 'order', 'group_id', 'options', 'is_system' ) as $key ) {
			$this->assertArrayHasKey( $key, $field );
		}

		$this->assertIsInt( $field['id'] );
		$this->assertIsString( $field['key'] );
		$this->assertIsString( $field['label'] );
		$this->assertIsString( $field['type'] );
		$this->assertContains( $field['visibility'], array( 'public', 'private' ) );
		$this->assertIsBool( $field['editable'] );
		$this->assertIsBool( $field['required'] );
		$this->assertIsString( $field['description'] );
		$this->assertIsInt( $field['order'] );
		$this->assertIsInt( $field['group_id'] );
		$this->assertIsArray( $field['options'] );
		$this->assertIsBool( $field['is_system'] );

		foreach ( $field['options'] as $option ) {
			$this->assertArrayHasKey( 'value', $option );
			$this->assertArrayHasKey( 'label', $option );
			$this->assertIsString( $option['value'] );
			$this->assertIsString( $option['label'] );
		}

		if ( $has_value ) {
			$this->assertArrayHasKey( 'value', $field );
		}
	}

	/**
	 * Assert a profile field group response shape.
	 *
	 * @param array $group      Group data.
	 * @param bool  $has_fields Whether the group should include grouped fields.
	 * @return void
	 */
	private function assertFieldGroupShape( $group, $has_fields = false ) {
		foreach ( array( 'id', 'key', 'label', 'description', 'order' ) as $key ) {
			$this->assertArrayHasKey( $key, $group );
		}

		$this->assertIsInt( $group['id'] );
		$this->assertIsString( $group['key'] );
		$this->assertIsString( $group['label'] );
		$this->assertIsString( $group['description'] );
		$this->assertIsInt( $group['order'] );

		if ( $has_fields ) {
			$this->assertArrayHasKey( 'fields', $group );
			$this->assertIsArray( $group['fields'] );

			foreach ( $group['fields'] as $field ) {
				$this->assertProfileFieldShape( $field, true );
			}
		}
	}

	/**
	 * Assert a member response shape.
	 *
	 * @param array $member Member data.
	 * @return void
	 */
	private function assertMemberShape( $member ) {
		foreach ( array( 'id', 'display_name', 'avatar_url', 'profile_url', 'fields', 'groups' ) as $key ) {
			$this->assertArrayHasKey( $key, $member );
		}

		$this->assertIsInt( $member['id'] );
		$this->assertIsString( $member['display_name'] );
		$this->assertIsString( $member['avatar_url'] );
		$this->assertIsString( $member['profile_url'] );
		$this->assertIsArray( $member['fields'] );
		$this->assertIsArray( $member['groups'] );

		foreach ( $member['fields'] as $field ) {
			$this->assertProfileFieldShape( $field, true );
		}
	}

	/**
	 * Assert grouped member field response shapes.
	 *
	 * @param array $groups Grouped fields.
	 * @return void
	 */
	private function assertMemberGroupsShape( $groups ) {
		$this->assertNotEmpty( $groups );

		foreach ( $groups as $group ) {
			$this->assertFieldGroupShape( $group, true );
		}
	}

	/**
	 * Create a private note field for response shape tests.
	 *
	 * @return array
	 */
	private function create_private_note_field() {
		Profile_Fields::seed_defaults();

		$about_group = Profile_Field_Groups::get_group( 'about' );
		$field       = Profile_Fields::create_field(
			$this->field_payload(
				'private_note',
				'Private Note',
				array(
					'visibility' => 'private',
					'group_id'   => $about_group['id'],
				)
			)
		);

		$this->assertNotWPError( $field );

		return $field;
	}

	/**
	 * Find a field by key.
	 *
	 * @param array  $fields Fields.
	 * @param string $key    Field key.
	 * @return array
	 */
	private function findFieldByKey( $fields, $key ) {
		foreach ( $fields as $field ) {
			if ( $key === $field['key'] ) {
				return $field;
			}
		}

		$this->fail( 'Expected field was not found.' );
	}

	/**
	 * Find a member by ID.
	 *
	 * @param array $members Members.
	 * @param int   $user_id User ID.
	 * @return array
	 */
	private function findMemberById( $members, $user_id ) {
		foreach ( $members as $member ) {
			if ( (int) $member['id'] === (int) $user_id ) {
				return $member;
			}
		}

		$this->fail( 'Expected member was not found.' );
	}

	/**
	 * Build a profile field request payload.
	 *
	 * @param string $key       Field key.
	 * @param string $label     Field label.
	 * @param array  $overrides Optional overrides.
	 * @return array
	 */
	private function field_payload( $key, $label, $overrides = array() ) {
		Profile_Field_Groups::ensure_starter_groups();

		$payload = array(
			'key'        => $key,
			'label'      => $label,
			'type'       => 'text',
			'visibility' => 'public',
			'editable'   => true,
			'required'   => false,
			'group_id'   => Profile_Field_Groups::get_default_group_id(),
			'order'      => 100,
		);

		return array_merge( $payload, $overrides );
	}

	/**
	 * Build a profile field group request payload.
	 *
	 * @param string $key       Group key.
	 * @param string $label     Group label.
	 * @param array  $overrides Optional overrides.
	 * @return array
	 */
	private function group_payload( $key, $label, $overrides = array() ) {
		return array_merge(
			array(
				'key'         => $key,
				'label'       => $label,
				'description' => '',
				'order'       => 100,
			),
			$overrides
		);
	}
}
