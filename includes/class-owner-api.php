<?php
/**
 * Owner-facing REST resources.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Administrative REST endpoints. */
final class Owner_API {
	/** Register capability-protected resources.
	 *
	 * @return void
	 */
	public static function register() {
		$owner   = static function () {
			return current_user_can( 'manage_options' );
		};
		$members = static function () {
			return current_user_can( 'list_users' );
		};
		$joining = static function () {
			return current_user_can( 'manage_options' ) && current_user_can( 'create_users' );
		};
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $owner,
					'callback'            => static function () {
						return array(
							'settings' => Settings::get(),
							'roles'    => wp_roles()->get_names(),
							'fields'   => Settings::filter_fields(),
							'pages'    => array_map(
								static function ( $page ) {
									return array(
										'id'    => $page->ID,
										'title' => $page->post_title,
									);
								},
								get_pages( array( 'post_status' => 'publish' ) )
							),
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $owner,
					'callback'            => static function ( $request ) {
						return Settings::save( (array) $request->get_json_params() ); },
				),
			)
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/admin/members',
			array(
				'methods'             => 'GET',
				'permission_callback' => $members,
				'callback'            => array( self::class, 'members' ),
				'args'                => self::collection_args(),
			)
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/admin/members/(?P<id>\d+)',
			array(
				'permission_callback' => static function ( $request ) {
					return current_user_can( 'list_users' ) && current_user_can( 'edit_user', (int) $request['id'] ) && is_user_member_of_blog( (int) $request['id'] ); },
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => static function ( $request ) {
					return 'GET' === $request->get_method() ? Profile_Fields::get_member_profile( (int) $request['id'], true ) : Profile_Fields::update_member_profile( (int) $request['id'], $request->get_param( 'fields' ), true );
				},
			)
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/admin/membership',
			array(
				'methods'             => 'GET',
				'permission_callback' => $joining,
				'callback'            => array( self::class, 'records' ),
				'args'                => self::collection_args(),
			)
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/admin/invitations',
			array(
				'methods'             => 'POST',
				'permission_callback' => $joining,
				'args'                => array(
					'email' => array(
						'type'     => 'string',
						'required' => true,
						'format'   => 'email',
					),
				),
				'callback'            => static function ( $request ) {
							return Membership::invite( $request['email'] );
				},
			)
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/admin/membership/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => $joining,
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'approve', 'reject', 'revoke', 'resend' ),
					),
				),
				'callback'            => static function ( $request ) {
							return Membership::transition( (int) $request['id'], $request['action'] );
				},
			)
		);
	}

	/** Collection input schema.
	 *
	 * @return array
	 */
	private static function collection_args() {
		return array(
			'page'        => array(
				'type'    => 'integer',
				'minimum' => 1,
				'default' => 1,
			),
			'per_page'    => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
				'default' => 20,
			),
			'search'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'state'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'eligibility' => array(
				'type'    => 'string',
				'enum'    => array( '', 'listed', 'excluded' ),
				'default' => '',
			),
		);
	}

	/** Paginated member collection.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public static function members( $request ) {
		$limit = min( 100, max( 1, (int) ( $request['per_page'] ?? 20 ) ) );
		$args  = array(
			'number'         => $limit,
			'paged'          => max( 1, (int) $request['page'] ),
			'orderby'        => 'display_name',
			'order'          => 'ASC',
			'search_columns' => array( 'display_name', 'user_login', 'user_email' ),
		);
		if ( $request['search'] ) {
			$args['search'] = '*' . sanitize_text_field( $request['search'] ) . '*';
		}
		if ( 'listed' === $request['eligibility'] ) {
			$args['role__in'] = Settings::get()['eligible_roles'];
			if ( ! $args['role__in'] ) {
				$args['include'] = array( 0 );
			}
		}
		if ( 'excluded' === $request['eligibility'] && Settings::get()['eligible_roles'] ) {
			$args['role__not_in'] = Settings::get()['eligible_roles'];
		}
		$query = new \WP_User_Query( $args );
		return array(
			'items' => array_map(
				static function ( $user ) {
					return array(
						'id'          => $user->ID,
						'name'        => $user->display_name,
						'email'       => $user->user_email,
						'roles'       => implode( ', ', $user->roles ),
						'eligibility' => Settings::eligible( $user->ID ) ? 'listed' : 'excluded',
						'profile_url' => Member_Profiles::get_url( $user->ID ),
						'edit_url'    => get_edit_user_link( $user->ID ),
						'can_edit'    => current_user_can( 'edit_user', $user->ID ),
					);
				},
				$query->get_results()
			),
			'total' => (int) $query->get_total(),
			'pages' => (int) ceil( $query->get_total() / $limit ),
		);
	}

	/** Paginated invitation/request collection.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public static function records( $request ) {
		$limit = min( 100, max( 1, (int) ( $request['per_page'] ?? 20 ) ) );
		$query = new \WP_Query(
			array(
				'post_type'      => 'ap_membership',
				'post_status'    => 'private',
				'posts_per_page' => $limit,
				'paged'          => max( 1, (int) $request['page'] ),
				's'              => sanitize_text_field( $request['search'] ?? '' ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded, paginated owner workflow filtered by one state.
				'meta_query'     => $request['state'] ? array(
					array(
						'key'   => '_ap_state',
						'value' => sanitize_key( $request['state'] ),
					),
				) : array(),
			)
		);
		return array(
			'items' => array_map(
				static function ( $post ) {
					return Membership::public_record( Membership::get( $post->ID ) );
				},
				$query->posts
			),
			'total' => $query->found_posts,
			'pages' => $query->max_num_pages,
		);
	}
}
