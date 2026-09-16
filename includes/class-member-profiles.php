<?php
/**
 * Member destinations, block context, and local avatars.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

/**
 * Connects WordPress users to shared member surfaces.
 */
final class Member_Profiles {
	/**
	 * Register public query variables.
	 *
	 * @param array $vars Query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'assemblypress_profile';
		$vars[] = 'assemblypress_edit';
		return $vars;
	}

	/**
	 * Register routes without changing WordPress author archives.
	 *
	 * @return void
	 */
	public static function register_routes() {
		add_rewrite_rule( '^members/([^/]+)/edit/?$', 'index.php?author_name=$matches[1]&assemblypress_profile=1&assemblypress_edit=1', 'top' );
		add_rewrite_rule( '^members/([^/]+)/?$', 'index.php?author_name=$matches[1]&assemblypress_profile=1', 'top' );
	}

	/**
	 * Remove plugin rules when deactivated, retaining member data and templates.
	 *
	 * @return void
	 */
	public static function deactivate() {
		global $wp_rewrite;
		unset( $wp_rewrite->extra_rules_top['^members/([^/]+)/edit/?$'], $wp_rewrite->extra_rules_top['^members/([^/]+)/?$'] );
		flush_rewrite_rules( false );
		delete_option( 'assemblypress_profile_routes_version' );
	}

	/**
	 * Refresh routing once when an existing installation receives these routes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_routes() {
		if ( '1' !== get_option( 'assemblypress_profile_routes_version' ) ) {
			flush_rewrite_rules( false );
			update_option( 'assemblypress_profile_routes_version', '1' );
		}
	}

	/**
	 * Generate the public or edit destination for a member of this site.
	 *
	 * @param int  $user_id Member ID.
	 * @param bool $edit    Whether to link to editing.
	 * @return string
	 */
	public static function get_url( $user_id, $edit = false ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_user_member_of_blog( $user->ID ) ) {
			return '';
		}
		if ( get_option( 'permalink_structure' ) ) {
			global $wp_rewrite;
			$prefix = $wp_rewrite->using_index_permalinks() ? $wp_rewrite->index . '/' : '';
			return home_url( user_trailingslashit( $prefix . 'members/' . $user->user_nicename . ( $edit ? '/edit' : '' ) ) );
		}
		$args = array(
			'author_name'           => $user->user_nicename,
			'assemblypress_profile' => 1,
		);
		if ( $edit ) {
			$args['assemblypress_edit'] = 1;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Whether this is a member destination.
	 *
	 * @return bool
	 */
	public static function is_request() {
		return '1' === (string) get_query_var( 'assemblypress_profile' );
	}

	/**
	 * Get the queried member, never treating a post or page ID as a user ID.
	 *
	 * @return int
	 */
	public static function queried_member_id() {
		$object = get_queried_object();
		return is_author() && $object instanceof \WP_User && is_user_member_of_blog( $object->ID ) ? (int) $object->ID : 0;
	}

	/**
	 * Resolve explicit selection, inherited context, request, then current user.
	 * Invalid explicit or route context must not fall back to the viewer.
	 *
	 * @param array          $attributes Block attributes.
	 * @param \WP_Block|null $block     Rendered block.
	 * @return int
	 */
	public static function resolve_member_id( $attributes, $block = null ) {
		if ( ! empty( $attributes['userId'] ) ) {
			$id = (int) $attributes['userId'];
		} elseif ( $block && isset( $block->context['assemblypress/memberId'] ) ) {
			$id = (int) $block->context['assemblypress/memberId'];
		} elseif ( self::is_request() || is_author() ) {
			$id = self::queried_member_id();
		} else {
			$id = get_current_user_id();
		}
		return $id > 0 && get_userdata( $id ) && is_user_member_of_blog( $id ) ? $id : 0;
	}

	/**
	 * Supply member context to all blocks on member and author destinations.
	 *
	 * @param array $context Block context.
	 * @return array
	 */
	public static function block_context( $context ) {
		if ( ! isset( $context['assemblypress/memberId'] ) && ( self::is_request() || is_author() ) ) {
			$context['assemblypress/memberId'] = self::queried_member_id();
		}
		return $context;
	}

	/**
	 * Whether the current request can edit the signed-in member.
	 *
	 * @return bool
	 */
	public static function can_edit_request() {
		return ! self::is_request() || ( get_current_user_id() && get_current_user_id() === self::queried_member_id() );
	}

	/**
	 * Reject missing members and prevent personalized edit pages being cached.
	 *
	 * @return void
	 */
	public static function prepare_request() {
		if ( ! self::is_request() ) {
			return;
		}
		if ( ! Settings::can_browse( 'profile' ) && ! current_user_can( 'edit_user', self::queried_member_id() ) && ! get_query_var( 'assemblypress_edit' ) ) {
			nocache_headers();
			auth_redirect();
		}
		if ( ! self::queried_member_id() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		} elseif ( get_query_var( 'assemblypress_edit' ) ) {
			nocache_headers();
			if ( is_user_logged_in() && ! self::can_edit_request() ) {
				status_header( 403 );
			}
		}
	}

	/**
	 * Keep member destinations independent of author archive canonical redirects.
	 *
	 * @param string|false $redirect Proposed canonical redirect.
	 * @return string|false
	 */
	public static function canonical_redirect( $redirect ) {
		return self::is_request() ? false : $redirect;
	}

	/**
	 * Register shared templates that owners can customize in the Site Editor.
	 *
	 * @return void
	 */
	public static function register_templates() {
		foreach ( array(
			'member-profile' => __( 'Member profile', 'assemblypress' ),
			'edit-profile'   => __( 'Edit member profile', 'assemblypress' ),
		) as $slug => $title ) {
			register_block_template(
				'assemblypress//assemblypress-' . $slug,
				array(
					'title'       => $title,
					'description' => __( 'Shared AssemblyPress member template.', 'assemblypress' ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the bundled local block template.
					'content'     => file_get_contents( ASSEMBLYPRESS_PLUGIN_DIR . 'templates/' . $slug . '.html' ),
				)
			);
		}
	}

	/**
	 * Prefer the member template only for plugin destinations.
	 *
	 * @param array $templates Template candidates.
	 * @return array
	 */
	public static function template_hierarchy( $templates ) {
		if ( self::is_request() && ! is_404() ) {
			array_unshift( $templates, get_query_var( 'assemblypress_edit' ) ? 'assemblypress-edit-profile.php' : 'assemblypress-member-profile.php' );
		}
		return $templates;
	}

	/**
	 * Provide a minimal fallback for themes without block template support.
	 *
	 * @param string $template Selected template path.
	 * @return string
	 */
	public static function classic_template( $template ) {
		return self::is_request() && ! is_404() && ! current_theme_supports( 'block-templates' ) ? ASSEMBLYPRESS_PLUGIN_DIR . 'includes/templates/member-profile.php' : $template;
	}

	/**
	 * Give the page a member-specific document title.
	 *
	 * @param array $parts Document title parts.
	 * @return array
	 */
	public static function document_title( $parts ) {
		if ( self::is_request() && ! is_404() ) {
			$user = get_userdata( self::queried_member_id() );
			if ( $user ) {
				$parts['title'] = get_query_var( 'assemblypress_edit' ) ? __( 'Edit profile', 'assemblypress' ) : $user->display_name;
			}
		}
		return $parts;
	}

	/**
	 * Keep edit destinations out of search results.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::is_request() && get_query_var( 'assemblypress_edit' ) ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/**
	 * Use public profile images throughout WordPress's avatar APIs.
	 * Private or invalid images retain WordPress's normal fallback behavior.
	 *
	 * @param array $args     Avatar arguments.
	 * @param mixed $identity User, post, comment, ID, or email.
	 * @return array
	 */
	public static function avatar_data( $args, $identity ) {
		if ( ! empty( $args['force_default'] ) ) {
			return $args;
		}
		$user = false;
		if ( $identity instanceof \WP_User ) {
			$user = $identity;
		} elseif ( $identity instanceof \WP_Post ) {
			$user = get_userdata( $identity->post_author );
		} elseif ( $identity instanceof \WP_Comment ) {
			if ( ! in_array( $identity->comment_type, apply_filters( 'get_avatar_comment_types', array( 'comment', 'note' ) ), true ) ) {
				return $args;
			}
			$user = $identity->user_id ? get_userdata( $identity->user_id ) : get_user_by( 'email', $identity->comment_author_email );
		} elseif ( is_numeric( $identity ) ) {
			$user = get_userdata( $identity );
		} elseif ( is_string( $identity ) ) {
			$user = get_user_by( 'email', $identity );
		}
		$field = Profile_Fields::get_field( 'avatar' );
		if ( ! $user || ! is_user_member_of_blog( $user->ID ) || ! $field || 'image' !== $field['type'] || 'public' !== $field['visibility'] ) {
			return $args;
		}
		$id = absint( Profile_Fields::get_value( $user, $field ) );
		if ( ! $id || ! wp_attachment_is_image( $id ) || 'inherit' !== get_post( $id )->post_status ) {
			return $args;
		}
		$parent_id = wp_get_post_parent_id( $id );
		if ( $parent_id && ( 'publish' !== get_post_status( $parent_id ) || ! empty( get_post( $parent_id )->post_password ) ) ) {
			return $args;
		}
		$url = wp_get_attachment_image_url( $id, array( (int) $args['size'], (int) $args['size'] ) );
		if ( $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;
		}
		return $args;
	}
}
