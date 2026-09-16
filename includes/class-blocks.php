<?php
/**
 * Dynamic blocks.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders AssemblyPress blocks.
 */
final class Blocks {
	/**
	 * Values retained only for the current failed request.
	 *
	 * @var array
	 */
	private static $submitted_values = array();

	/**
	 * Current request error, never placed in a URL or persisted.
	 *
	 * @var string
	 */
	private static $form_error = '';

	/**
	 * Register blocks from metadata.
	 *
	 * @return void
	 */
	public static function register() {
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/member-directory' );
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/member-profile' );
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/profile-field' );
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/edit-profile-form' );
		register_block_pattern_category( 'assemblypress', array( 'label' => __( 'Member profiles', 'assemblypress' ) ) );
		register_block_pattern(
			'assemblypress/member-profile',
			array(
				'title'      => __( 'Member profile with editable fields', 'assemblypress' ),
				'categories' => array( 'assemblypress' ),
				'content'    => '<!-- wp:assemblypress/member-profile {"showFields":false} /--><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:assemblypress/profile-field {"fieldKey":"bio"} /--><!-- wp:assemblypress/profile-field {"fieldKey":"location"} /--><!-- wp:assemblypress/profile-field {"fieldKey":"website"} /--></div><!-- /wp:group -->',
			)
		);
	}

	/**
	 * Render the member directory block.
	 *
	 * @param array          $attributes Block attributes.
	 * @param \WP_Block|null $block      Directory block.
	 * @return string
	 */
	public static function render_member_directory( $attributes, $block = null ) {
		if ( ! Settings::can_browse() ) {
			return '<p><a href="' . esc_url( wp_login_url( Settings::directory_url() ) ) . '">' . esc_html__( 'Sign in to browse members.', 'assemblypress' ) . '</a></p>';
		}
		if ( $block && has_block( 'assemblypress/member-results', serialize_blocks( $block->parsed_block['innerBlocks'] ) ) ) {
			$output = '';
			foreach ( $block->inner_blocks as $child ) {
				$output .= $child->render(); }
			return '<div ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-member-directory' ) ) . '>' . $output . '</div>';
		}
		$limit = isset( $attributes['perPage'] ) ? max( 1, min( 100, (int) $attributes['perPage'] ) ) : 12;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public directory filters are read-only.
		$search = isset( $_GET['member_search'] ) ? sanitize_text_field( wp_unslash( $_GET['member_search'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public pagination is read-only.
		$page  = isset( $_GET['member_page'] ) ? max( 1, absint( $_GET['member_page'] ) ) : 1;
		$query = Directory_Blocks::query( $attributes );

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'assemblypress-member-directory' ) );
		$output             = '<div ' . $wrapper_attributes . '>';
		$search_id          = wp_unique_id( 'assemblypress-member-search-' );
		$output            .= '<form method="get"><label for="' . esc_attr( $search_id ) . '">' . esc_html__( 'Search members', 'assemblypress' ) . '</label><input type="search" id="' . esc_attr( $search_id ) . '" name="member_search" value="' . esc_attr( $search ) . '"><button type="submit">' . esc_html__( 'Search', 'assemblypress' ) . '</button></form>';
		if ( ! $query->get_results() ) {
			$output .= '<p>' . esc_html__( 'No members found. Try a different name.', 'assemblypress' ) . '</p>';
		}
		$output .= '<ul class="assemblypress-member-directory__list">';

		foreach ( $query->get_results() as $user ) {
			$profile = Profile_Fields::get_member_profile( $user->ID );
			if ( is_wp_error( $profile ) ) {
				continue;
			}

			$output .= '<li class="assemblypress-member-directory__member">';
			if ( $block && count( $block->inner_blocks ) ) {
				foreach ( $block->parsed_block['innerBlocks'] as $inner ) {
					$member_block = new \WP_Block(
						$inner,
						array(
							'assemblypress/memberId'    => $user->ID,
							'assemblypress/inDirectory' => true,
						)
					);
					$output      .= $member_block->render();
				}
				$output .= '</li>';
				continue;
			}
			$output .= '<a class="assemblypress-member-directory__link" href="' . esc_url( Directory_Blocks::profile_url( $user->ID ) ) . '">';
			$output .= get_avatar( $user->ID, 64, '', '', array( 'class' => 'assemblypress-member-directory__avatar' ) );
			$output .= '<span class="assemblypress-member-directory__name">' . esc_html( $profile['display_name'] ) . '</span>';
			$output .= '</a>';
			$output .= '</li>';
		}

		$output .= '</ul>';
		$pages   = (int) ceil( $query->get_total() / $limit );
		if ( $pages > 1 ) {
			$output .= '<nav aria-label="' . esc_attr__( 'Member directory pages', 'assemblypress' ) . '">' . paginate_links(
				array(
					'base'    => add_query_arg( 'member_page', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => $pages,
				)
			) . '</nav>';
		}
		if ( is_user_logged_in() ) {
			$output .= '<p><a href="' . esc_url( Member_Profiles::get_url( get_current_user_id() ) ) . '">' . esc_html__( 'View my profile', 'assemblypress' ) . '</a></p>';
		}
		$output .= '</div>';
		return $output;
	}

	/**
	 * Render the member profile block.
	 *
	 * @param array          $attributes Block attributes.
	 * @param \WP_Block|null $block      Rendered block.
	 * @return string
	 */
	public static function render_member_profile( $attributes, $block = null ) {
		$user_id = Member_Profiles::resolve_member_id( $attributes, $block );

		$profile = Profile_Fields::get_member_profile( $user_id );
		if ( is_wp_error( $profile ) ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'assemblypress-member-profile' ) );
		$output             = '<article ' . $wrapper_attributes . '>';
		$output            .= get_avatar( $profile['id'], 96, '', '', array( 'class' => 'assemblypress-member-profile__avatar' ) );
		$heading_tag        = Member_Profiles::is_request() ? 'h1' : 'h2';
		$output            .= '<' . $heading_tag . ' class="assemblypress-member-profile__name">' . esc_html( $profile['display_name'] ) . '</' . $heading_tag . '>';
		if ( get_current_user_id() === $user_id ) {
			$output .= '<p><a href="' . esc_url( Member_Profiles::get_url( $user_id, true ) ) . '">' . esc_html__( 'Edit my profile', 'assemblypress' ) . '</a></p>';
		}

		if ( isset( $attributes['showFields'] ) && ! $attributes['showFields'] ) {
			return $output . '</article>';
		}
		foreach ( $profile['groups'] as $group ) {
			$group_output = '';

			foreach ( $group['fields'] as $field ) {
				if ( in_array( $field['key'], array( 'display_name', 'avatar' ), true ) || Profile_Fields::sanitize_value( $field['value'], $field ) === '' ) {
					continue;
				}
				$group_output .= self::render_field_definition( $field );
			}

			if ( '' === $group_output ) {
				continue;
			}

			$output .= '<section class="assemblypress-member-profile__group">';
			$output .= '<h3 class="assemblypress-member-profile__group-title">' . esc_html( $group['label'] ) . '</h3>';
			$output .= '<dl class="assemblypress-member-profile__fields">' . $group_output . '</dl>';
			$output .= '</section>';
		}

		$output .= '</article>';
		return $output;
	}

	/**
	 * Render one profile field block.
	 *
	 * @param array          $attributes Block attributes.
	 * @param \WP_Block|null $block      Rendered block.
	 * @return string
	 */
	public static function render_profile_field( $attributes, $block = null ) {
		$field_key = sanitize_key( $attributes['fieldKey'] ?? '' );
		$user_id   = Member_Profiles::resolve_member_id( $attributes, $block );
		$field     = Profile_Fields::get_field( $field_key );
		$user      = get_userdata( $user_id );

		if ( ! $field || ! $user || ( ! Settings::can_browse( 'profile' ) && ! current_user_can( 'edit_user', $user_id ) ) ) {
			return '';
		}

		if ( 'private' === $field['visibility'] && ! current_user_can( 'edit_user', $user_id ) ) {
			return '';
		}

		$field['value'] = Profile_Fields::get_value( $user, $field );
		$field_output   = self::render_field_definition( $field );
		if ( isset( $attributes['showLabel'] ) && ! $attributes['showLabel'] ) {
			$field_output = preg_replace( '/<dt>.*?<\/dt>/s', '', $field_output );
		}
		if ( ! empty( $attributes['headingLevel'] ) && 'display_name' === $field_key ) {
			$level        = max( 1, min( 6, absint( $attributes['headingLevel'] ) ) );
			$field_output = str_replace( array( '<dd>', '</dd>' ), array( '<dd><h' . $level . '>', '</h' . $level . '></dd>' ), $field_output );
		}
		if ( ! empty( $attributes['linkToProfile'] ) && $field_output && ! in_array( $field['type'], array( 'url', 'social_links' ), true ) ) {
			$field_output = str_replace( array( '<dd>', '</dd>' ), array( '<dd><a href="' . esc_url( ! empty( $block->context['assemblypress/inDirectory'] ) ? Directory_Blocks::profile_url( $user_id ) : Member_Profiles::get_url( $user_id ) ) . '">', '</a></dd>' ), $field_output );
		}

		if ( '' === $field_output ) {
			return '';
		}

		if ( isset( $attributes['showLabel'] ) && ! $attributes['showLabel'] ) {
			return '<div ' . get_block_wrapper_attributes() . '>' . str_replace( array( '<dd>', '</dd>' ), array( '<div>', '</div>' ), $field_output ) . '</div>';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-profile-field' ) ) . '><dl class="assemblypress-profile-field__fields">' . $field_output . '</dl></div>';
	}

	/**
	 * Handle temporary profile value editor submissions before blocks render.
	 *
	 * @return void
	 */
	public static function maybe_handle_edit_profile_form_submission() {
		self::$form_error       = '';
		self::$submitted_values = array();
		$request_method         = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $request_method || ! isset( $_POST['assemblypress_profile_nonce'] ) ) {
			return;
		}

		$status = 'error';
		$notice = __( 'Your profile could not be saved. Please refresh and try again.', 'assemblypress' );

		if ( is_user_logged_in() && Member_Profiles::can_edit_request() ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['assemblypress_profile_nonce'] ) );

			if ( wp_verify_nonce( $nonce, 'assemblypress_update_profile' ) ) {
				$values                 = self::get_submitted_profile_form_values();
				self::$submitted_values = $values;
				$uploaded               = array();
				$result                 = self::process_profile_images( $values, $uploaded );
				if ( ! is_wp_error( $result ) ) {
					$result = Profile_Fields::update_member_profile( get_current_user_id(), $values );
				}
				if ( is_wp_error( $result ) ) {
					foreach ( $uploaded as $attachment_id ) {
						wp_delete_attachment( $attachment_id, true );
					}
				}

				if ( is_wp_error( $result ) ) {
					$notice = $result->get_error_message();
				} else {
					$status = 'success';
					$notice = __( 'Profile saved.', 'assemblypress' );
				}
			}
		}

		if ( 'error' === $status ) {
			self::$form_error = $notice;
			nocache_headers();
			return;
		}

		$redirect_url = remove_query_arg( array( 'assemblypress_profile_status', 'assemblypress_profile_notice' ), self::get_profile_form_redirect_url() );
		$redirect_url = add_query_arg(
			array(
				'assemblypress_profile_status' => $status,
				'assemblypress_profile_notice' => $notice,
			),
			$redirect_url
		);

		if ( wp_safe_redirect( $redirect_url ) ) {
			exit;
		}
	}

	/**
	 * Get the temporary profile value editor redirect target.
	 *
	 * @return string
	 */
	private static function get_profile_form_redirect_url() {
		if ( Member_Profiles::is_request() && Member_Profiles::queried_member_id() ) {
			return Member_Profiles::get_url( Member_Profiles::queried_member_id(), true );
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		return wp_get_referer() ? wp_get_referer() : home_url( '/' );
	}

	/**
	 * Render the temporary profile value editing test surface.
	 *
	 * @return string
	 */
	public static function render_edit_profile_form() {
		if ( ! is_user_logged_in() ) {
			$login_form = wp_login_form(
				array(
					'echo'     => false,
					'redirect' => self::get_profile_form_redirect_url(),
				)
			);

			return '<div ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-edit-profile-form' ) ) . '><p>' . esc_html__( 'Log in to edit your profile.', 'assemblypress' ) . '</p>' . $login_form . '<p><a href="' . esc_url( wp_lostpassword_url( self::get_profile_form_redirect_url() ) ) . '">' . esc_html__( 'Reset your password', 'assemblypress' ) . '</a></p></div>';
		}

		if ( ! Member_Profiles::can_edit_request() ) {
			return '<p>' . esc_html__( 'You can only edit your own profile.', 'assemblypress' ) . ' <a href="' . esc_url( Member_Profiles::get_url( get_current_user_id(), true ) ) . '">' . esc_html__( 'Edit my profile', 'assemblypress' ) . '</a></p>';
		}

		wp_enqueue_style( 'wp-components' );
		$user   = get_userdata( get_current_user_id() );
		$notice = self::get_profile_form_notice();
		$fields = self::get_profile_form_fields();

		$output  = Member_Profiles::is_request() ? '<h1>' . esc_html__( 'Edit profile', 'assemblypress' ) . '</h1>' : '';
		$output .= '<form method="post" enctype="multipart/form-data" ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-edit-profile-form' ) ) . '>';
		$output .= $notice;
		foreach ( Profile_Fields::group_fields( $fields ) as $group ) {
			$output .= '<fieldset class="assemblypress-edit-profile-form__group">';
			$output .= '<legend>' . esc_html( $group['label'] ) . '</legend>';
			foreach ( $group['fields'] as $field ) {
				$value    = array_key_exists( $field['key'], self::$submitted_values ) ? self::$submitted_values[ $field['key'] ] : Profile_Fields::get_value( $user, $field );
				$name     = 'assemblypress_' . $field['key'];
				$output  .= '<div class="assemblypress-edit-profile-form__field">';
				$output  .= '<label for="' . esc_attr( $name ) . '">' . esc_html( $field['label'] ) . ( $field['required'] ? ' ' . esc_html__( '(required)', 'assemblypress' ) : '' ) . '</label>';
				$output  .= self::render_input( $name, $field, $value );
				$audience = 'private' === $field['visibility'] ? __( 'Visible to you and authorized site editors.', 'assemblypress' ) : __( 'Public: visible to anyone who visits your profile.', 'assemblypress' );
				$output  .= '<span id="' . esc_attr( $name . '_help' ) . '" class="assemblypress-edit-profile-form__help">' . esc_html( $field['description'] . ' ' . $audience ) . '</span>';
				$output  .= '</div>';
			}
			$output .= '</fieldset>';
		}
		$output .= wp_nonce_field( 'assemblypress_update_profile', 'assemblypress_profile_nonce', true, false );
		$output .= '<button type="submit">' . esc_html__( 'Save profile', 'assemblypress' ) . '</button>';
		$output .= '</form>';
		$output .= '<p><a href="' . esc_url( Member_Profiles::get_url( $user->ID ) ) . '">' . esc_html__( 'View my profile', 'assemblypress' ) . '</a></p>';

		return $output;
	}

	/**
	 * Get editable fields for the temporary profile value editor.
	 *
	 * @return array
	 */
	private static function get_profile_form_fields() {
		return array_filter(
			Profile_Fields::get_fields(),
			static function ( $field ) {
				return ! empty( $field['editable'] ) && 'social_links' !== $field['type'];
			}
		);
	}

	/**
	 * Get submitted temporary profile value editor values.
	 *
	 * @return array
	 */
	private static function get_submitted_profile_form_values() {
		$values = array();

		foreach ( self::get_profile_form_fields() as $field ) {
			$input_key = 'assemblypress_' . $field['key'];

			if ( 'image' === $field['type'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies nonce.
				$values[ $field['key'] ] = ! empty( $_POST[ $input_key . '_remove' ] ) ? 0 : Profile_Fields::get_value( wp_get_current_user(), $field );
				continue;
			}
			if ( 'checkbox' === $field['type'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before submitted profile form values are read.
				$values[ $field['key'] ] = isset( $_POST[ $input_key ] ) ? '1' : '';
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified before reads; Profile_Fields::update_member_profile() sanitizes values by field type.
			$values[ $field['key'] ] = isset( $_POST[ $input_key ] ) ? wp_unslash( $_POST[ $input_key ] ) : '';
		}

		return $values;
	}

	/**
	 * Handle tightly scoped member image uploads after authentication and nonce checks.
	 *
	 * @param array $values   Pending field values.
	 * @param array $uploaded New attachment IDs to remove if validation fails.
	 * @return true|\WP_Error
	 */
	private static function process_profile_images( &$values, &$uploaded ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		foreach ( self::get_profile_form_fields() as $field ) {
			if ( 'image' !== $field['type'] ) {
				continue;
			}
			$name = 'assemblypress_' . $field['key'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce and self-edit permission.
			if ( ! empty( $_POST[ $name . '_remove' ] ) ) {
				$values[ $field['key'] ] = 0;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Native upload handler validates file data below.
			$file = $_FILES[ $name ] ?? null;
			if ( ! $file || UPLOAD_ERR_NO_FILE === $file['error'] ) {
				continue;
			}
			if ( 'private' === $field['visibility'] || UPLOAD_ERR_OK !== $file['error'] || $file['size'] > min( 2 * MB_IN_BYTES, wp_max_upload_size() ) ) {
				return new \WP_Error( 'assemblypress_image_upload', __( 'Choose a public profile image under 2 MB, then save again. Your other changes are still shown below.', 'assemblypress' ) );
			}
			$size = wp_getimagesize( $file['tmp_name'] );
			if ( ! $size || ! in_array( $size['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) || max( $size[0], $size[1] ) > 4096 ) {
				return new \WP_Error( 'assemblypress_invalid_image', __( 'Choose a JPEG, PNG, or WebP image no larger than 4096 pixels per side. Select the image again after correcting the problem.', 'assemblypress' ) );
			}
			$id = media_handle_upload(
				$name,
				0,
				array( 'post_author' => get_current_user_id() ),
				array(
					'test_form' => false,
					'mimes'     => array(
						'jpg|jpeg|jpe' => 'image/jpeg',
						'png'          => 'image/png',
						'webp'         => 'image/webp',
					),
				)
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$uploaded[]              = $id;
			$values[ $field['key'] ] = $id;
		}
		return true;
	}

	/**
	 * Get redirected temporary profile value editor feedback.
	 *
	 * @return string
	 */
	private static function get_profile_form_notice() {
		if ( self::$form_error ) {
			return self::render_profile_notice( self::$form_error, 'error' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirected feedback is display-only and escaped before output.
		$status = isset( $_GET['assemblypress_profile_status'] ) ? sanitize_key( wp_unslash( $_GET['assemblypress_profile_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirected feedback is display-only and escaped before output.
		$notice = isset( $_GET['assemblypress_profile_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['assemblypress_profile_notice'] ) ) : '';

		if ( ! $status || ! $notice ) {
			return '';
		}

		return self::render_profile_notice( $notice, $status );
	}

	/**
	 * Render profile editing feedback.
	 *
	 * @param string $message Notice message.
	 * @param string $status  Notice status.
	 * @return string
	 */
	private static function render_profile_notice( $message, $status ) {
		$status = 'success' === $status ? 'success' : 'error';
		$role   = 'error' === $status ? 'alert' : 'status';

		return '<div class="assemblypress-edit-profile-form__notice is-' . esc_attr( $status ) . '" role="' . esc_attr( $role ) . '">' . esc_html( $message ) . '</div>';
	}

	/**
	 * Render a field definition/value pair.
	 *
	 * @param array $field Field with value.
	 * @return string
	 */
	private static function render_field_definition( $field ) {
		$value = $field['value'];
		if ( 'social_links' === $field['type'] && is_array( $value ) ) {
			if ( ! $value ) {
				return '';
			}
			$rendered = '<ul class="assemblypress-profile-field__social-links">';
			foreach ( $value as $link ) {
				if ( empty( $link['url'] ) ) {
					continue;
				}
					$link_label = $link['label'] ? $link['label'] : $link['url'];
					$rendered  .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link_label ) . '</a></li>';
			}
			$rendered .= '</ul>';
		} elseif ( 'url' === $field['type'] && $value ) {
			$rendered = '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
		} elseif ( 'image' === $field['type'] ) {
			$attachment_id = absint( $value );
			$rendered      = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail' ) : '';
		} else {
			$rendered = esc_html( (string) $value );
		}

		if ( '' === $rendered ) {
			return '';
		}

		return '<div class="assemblypress-profile-field__item"><dt>' . esc_html( $field['label'] ) . '</dt><dd>' . $rendered . '</dd></div>';
	}

	/**
	 * Render one profile value input.
	 *
	 * @param string $name  Input name.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 * @return string
	 */
	private static function render_input( $name, $field, $value ) {
		$value     = is_scalar( $value ) ? $value : '';
		$required  = ! empty( $field['required'] ) ? ' required="required"' : '';
		$required .= ' aria-describedby="' . esc_attr( $name . '_help' ) . '"';

		if ( 'textarea' === $field['type'] ) {
			return '<textarea id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . $required . '>' . esc_textarea( (string) $value ) . '</textarea>';
		}

		if ( 'checkbox' === $field['type'] ) {
			return '<input id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="checkbox" value="1" ' . checked( (bool) $value, true, false ) . $required . ' />';
		}

		if ( 'select' === $field['type'] ) {
			$output = '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . $required . '>';
			if ( empty( $field['required'] ) ) {
				$output .= '<option value=""></option>';
			}
			foreach ( $field['options'] as $option ) {
				$option_value = $option['value'] ?? '';
				$output      .= '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option['label'] ?? $option_value ) . '</option>';
			}
			$output .= '</select>';
			return $output;
		}

		if ( 'image' === $field['type'] ) {
			$saved      = Profile_Fields::get_value( wp_get_current_user(), $field );
			$removed    = array_key_exists( $field['key'], self::$submitted_values ) && 0 === self::$submitted_values[ $field['key'] ] && $saved;
			$current    = $saved ? wp_get_attachment_image_url( absint( $saved ), 'thumbnail' ) : '';
			$can_upload = 'private' !== $field['visibility'];
			$limit      = min( 2 * MB_IN_BYTES, wp_max_upload_size() );
			$output     = '<div class="assemblypress-profile-image" role="group" aria-label="' . esc_attr( $field['label'] ) . '" data-name="' . esc_attr( $name ) . '" data-label="' . esc_attr( $field['label'] ) . '" data-current="' . esc_url( $current ) . '" data-removed="' . ( $removed ? 'true' : 'false' ) . '" data-upload="' . ( $can_upload ? 'true' : 'false' ) . '" data-limit="' . esc_attr( $limit ) . '">';
			$output    .= $value ? wp_get_attachment_image( absint( $value ), 'thumbnail', false, array( 'alt' => __( 'Current profile image', 'assemblypress' ) ) ) : '';
			if ( $can_upload ) {
				$output .= '<input type="file" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" accept="image/jpeg,image/png,image/webp" aria-describedby="' . esc_attr( $name . '_help' ) . '">';
			}
			if ( $saved ) {
				$output .= '<label><input ' . checked( (bool) $removed, true, false ) . ' type="checkbox" name="' . esc_attr( $name . '_remove' ) . '" value="1"> ' . esc_html__( 'Remove current image when saving', 'assemblypress' ) . '</label>';
			}
			$output .= '</div><span class="assemblypress-edit-profile-form__help">';
			if ( $can_upload ) {
				/* translators: %s: Maximum upload file size. */
				$output .= esc_html( sprintf( __( 'JPEG, PNG, or WebP. Maximum %s and 4096 pixels per side. Uploaded images are public.', 'assemblypress' ), size_format( $limit ) ) );
			} else {
				$output .= esc_html__( 'Image uploads require a public field. Contact a site editor to change this setting.', 'assemblypress' );
			}
			return $output . '</span>';
		}

		$type = 'email' === $field['type'] ? 'email' : ( 'url' === $field['type'] ? 'url' : 'text' );
		return '<input id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( (string) $value ) . '"' . $required . ' />';
	}
}
