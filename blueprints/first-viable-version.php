<?php
/**
 * Playground setup for the AssemblyPress first viable version demo.
 *
 * @package AssemblyPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AssemblyPress\\Profile_Fields' ) ) {
	throw new RuntimeException( 'AssemblyPress must be active before the demo content can be installed.' );
}

AssemblyPress\Capabilities::grant_default_role_capabilities();
AssemblyPress\Config_Entities::register();
AssemblyPress\Profile_Field_Groups::ensure_starter_groups();
AssemblyPress\Profile_Fields::seed_defaults();

update_option( 'blogname', 'AssemblyPress demo' );
update_option( 'blogdescription', 'A first viable member/profile loop.' );

$get_group_id = static function ( $key ) {
	$group = AssemblyPress\Profile_Field_Groups::get_group( $key );

	if ( ! $group ) {
		throw new RuntimeException( 'Expected profile field group is missing: ' . esc_html( $key ) );
	}

	return (int) $group['id'];
};

$ensure_field = static function ( $field ) {
	$existing = AssemblyPress\Profile_Fields::get_field( $field['key'] );

	if ( $existing ) {
		return $existing;
	}

	$created = AssemblyPress\Profile_Fields::create_field( $field );

	if ( is_wp_error( $created ) ) {
		throw new RuntimeException( esc_html( $created->get_error_message() ) );
	}

	return $created;
};

$ensure_field(
	array(
		'key'        => 'preferred_contact',
		'label'      => 'Preferred contact',
		'type'       => 'select',
		'visibility' => 'public',
		'editable'   => true,
		'required'   => false,
		'group_id'   => $get_group_id( 'contact' ),
		'order'      => 90,
		'options'    => array(
			array(
				'value' => 'email',
				'label' => 'Email',
			),
			array(
				'value' => 'site_message',
				'label' => 'Site message',
			),
		),
	)
);

$ensure_field(
	array(
		'key'        => 'member_note',
		'label'      => 'Member note',
		'type'       => 'text',
		'visibility' => 'private',
		'editable'   => true,
		'required'   => false,
		'group_id'   => $get_group_id( 'about' ),
		'order'      => 95,
	)
);

$ensure_member = static function ( $login, $display_name, $email, $values ) {
	$user = get_user_by( 'login', $login );

	if ( ! $user ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 24, true ),
				'user_email'   => $email,
				'display_name' => $display_name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			throw new RuntimeException( esc_html( $user_id->get_error_message() ) );
		}

		$user = get_user_by( 'id', $user_id );
	}

	wp_update_user(
		array(
			'ID'           => $user->ID,
			'display_name' => $display_name,
		)
	);

	$result = AssemblyPress\Profile_Fields::update_member_profile( $user->ID, $values );

	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( esc_html( $result->get_error_message() ) );
	}

	return (int) $user->ID;
};

$mina_id = $ensure_member(
	'mina_member',
	'Mina Member',
	'mina@example.test',
	array(
		'display_name'      => 'Mina Member',
		'bio'               => 'Builds small, intentional communities on WordPress.',
		'location'          => 'Toronto',
		'website'           => 'https://example.test/mina',
		'preferred_contact' => 'site_message',
		'member_note'       => 'Private setup note for the member.',
	)
);

$ensure_member(
	'owen_member',
	'Owen Member',
	'owen@example.test',
	array(
		'display_name'      => 'Owen Member',
		'bio'               => 'Keeps the community garden tidy and useful.',
		'location'          => 'Vancouver',
		'website'           => 'https://example.test/owen',
		'preferred_contact' => 'email',
		'member_note'       => 'Private onboarding note for Owen.',
	)
);

$upsert_page = static function ( $slug, $title, $content ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$data = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);

	if ( $page ) {
		$data['ID'] = $page->ID;
		$page_id    = wp_update_post( $data, true );
	} else {
		$page_id = wp_insert_post( $data, true );
	}

	if ( is_wp_error( $page_id ) ) {
		throw new RuntimeException( esc_html( $page_id->get_error_message() ) );
	}

	return (int) $page_id;
};

$directory_page_id = $upsert_page(
	'member-directory',
	'Member directory',
	'<!-- wp:heading --><h2>Members</h2><!-- /wp:heading -->' . "\n\n" .
	'<!-- wp:assemblypress/member-directory {"perPage":12} /-->'
);

$upsert_page(
	'member-profile',
	'Member profile',
	'<!-- wp:assemblypress/member-profile {"userId":' . $mina_id . '} /-->' . "\n\n" .
	'<!-- wp:assemblypress/profile-field {"fieldKey":"location","userId":' . $mina_id . '} /-->' . "\n\n" .
	'<!-- wp:assemblypress/profile-field {"fieldKey":"member_note","userId":' . $mina_id . '} /-->'
);

$upsert_page(
	'edit-profile',
	'Edit profile',
	'<!-- wp:heading --><h2>Edit your member profile</h2><!-- /wp:heading -->' . "\n\n" .
	'<!-- wp:paragraph --><p>Log in as a member to update AssemblyPress-owned profile values.</p><!-- /wp:paragraph -->' . "\n\n" .
	'<!-- wp:assemblypress/edit-profile-form /-->'
);

update_option( 'page_on_front', $directory_page_id );
update_option( 'show_on_front', 'page' );
