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
	 * Register blocks from metadata.
	 *
	 * @return void
	 */
	public static function register() {
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/member-directory' );
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/member-profile' );
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/profile-field' );
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/edit-profile-form' );
	}

	/**
	 * Render the member directory block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_member_directory( $attributes ) {
		$limit = isset( $attributes['perPage'] ) ? max( 1, min( 100, (int) $attributes['perPage'] ) ) : 12;
		$query = new \WP_User_Query(
			array(
				'number'  => $limit,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => 'all',
			)
		);

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'assemblypress-member-directory' ) );
		$output             = '<div ' . $wrapper_attributes . '><ul class="assemblypress-member-directory__list">';

		foreach ( $query->get_results() as $user ) {
			$profile = Profile_Fields::get_member_profile( $user->ID );
			if ( is_wp_error( $profile ) ) {
				continue;
			}

			$output .= '<li class="assemblypress-member-directory__member">';
			$output .= '<a class="assemblypress-member-directory__link" href="' . esc_url( $profile['profile_url'] ) . '">';
			$output .= get_avatar( $user->ID, 64, '', '', array( 'class' => 'assemblypress-member-directory__avatar' ) );
			$output .= '<span class="assemblypress-member-directory__name">' . esc_html( $profile['display_name'] ) . '</span>';
			$output .= '</a>';
			$output .= '</li>';
		}

		$output .= '</ul></div>';
		return $output;
	}

	/**
	 * Render the member profile block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_member_profile( $attributes ) {
		$user_id = ! empty( $attributes['userId'] ) ? (int) $attributes['userId'] : get_queried_object_id();
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$profile = Profile_Fields::get_member_profile( $user_id );
		if ( is_wp_error( $profile ) ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'assemblypress-member-profile' ) );
		$output             = '<article ' . $wrapper_attributes . '>';
		$output            .= get_avatar( $profile['id'], 96, '', '', array( 'class' => 'assemblypress-member-profile__avatar' ) );
		$output            .= '<h2 class="assemblypress-member-profile__name">' . esc_html( $profile['display_name'] ) . '</h2>';
		$output            .= '<dl class="assemblypress-member-profile__fields">';

		foreach ( $profile['fields'] as $field ) {
			if ( 'display_name' === $field['key'] || Profile_Fields::sanitize_value( $field['value'], $field ) === '' ) {
				continue;
			}
			$output .= self::render_field_definition( $field );
		}

		$output .= '</dl></article>';
		return $output;
	}

	/**
	 * Render one profile field block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_profile_field( $attributes ) {
		$field_key = sanitize_key( $attributes['fieldKey'] ?? '' );
		$user_id   = ! empty( $attributes['userId'] ) ? (int) $attributes['userId'] : get_current_user_id();
		$field     = Profile_Fields::get_field( $field_key );
		$user      = get_userdata( $user_id );

		if ( ! $field || ! $user ) {
			return '';
		}

		$field['value'] = Profile_Fields::get_value( $user, $field );
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-profile-field' ) ) . '>' . self::render_field_definition( $field ) . '</div>';
	}

	/**
	 * Render the edit profile form block.
	 *
	 * @return string
	 */
	public static function render_edit_profile_form() {
		if ( ! is_user_logged_in() ) {
			return '<p ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-edit-profile-form' ) ) . '>' . esc_html__( 'Log in to edit your profile.', 'assemblypress' ) . '</p>';
		}

		$user   = wp_get_current_user();
		$fields = array_filter(
			Profile_Fields::get_fields(),
			static function ( $field ) {
				return ! empty( $field['editable'] );
			}
		);

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $request_method && isset( $_POST['assemblypress_profile_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['assemblypress_profile_nonce'] ) ), 'assemblypress_update_profile' ) ) {
			$values = array();
			foreach ( $fields as $field ) {
				$input_key               = 'assemblypress_' . $field['key'];
				$values[ $field['key'] ] = isset( $_POST[ $input_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $input_key ] ) ) : '';
			}
			Profile_Fields::update_member_profile( $user->ID, $values );
		}

		$output = '<form method="post" ' . get_block_wrapper_attributes( array( 'class' => 'assemblypress-edit-profile-form' ) ) . '>';
		foreach ( $fields as $field ) {
			$value   = Profile_Fields::get_value( $user, $field );
			$name    = 'assemblypress_' . $field['key'];
			$output .= '<p class="assemblypress-edit-profile-form__field">';
			$output .= '<label for="' . esc_attr( $name ) . '">' . esc_html( $field['label'] ) . '</label>';
			$output .= self::render_input( $name, $field, $value );
			$output .= '</p>';
		}
		$output .= wp_nonce_field( 'assemblypress_update_profile', 'assemblypress_profile_nonce', true, false );
		$output .= '<button type="submit">' . esc_html__( 'Save profile', 'assemblypress' ) . '</button>';
		$output .= '</form>';

		return $output;
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
		} elseif ( 'image' === $field['type'] && $value ) {
			$rendered = wp_get_attachment_image( (int) $value, 'thumbnail' );
		} else {
			$rendered = esc_html( (string) $value );
		}

		if ( '' === $rendered ) {
			return '';
		}

		return '<div class="assemblypress-profile-field__item"><dt>' . esc_html( $field['label'] ) . '</dt><dd>' . $rendered . '</dd></div>';
	}

	/**
	 * Render a frontend form input.
	 *
	 * @param string $name  Input name.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 * @return string
	 */
	private static function render_input( $name, $field, $value ) {
		if ( 'textarea' === $field['type'] ) {
			return '<textarea id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
		}

		if ( 'checkbox' === $field['type'] ) {
			return '<input id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="checkbox" value="1" ' . checked( (bool) $value, true, false ) . ' />';
		}

		if ( 'select' === $field['type'] ) {
			$output = '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $field['options'] as $option ) {
				$option_value = $option['value'] ?? '';
				$output      .= '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option['label'] ?? $option_value ) . '</option>';
			}
			$output .= '</select>';
			return $output;
		}

		$type = 'email' === $field['type'] ? 'email' : ( 'url' === $field['type'] ? 'url' : 'text' );
		return '<input id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( (string) $value ) . '" />';
	}
}
