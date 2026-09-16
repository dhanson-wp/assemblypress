<?php
/**
 * Composable directory parts with shared query and member context.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Directory parts and starter patterns. */
final class Directory_Blocks {
	/** Register blocks and patterns.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( array( 'member-search', 'member-results', 'member-count', 'member-pagination', 'member-empty', 'member-navigation' ) as $name ) {
			register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/' . $name, array( 'render_callback' => array( self::class, 'render' ) ) );
		}
		register_block_pattern_category( 'assemblypress', array( 'label' => __( 'AssemblyPress', 'assemblypress' ) ) );
		$card = '<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} --><div class="wp-block-group"><!-- wp:assemblypress/profile-field {"fieldKey":"avatar","showLabel":false,"linkToProfile":true} /--><!-- wp:group --><div class="wp-block-group"><!-- wp:assemblypress/profile-field {"fieldKey":"display_name","showLabel":false,"linkToProfile":true,"headingLevel":2} /--><!-- wp:assemblypress/profile-field {"fieldKey":"location","showLabel":false} /--><!-- wp:assemblypress/profile-field {"fieldKey":"bio","showLabel":false} /--></div><!-- /wp:group --></div><!-- /wp:group -->';
		register_block_pattern(
			'assemblypress/member-directory',
			array(
				'title'      => __( 'Member directory', 'assemblypress' ),
				'categories' => array( 'assemblypress' ),
				'content'    => '<!-- wp:assemblypress/member-directory --><!-- wp:assemblypress/member-search /--><!-- wp:assemblypress/member-count /--><!-- wp:assemblypress/member-results -->' . $card . '<!-- /wp:assemblypress/member-results --><!-- wp:assemblypress/member-empty --><!-- wp:paragraph --><p>' . esc_html__( 'Try another name or clear the filters.', 'assemblypress' ) . '</p><!-- /wp:paragraph --><!-- /wp:assemblypress/member-empty --><!-- wp:assemblypress/member-pagination /--><!-- /wp:assemblypress/member-directory -->',
			)
		);
		register_block_pattern(
			'assemblypress/member-profile',
			array(
				'title'      => __( 'Member profile', 'assemblypress' ),
				'categories' => array( 'assemblypress' ),
				'content'    => '<!-- wp:assemblypress/member-navigation /--><!-- wp:assemblypress/profile-field {"fieldKey":"avatar","showLabel":false} /--><!-- wp:assemblypress/profile-field {"fieldKey":"display_name","showLabel":false,"headingLevel":1} /--><!-- wp:assemblypress/profile-field {"fieldKey":"bio","showLabel":false} /--><!-- wp:assemblypress/profile-field {"fieldKey":"location"} /-->',
			)
		);
		register_block_pattern(
			'assemblypress/member-registration',
			array(
				'title'      => __( 'Join the directory', 'assemblypress' ),
				'categories' => array( 'assemblypress' ),
				'content'    => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Join the directory', 'assemblypress' ) . '</h2><!-- /wp:heading --><!-- wp:assemblypress/registration /-->',
			)
		);
	}

	/** Shared directory query.
	 *
	 * @param array $attributes Directory attributes.
	 * @return \WP_User_Query
	 */
	public static function query( $attributes ) {
		$roles = Settings::get()['eligible_roles'];
		$args  = array(
			'number'         => max( 1, min( 100, (int) ( $attributes['perPage'] ?? 12 ) ) ),
			'paged'          => self::page(),
			'search'         => self::search() ? '*' . self::search() . '*' : '',
			'search_columns' => array( 'display_name' ),
			'orderby'        => array(
				'display_name' => 'ASC',
				'ID'           => 'ASC',
			),
			'role__in'       => empty( $attributes['includeAllAccounts'] ) ? $roles : array(),
		);
		if ( empty( $attributes['includeAllAccounts'] ) && ! $roles ) {
			$args['include'] = array( 0 );
		}
		foreach ( Settings::filter_fields() as $field ) {
			if ( ! in_array( $field['key'], Settings::get()['filter_fields'], true ) ) {
				continue;
			}
			$value = self::filter_value( $field['key'] );
			if ( '' !== $value ) {
				$args['meta_query'][] = array(
					'key'   => $field['meta_key'],
					'value' => $value,
				);
			}
		}
		return new \WP_User_Query( $args );
	}

	/** Read-only search term.
	 *
	 * @return string
	 */
	public static function search() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only search.
		return isset( $_GET['member_search'] ) && is_string( $_GET['member_search'] ) ? sanitize_text_field( wp_unslash( $_GET['member_search'] ) ) : '';
	}

	/** Read-only page.
	 *
	 * @return int
	 */
	public static function page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public pagination.
		return isset( $_GET['member_page'] ) ? max( 1, absint( $_GET['member_page'] ) ) : 1;
	}

	/** Read-only filter.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function filter_value( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public field filter.
		return isset( $_GET[ 'member_filter_' . $key ] ) && is_string( $_GET[ 'member_filter_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'member_filter_' . $key ] ) ) : '';
	}

	/** Profile URL retaining a local directory return destination.
	 *
	 * @param int $id Member ID.
	 * @return string
	 */
	public static function profile_url( $id ) {
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$return = wp_validate_redirect( $uri, Settings::directory_url() );
		return add_query_arg( 'ap_return', $return, Member_Profiles::get_url( $id ) );
	}

	/** Render an independently placeable directory part.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content Inner content.
	 * @param \WP_Block $block Block instance.
	 * @return string
	 */
	public static function render( $attributes, $content, $block ) {
		if ( 'assemblypress/member-navigation' === $block->name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only local return URL.
			$return = isset( $_GET['ap_return'] ) && is_string( $_GET['ap_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['ap_return'] ) ), Settings::directory_url() ) : Settings::directory_url();
			$output = '<a href="' . esc_url( $return ) . '">' . esc_html__( 'Back to members', 'assemblypress' ) . '</a>';
			$id     = Member_Profiles::resolve_member_id( array(), $block );
			if ( $id && get_current_user_id() === $id && ( ! isset( $attributes['showEdit'] ) || $attributes['showEdit'] ) ) {
				$output .= ' <a href="' . esc_url( Member_Profiles::get_url( $id, true ) ) . '">' . esc_html__( 'Edit my profile', 'assemblypress' ) . '</a>';
			}
			return '<nav ' . get_block_wrapper_attributes( array( 'aria-label' => __( 'Member navigation', 'assemblypress' ) ) ) . '>' . $output . '</nav>';
		}
		if ( ! Settings::can_browse() ) {
			return '';
		}
		$args   = array(
			'perPage'            => $block->context['assemblypress/perPage'] ?? 12,
			'includeAllAccounts' => $block->context['assemblypress/includeAllAccounts'] ?? false,
		);
		$query  = self::query( $args );
		$total  = (int) $query->get_total();
		$output = '';
		switch ( $block->name ) {
			case 'assemblypress/member-search':
				$id     = wp_unique_id( 'member-search-' );
				$output = '<form method="get"><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Search members', 'assemblypress' ) . '</label> <input type="search" id="' . esc_attr( $id ) . '" name="member_search" value="' . esc_attr( self::search() ) . '">';
				if ( ! get_option( 'permalink_structure' ) && get_queried_object_id() ) {
					$output .= '<input type="hidden" name="page_id" value="' . esc_attr( get_queried_object_id() ) . '">';
				}
				$remove = array( 'member_search', 'member_page' );
				foreach ( Settings::filter_fields() as $field ) {
					$key = $field['key'];
					if ( ! in_array( $key, Settings::get()['filter_fields'], true ) ) {
						continue;
					}
					$remove[]  = 'member_filter_' . $key;
					$filter_id = wp_unique_id( 'member-filter-' );
					$output   .= '<label for="' . esc_attr( $filter_id ) . '">' . esc_html( $field['label'] ) . '</label><select id="' . esc_attr( $filter_id ) . '" name="member_filter_' . esc_attr( $key ) . '"><option value="">' . esc_html__( 'All', 'assemblypress' ) . '</option>';
					foreach ( $field['options'] as $option ) {
						$output .= '<option value="' . esc_attr( $option['value'] ) . '" ' . selected( self::filter_value( $key ), $option['value'], false ) . '>' . esc_html( $option['label'] ) . '</option>';
					}
					$output .= '</select>';
				}
				$output .= '<button type="submit">' . esc_html__( 'Search', 'assemblypress' ) . '</button> <a href="' . esc_url( remove_query_arg( $remove ) ) . '">' . esc_html__( 'Clear search and filters', 'assemblypress' ) . '</a></form>';
				break;
			case 'assemblypress/member-count':
				/* translators: %d: Matching member count. */
				$output = '<p role="status">' . esc_html( sprintf( _n( '%d member found', '%d members found', $total, 'assemblypress' ), $total ) ) . '</p>';
				break;
			case 'assemblypress/member-results':
				$output = '<ul style="--assemblypress-columns:' . esc_attr( max( 1, min( 6, absint( $attributes['columns'] ?? 1 ) ) ) ) . '">';
				foreach ( $query->get_results() as $user ) {
					$output .= '<li>';
					foreach ( $block->parsed_block['innerBlocks'] as $inner ) {
						$child   = new \WP_Block(
							$inner,
							array(
								'assemblypress/memberId' => $user->ID,
								'assemblypress/inDirectory' => true,
							)
						);
						$output .= $child->render();
					}
					$output .= '</li>';
				}
				$output .= '</ul>';
				break;
			case 'assemblypress/member-empty':
				if ( ! $query->get_results() ) {
					$filtered = self::search() || self::page() > 1;
					foreach ( Settings::get()['filter_fields'] as $key ) {
						$filtered = $filtered || self::filter_value( $key );
					}
					$output = '<p>' . esc_html( $filtered ? __( 'No members match this search or page.', 'assemblypress' ) : __( 'No members are listed yet.', 'assemblypress' ) ) . '</p>' . $content;
				}
				break;
			case 'assemblypress/member-pagination':
				$output = paginate_links(
					array(
						'base'    => add_query_arg( 'member_page', '%#%' ),
						'format'  => '',
						'current' => self::page(),
						'total'   => (int) ceil( $total / $args['perPage'] ),
					)
				);
				$output = $output ? '<nav aria-label="' . esc_attr__( 'Member directory pages', 'assemblypress' ) . '">' . $output . '</nav>' : '';
				break;
		}
		return $output ? '<div ' . get_block_wrapper_attributes() . '>' . $output . '</div>' : '';
	}
}
