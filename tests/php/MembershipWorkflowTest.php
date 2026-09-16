<?php
/**
 * Membership, privacy, and composable directory regressions.
 *
 * @package AssemblyPress
 */

use AssemblyPress\Membership;
use AssemblyPress\Settings;
use AssemblyPress\Owner_API;
use AssemblyPress\Profile_Fields;

class MembershipWorkflowTest extends WP_UnitTestCase {
	private $mail = array();

	public function set_up() {
		parent::set_up();
		delete_option( 'assemblypress_settings' );
		Profile_Fields::seed_defaults();
		Membership::register();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
		wp_set_current_user( 0 );
		$_GET = array();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	public function tear_down() {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		delete_option( 'assemblypress_settings' );
		$_GET = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;
		return true;
	}

	private function last_link() {
		preg_match( '~https?://[^\s]+~', end( $this->mail )['message'], $matches );
		parse_str( wp_parse_url( $matches[0], PHP_URL_QUERY ), $params );
		return array( (int) $params['ap_membership'], $params['ap_token'] );
	}

	public function test_registration_creates_no_identity_until_email_verified_and_token_is_single_use() {
		Settings::save( array( 'joining' => 'open' ) );
		$this->assertTrue( Membership::apply( 'new@example.test', 'new_member', 'New member' ) );
		$this->assertFalse( email_exists( 'new@example.test' ) );
		list( $id, $token ) = $this->last_link();
		$data = Membership::get( $id );
		$this->assertNotSame( $token, $data['token_hash'] );
		$this->assertSame( 'pending_email', $data['state'] );
		$this->assertSame( 'active', Membership::redeem( $id, $token )['state'] );
		$this->assertContains( 'subscriber', get_userdata( email_exists( 'new@example.test' ) )->roles );
		$this->assertWPError( Membership::redeem( $id, $token ) );
		$this->assertArrayNotHasKey( 'token_hash', Membership::public_record( Membership::get( $id ) ) );
	}

	public function test_approval_requires_verified_email_then_owner_action() {
		Settings::save( array( 'joining' => 'approval' ) );
		Membership::apply( 'review@example.test', 'review_member', 'Review member' );
		list( $id, $token ) = $this->last_link();
		$this->assertWPError( Membership::transition( $id, 'approve' ) );
		$this->assertSame( 'pending_review', Membership::redeem( $id, $token )['state'] );
		$this->assertFalse( email_exists( 'review@example.test' ) );
		$this->assertSame( 'active', Membership::transition( $id, 'approve' )['state'] );
		$this->assertWPError( Membership::transition( $id, 'approve' ) );
	}

	public function test_invitation_uses_bound_email_and_can_be_revoked() {
		Membership::invite( 'invited@example.test' );
		list( $id, $token ) = $this->last_link();
		$this->assertWPError( Membership::redeem( $id, 'wrong', 'invite_member', 'Invited' ) );
		$this->assertSame( 'active', Membership::redeem( $id, $token, 'invite_member', 'Invited' )['state'] );
		$this->assertNotFalse( email_exists( 'invited@example.test' ) );
		Membership::invite( 'revoked@example.test' );
		list( $id, $token ) = $this->last_link();
		$this->assertSame( 'revoked', Membership::transition( $id, 'revoke' )['state'] );
		$this->assertWPError( Membership::redeem( $id, $token, 'revoked_member', 'Revoked' ) );
	}

	public function test_expired_token_and_resend_rotation() {
		Membership::invite( 'expiry@example.test' );
		list( $id, $old ) = $this->last_link();
		$data = Membership::get( $id );
		$data['expires'] = time() - 1;
		update_post_meta( $id, '_ap_record', $data );
		$this->assertWPError( Membership::redeem( $id, $old, 'expiry_member', 'Expiry' ) );
		$this->assertIsArray( Membership::transition( $id, 'resend' ) );
		list( $id, $fresh ) = $this->last_link();
		$this->assertWPError( Membership::redeem( $id, $old, 'expiry_member', 'Expiry' ) );
		$this->assertSame( 'active', Membership::redeem( $id, $fresh, 'expiry_member', 'Expiry' )['state'] );
	}

	public function test_closed_and_invitation_modes_reject_public_registration() {
		foreach ( array( 'closed', 'invitation' ) as $mode ) {
			Settings::save( array( 'joining' => $mode ) );
			$this->assertWPError( Membership::apply( 'closed@example.test', 'closed_member', 'Closed' ) );
		}
		$this->assertCount( 0, $this->mail );
	}

	public function test_rejected_request_cannot_be_approved_or_redeemed() {
		Settings::save( array( 'joining' => 'approval' ) );
		Membership::apply( 'reject@example.test', 'reject_member', 'Reject' );
		list( $id, $token ) = $this->last_link();
		Membership::redeem( $id, $token );
		$this->assertSame( 'rejected', Membership::transition( $id, 'reject' )['state'] );
		$this->assertWPError( Membership::transition( $id, 'approve' ) );
		$this->assertWPError( Membership::redeem( $id, $token ) );
		$this->assertFalse( email_exists( 'reject@example.test' ) );
	}

	public function test_admin_endpoints_reject_subscriber_and_allow_owner() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		foreach ( array( '/settings', '/admin/members', '/admin/membership' ) as $route ) {
			$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'GET', '/assemblypress/v1' . $route ) )->get_status() );
		}
		$request = new WP_REST_Request( 'POST', '/assemblypress/v1/admin/invitations' );
		$request->set_param( 'email', 'unauthorized@example.test' );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/assemblypress/v1/settings' ) )->get_status() );
	}

	public function test_owner_members_view_and_privacy_apply_to_rest_and_field_blocks() {
		$id = self::factory()->user->create( array( 'role' => 'subscriber', 'display_name' => 'Private Person' ) );
		Settings::save( array( 'directory_audience' => 'members', 'profile_audience' => 'members' ) );
		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'GET', '/assemblypress/v1/members' ) )->get_status() );
		$this->assertWPError( Profile_Fields::get_member_profile( $id ) );
		$this->assertStringNotContainsString( 'Private Person', do_blocks( '<!-- wp:assemblypress/profile-field {"fieldKey":"display_name","userId":' . $id . '} /-->' ) );
		$this->assertStringContainsString( 'Sign in', do_blocks( '<!-- wp:assemblypress/member-directory /-->' ) );
		wp_set_current_user( $id );
		$this->assertIsArray( Profile_Fields::get_member_profile( $id ) );
	}

	public function test_settings_reject_invalid_values_atomically() {
		$before = Settings::get();
		$this->assertWPError( Settings::save( array( 'joining' => 'unexpected' ) ) );
		$this->assertWPError( Settings::save( array( 'eligible_roles' => array( 'made_up_role' ) ) ) );
		$this->assertWPError( Settings::save( array( 'filter_fields' => array( 'user_email' ) ) ) );
		$this->assertSame( $before, Settings::get() );
	}

	public function test_composed_directory_context_and_navigation() {
		$id = self::factory()->user->create( array( 'role' => 'subscriber', 'display_name' => 'Composed member' ) );
		$_GET['member_search'] = 'Composed member';
		$output = do_blocks( '<!-- wp:assemblypress/member-directory --><!-- wp:assemblypress/member-count /--><!-- wp:assemblypress/member-results --><!-- wp:assemblypress/profile-field {"fieldKey":"display_name","showLabel":false,"linkToProfile":true} /--><!-- /wp:assemblypress/member-results --><!-- wp:assemblypress/member-empty --><!-- wp:paragraph --><p>Empty custom copy</p><!-- /wp:paragraph --><!-- /wp:assemblypress/member-empty --><!-- /wp:assemblypress/member-directory -->' );
		$this->assertStringContainsString( '1 member found', $output );
		$this->assertStringContainsString( 'Composed member', $output );
		$this->assertStringContainsString( 'ap_return', $output );
		$this->assertStringNotContainsString( 'Empty custom copy', $output );
		$_GET['member_search'] = 'missing_member';
		$output = do_blocks( '<!-- wp:assemblypress/member-directory --><!-- wp:assemblypress/member-results /--><!-- wp:assemblypress/member-empty --><!-- wp:paragraph --><p>Empty custom copy</p><!-- /wp:paragraph --><!-- /wp:assemblypress/member-empty --><!-- /wp:assemblypress/member-directory -->' );
		$this->assertStringContainsString( 'Empty custom copy', $output );
		$this->assertStringContainsString( 'No members match', $output );
	}
	public function test_duplicate_pending_registrations_do_not_create_extra_records() {
		Settings::save( array( 'joining' => 'open' ) );
		Membership::apply( 'duplicate@example.test', 'duplicate_member', 'Duplicate' );
		list( $id, $token ) = $this->last_link();
		$this->assertTrue( Membership::apply( 'duplicate@example.test', 'another_login', 'Duplicate' ) );
		$this->assertCount( 1, $this->mail );
		$this->assertWPError( Membership::invite( 'duplicate@example.test' ) );
		Membership::recover( 'duplicate@example.test' );
		list( $new_id, $new_token ) = $this->last_link();
		$this->assertSame( $id, $new_id );
		$this->assertNotSame( $token, $new_token );
		$this->assertWPError( Membership::redeem( $id, $token ) );
		$this->assertSame( 'active', Membership::redeem( $id, $new_token )['state'] );
	}

	public function test_failed_mail_remains_recoverable_without_creating_an_account() {
		$fail = static function () { return false; };
		add_filter( 'pre_wp_mail', $fail, 20 );
		$this->assertWPError( Membership::invite( 'failure@example.test' ) );
		list( $id, $token ) = $this->last_link();
		$this->assertFalse( Membership::get( $id )['mail_sent'] );
		$this->assertFalse( email_exists( 'failure@example.test' ) );
		remove_filter( 'pre_wp_mail', $fail, 20 );
		$this->assertIsArray( Membership::transition( $id, 'resend' ) );
		$this->assertTrue( Membership::get( $id )['mail_sent'] );
	}

	public function test_public_filters_never_use_private_or_disabled_fields() {
		$field = Profile_Fields::create_field( array( 'key' => 'specialty', 'label' => 'Specialty', 'type' => 'select', 'visibility' => 'public', 'editable' => true, 'options' => array( array( 'value' => 'design', 'label' => 'Design' ), array( 'value' => 'writing', 'label' => 'Writing' ) ) ) );
		$this->assertIsArray( $field );
		$a = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$b = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $a, $field['meta_key'], 'design' );
		update_user_meta( $b, $field['meta_key'], 'writing' );
		Settings::save( array( 'filter_fields' => array( 'specialty' ) ) );
		$_GET['member_filter_specialty'] = 'design';
		$query = AssemblyPress\Directory_Blocks::query( array() );
		$this->assertSame( array( $a ), array_map( 'intval', wp_list_pluck( $query->get_results(), 'ID' ) ) );
		Profile_Fields::update_field( $field['id'], array_merge( $field, array( 'visibility' => 'private' ) ) );
		$query = AssemblyPress\Directory_Blocks::query( array() );
		$this->assertCount( 2, $query->get_results() );
	}

	public function test_owner_can_edit_owner_only_fields_but_member_cannot() {
		$field = Profile_Fields::create_field( array( 'key' => 'owner_note', 'label' => 'Owner note', 'type' => 'text', 'visibility' => 'private', 'editable' => false ) );
		$id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $id );
		Profile_Fields::update_member_profile( $id, array( 'owner_note' => 'Untrusted' ), true );
		$this->assertSame( '', get_user_meta( $id, $field['meta_key'], true ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'POST', '/assemblypress/v1/admin/members/' . $id );
		$request->set_param( 'fields', array( 'owner_note' => 'Owner saved' ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 'Owner saved', get_user_meta( $id, $field['meta_key'], true ) );
	}

	public function test_joining_submission_rate_limit_is_enforced() {
		Settings::save( array( 'joining' => 'open' ) );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.245';
		$_POST = array( 'assemblypress_join_nonce' => wp_create_nonce( 'assemblypress_join' ), 'ap_email' => 'rate@example.test', 'ap_login' => 'rate_member', 'ap_name' => 'Rate member' );
		try {
			for ( $i = 0; $i < 11; $i++ ) { AssemblyPress\Joining::handle(); }
			$this->assertStringContainsString( 'Please wait before trying again.', do_blocks( '<!-- wp:assemblypress/registration /-->' ) );
			$this->assertCount( 1, $this->mail );
		} finally {
			$_POST = array();
			unset( $_SERVER['REMOTE_ADDR'] );
			AssemblyPress\Joining::handle();
		}
	}

}
