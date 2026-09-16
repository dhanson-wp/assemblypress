<?php
/**
 * Shared member destination and avatar regression tests.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Blocks;
use AssemblyPress\Member_Profiles;
use AssemblyPress\Profile_Fields;

/**
 * Exercise member context independently of post/page IDs.
 */
class MemberProfilesTest extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		Profile_Fields::seed_defaults();
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_new_member_has_pretty_and_plain_destinations_without_a_page() {
		$id = self::factory()->user->create( array( 'user_nicename' => 'new-member' ) );
		$this->set_permalink_structure( '/%postname%/' );
		Member_Profiles::register_routes();
		flush_rewrite_rules( false );
		$this->assertSame( home_url( '/members/new-member/' ), Member_Profiles::get_url( $id ) );
		$this->go_to( Member_Profiles::get_url( $id ) );
		$this->assertFalse( is_404() );
		$this->assertSame( $id, Member_Profiles::queried_member_id() );
		$this->assertSame( 'assemblypress-member-profile.php', Member_Profiles::template_hierarchy( array( 'author.php' ) )[0] );
		$this->go_to( Member_Profiles::get_url( $id, true ) );
		$this->assertSame( 'assemblypress-edit-profile.php', Member_Profiles::template_hierarchy( array( 'author.php' ) )[0] );
		$this->set_permalink_structure( '' );
		$this->go_to( Member_Profiles::get_url( $id ) );
		$this->assertFalse( is_404() );
		$this->assertSame( $id, Member_Profiles::queried_member_id() );
	}

	public function test_unknown_member_is_not_replaced_by_signed_in_viewer() {
		wp_set_current_user( self::factory()->user->create() );
		$this->go_to( home_url( '/?author_name=missing-assemblypress-member&assemblypress_profile=1' ) );
		Member_Profiles::prepare_request();
		$this->assertTrue( is_404() );
		$this->assertSame( 0, Member_Profiles::resolve_member_id( array() ) );
		$this->assertSame( '', Blocks::render_member_profile( array() ) );
	}

	public function test_page_id_is_never_used_as_a_member_id() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$user_id = self::factory()->user->create();
		$this->go_to( get_permalink( $page_id ) );
		global $wp_query;
		// Simulate a page whose numeric ID also belongs to an unrelated user.
		$wp_query->queried_object = clone get_post( $page_id );
		$wp_query->queried_object->ID = $user_id;
		$wp_query->queried_object_id = $user_id;
		$this->assertSame( 0, Member_Profiles::resolve_member_id( array() ) );
		$viewer = self::factory()->user->create();
		wp_set_current_user( $viewer );
		$this->assertSame( $viewer, Member_Profiles::resolve_member_id( array() ) );
		$this->assertSame( 0, Member_Profiles::resolve_member_id( array( 'userId' => 9999999 ) ) );
	}

	public function test_profile_and_field_blocks_share_queried_member_context() {
		$member = self::factory()->user->create( array( 'display_name' => 'Queried Member' ) );
		$viewer = self::factory()->user->create( array( 'display_name' => 'Viewing Member' ) );
		update_user_meta( $member, 'assemblypress_location', 'Member location' );
		update_user_meta( $viewer, 'assemblypress_location', 'Viewer location' );
		wp_set_current_user( $viewer );
		$this->go_to( Member_Profiles::get_url( $member ) );
		$output = do_blocks( '<!-- wp:assemblypress/member-profile /--><!-- wp:assemblypress/profile-field {"fieldKey":"location"} /-->' );
		$this->assertStringContainsString( 'Queried Member', $output );
		$this->assertStringContainsString( 'Member location', $output );
		$this->assertStringNotContainsString( 'Viewing Member', $output );
		$this->assertStringNotContainsString( 'Viewer location', $output );
		$this->assertStringNotContainsString( 'Edit my profile', $output );
		$this->assertSame( $viewer, Member_Profiles::resolve_member_id( array( 'userId' => $viewer ) ) );
	}

	public function test_edit_destination_only_edits_its_owner() {
		$member = self::factory()->user->create();
		$other  = self::factory()->user->create();
		$this->go_to( Member_Profiles::get_url( $member, true ) );
		$logged_out = do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' );
		$this->assertStringContainsString( 'name="log"', $logged_out );
		$this->assertStringContainsString( 'name="pwd"', $logged_out );
		wp_set_current_user( $other );
		$this->assertFalse( Member_Profiles::can_edit_request() );
		$this->assertStringNotContainsString( 'name="assemblypress_display_name"', do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' ) );
		wp_set_current_user( $member );
		$this->assertTrue( Member_Profiles::can_edit_request() );
		$this->assertStringContainsString( 'name="assemblypress_display_name"', do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' ) );
		$this->assertStringContainsString( 'View my profile', do_blocks( '<!-- wp:assemblypress/edit-profile-form /-->' ) );
	}

	public function test_posting_to_another_members_route_cannot_change_either_profile() {
		$owner = self::factory()->user->create( array( 'display_name' => 'Profile owner' ) );
		$viewer = self::factory()->user->create( array( 'display_name' => 'Other member' ) );
		wp_set_current_user( $viewer );
		$this->go_to( Member_Profiles::get_url( $owner, true ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['assemblypress_profile_nonce'] = wp_create_nonce( 'assemblypress_update_profile' );
		$_POST['assemblypress_display_name'] = 'Changed name';
		$stop_redirect = static function () { return false; };
		add_filter( 'wp_redirect', $stop_redirect );
		try {
			Blocks::maybe_handle_edit_profile_form_submission();
			$this->assertSame( 'Profile owner', get_userdata( $owner )->display_name );
			$this->assertSame( 'Other member', get_userdata( $viewer )->display_name );
		} finally {
			remove_filter( 'wp_redirect', $stop_redirect );
			unset( $_POST['assemblypress_profile_nonce'], $_POST['assemblypress_display_name'] );
			$_SERVER['REQUEST_METHOD'] = 'GET';
		}
	}

	public function test_private_attachment_parent_and_deleted_image_use_fallback() {
		$member = self::factory()->user->create();
		$fallback = get_avatar_url( $member );
		$parent = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $parent );
		update_user_meta( $member, 'assemblypress_avatar_id', $image );
		$this->assertSame( $fallback, get_avatar_url( $member ) );
		wp_update_post( array( 'ID' => $parent, 'post_status' => 'publish' ) );
		$this->assertNotSame( $fallback, get_avatar_url( $member ) );
		wp_delete_attachment( $image, true );
		$this->assertSame( $fallback, get_avatar_url( $member ) );
	}

	public function test_author_archives_keep_their_original_links_and_templates() {
		$member = self::factory()->user->create();
		$url = get_author_posts_url( $member );
		$this->assertNotSame( Member_Profiles::get_url( $member ), $url );
		$this->go_to( $url );
		$this->assertFalse( Member_Profiles::is_request() );
		$this->assertSame( array( 'author.php' ), Member_Profiles::template_hierarchy( array( 'author.php' ) ) );
		$this->assertSame( $url, Member_Profiles::canonical_redirect( $url ) );
	}

	public function test_public_avatar_is_consistent_and_invalid_values_fall_back() {
		$member = self::factory()->user->create();
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		update_user_meta( $member, 'assemblypress_avatar_id', $image );
		$url = wp_get_attachment_image_url( $image, array( 96, 96 ) );
		$this->assertSame( $url, get_avatar_url( $member ) );
		$this->assertSame( $url, Profile_Fields::get_member_profile( $member )['avatar_url'] );
		$this->assertStringContainsString( esc_url( $url ), do_blocks( '<!-- wp:assemblypress/member-profile {"userId":' . $member . '} /-->' ) );
		$this->assertSame( $url, get_avatar_url( get_userdata( $member )->user_email ) );
		$this->assertNotSame( $url, get_avatar_url( $member, array( 'force_default' => true ) ) );
		update_user_meta( $member, 'assemblypress_avatar_id', 99999999 );
		$this->assertNotSame( $url, get_avatar_url( $member ) );
		update_user_meta( $member, 'assemblypress_avatar_id', self::factory()->post->create() );
		$this->assertNotSame( $url, get_avatar_url( $member ) );
	}

	public function test_private_avatar_is_not_exposed_even_to_owner_in_public_output() {
		$member = self::factory()->user->create();
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		update_user_meta( $member, 'assemblypress_avatar_id', $image );
		$field = Profile_Fields::get_field( 'avatar' );
		Profile_Fields::update_field( $field['id'], array_merge( $field, array( 'visibility' => 'private' ) ) );
		wp_set_current_user( $member );
		$url = wp_get_attachment_url( $image );
		$this->assertNotSame( $url, get_avatar_url( $member ) );
		$this->assertStringNotContainsString( $url, wp_json_encode( Profile_Fields::get_member_profile( $member ) ) );
		$this->assertNotContains( 'avatar', wp_list_pluck( Profile_Fields::get_member_profile( $member )['fields'], 'key' ) );
	}
}
