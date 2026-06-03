<?php
/**
 * Jetpack-shaped profile form adapter proof.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps Jetpack Forms field block data to AssemblyPress profile contracts.
 *
 * This is intentionally dormant: it registers no blocks, routes, forms, uploads,
 * notifications, or editor UI. It exists to prove the smallest safe adapter
 * boundary before replacing the temporary profile value editor.
 */
final class Profile_Form_Adapter {
	/**
	 * Jetpack field blocks that map to the current AssemblyPress profile field types.
	 *
	 * @var array
	 */
	const SUPPORTED_FIELD_BLOCKS = array(
		'jetpack/field-checkbox' => 'checkbox',
		'jetpack/field-email'    => 'email',
		'jetpack/field-name'     => 'text',
		'jetpack/field-select'   => 'select',
		'jetpack/field-text'     => 'text',
		'jetpack/field-textarea' => 'textarea',
		'jetpack/field-url'      => 'url',
	);

	/**
	 * Jetpack field blocks that should stay out of the profile foundation slice.
	 *
	 * @var array
	 */
	const DEFERRED_FIELD_BLOCKS = array(
		'jetpack/field-file' => 'assemblypress_profile_uploads_deferred',
	);

	/**
	 * HTML form attribute names Jetpack refuses as field IDs.
	 *
	 * @var array
	 */
	const RESERVED_FIELD_KEYS = array(
		'accept',
		'action',
		'autocomplete',
		'enctype',
		'method',
		'name',
		'novalidate',
		'target',
		'type',
		'value',
	);

	/**
	 * Return supported Jetpack field block mappings.
	 *
	 * @return array
	 */
	public static function supported_field_blocks() {
		return self::SUPPORTED_FIELD_BLOCKS;
	}

	/**
	 * Return intentionally deferred Jetpack field blocks.
	 *
	 * @return array
	 */
	public static function deferred_field_blocks() {
		return self::DEFERRED_FIELD_BLOCKS;
	}

	/**
	 * Build profile field definitions from parsed block arrays.
	 *
	 * @param array $blocks   Parsed blocks or a single parsed block.
	 * @param array $defaults Field definition defaults.
	 * @return array|WP_Error
	 */
	public static function profile_fields_from_blocks( $blocks, $defaults = array() ) {
		$fields = array();

		foreach ( self::flatten_blocks( $blocks ) as $block ) {
			$field = self::profile_field_from_block( $block, $defaults );

			if ( is_wp_error( $field ) ) {
				return $field;
			}

			if ( null !== $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Build one profile field definition from a Jetpack field block.
	 *
	 * @param array $block    Parsed block.
	 * @param array $defaults Field definition defaults.
	 * @return array|null|WP_Error
	 */
	public static function profile_field_from_block( $block, $defaults = array() ) {
		if ( ! is_array( $block ) ) {
			return new WP_Error(
				'assemblypress_invalid_form_block',
				__( 'Profile form blocks must be parsed block arrays.', 'assemblypress' ),
				array( 'status' => 400 )
			);
		}

		$block_name = self::block_name( $block );

		if ( isset( self::DEFERRED_FIELD_BLOCKS[ $block_name ] ) ) {
			return new WP_Error(
				self::DEFERRED_FIELD_BLOCKS[ $block_name ],
				__( 'Profile upload fields are deferred until the media policy is designed.', 'assemblypress' ),
				array( 'status' => 400 )
			);
		}

		if ( ! isset( self::SUPPORTED_FIELD_BLOCKS[ $block_name ] ) ) {
			if ( 0 === strpos( $block_name, 'jetpack/field-' ) ) {
				return new WP_Error(
					'assemblypress_unsupported_jetpack_profile_field',
					__( 'This Jetpack field type is not supported by the profile foundation slice.', 'assemblypress' ),
					array( 'status' => 400 )
				);
			}

			return null;
		}

		$field_key = self::field_key_from_block( $block );

		if ( '' === $field_key ) {
			return new WP_Error(
				'assemblypress_missing_profile_field_key',
				__( 'Profile form fields need a Name/ID that maps to an AssemblyPress profile field key.', 'assemblypress' ),
				array( 'status' => 400 )
			);
		}

		if ( in_array( strtolower( $field_key ), self::RESERVED_FIELD_KEYS, true ) ) {
			return new WP_Error(
				'assemblypress_reserved_profile_field_key',
				__( 'Profile form field keys cannot use reserved HTML form attribute names.', 'assemblypress' ),
				array( 'status' => 400 )
			);
		}

		$attrs    = self::block_attrs( $block );
		$existing = self::field_contract_for_key( $field_key );
		$type     = $existing['type'] ?? self::SUPPORTED_FIELD_BLOCKS[ $block_name ];

		$field = array_merge(
			array(
				'visibility'  => 'public',
				'editable'    => true,
				'description' => '',
				'order'       => 0,
				'group_id'    => 0,
			),
			$defaults,
			array(
				'key'         => $field_key,
				'label'       => self::field_label_from_block( $block, $field_key ),
				'type'        => $type,
				'source'      => 'meta',
				'meta_key'    => Profile_Fields::META_PREFIX . $field_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'required'    => ! empty( $attrs['required'] ),
				'description' => sanitize_text_field( $attrs['help'] ?? '' ),
				'options'     => self::field_options_from_block( $block ),
			)
		);

		if ( $existing ) {
			$field = array_merge(
				$field,
				array(
					'type'     => $existing['type'],
					'source'   => $existing['source'],
					'user_key' => $existing['user_key'] ?? '',
					'meta_key' => $existing['meta_key'] ?? $field['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				)
			);
		}

		return Profile_Fields::normalize_field( $field );
	}

	/**
	 * Build an AssemblyPress /me/profile values payload from Jetpack-style POST data.
	 *
	 * @param array $blocks     Parsed blocks or a single parsed block.
	 * @param array $submission Submitted field values keyed by Jetpack field ID.
	 * @return array|WP_Error
	 */
	public static function profile_values_from_submission( $blocks, $submission ) {
		if ( ! is_array( $submission ) ) {
			return new WP_Error(
				'assemblypress_invalid_profile_submission',
				__( 'Profile form submissions must be submitted as an object.', 'assemblypress' ),
				array( 'status' => 400 )
			);
		}

		$values = array();

		foreach ( self::flatten_blocks( $blocks ) as $block ) {
			$field = self::profile_field_from_block( $block );

			if ( is_wp_error( $field ) ) {
				return $field;
			}

			if ( null === $field ) {
				continue;
			}

			$field_key = $field['key'];

			if ( array_key_exists( $field_key, $submission ) ) {
				$values[ $field_key ] = $submission[ $field_key ];
				continue;
			}

			if ( 'checkbox' === $field['type'] ) {
				$values[ $field_key ] = false;
			} elseif ( ! empty( $field['required'] ) ) {
				$values[ $field_key ] = '';
			}
		}

		return $values;
	}

	/**
	 * Return a known AssemblyPress field storage contract for a field key.
	 *
	 * @param string $field_key Field key.
	 * @return array|null
	 */
	private static function field_contract_for_key( $field_key ) {
		$field = Profile_Fields::get_field( $field_key );

		if ( $field ) {
			return $field;
		}

		foreach ( Profile_Fields::default_fields() as $default_field ) {
			if ( ( $default_field['key'] ?? '' ) === $field_key ) {
				return Profile_Fields::normalize_field( $default_field );
			}
		}

		return null;
	}

	/**
	 * Get a parsed block name.
	 *
	 * @param array $block Parsed block.
	 * @return string
	 */
	private static function block_name( $block ) {
		return is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';
	}

	/**
	 * Get parsed block attributes.
	 *
	 * @param array $block Parsed block.
	 * @return array
	 */
	private static function block_attrs( $block ) {
		return is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
	}

	/**
	 * Get parsed inner blocks.
	 *
	 * @param array $block Parsed block.
	 * @return array
	 */
	private static function inner_blocks( $block ) {
		return is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
	}

	/**
	 * Flatten parsed blocks in document order.
	 *
	 * @param array $blocks Parsed blocks or a single parsed block.
	 * @return array
	 */
	private static function flatten_blocks( $blocks ) {
		if ( isset( $blocks['blockName'] ) ) {
			$blocks = array( $blocks );
		}

		if ( ! is_array( $blocks ) ) {
			return array();
		}

		$flattened = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$flattened[] = $block;
			$flattened   = array_merge( $flattened, self::flatten_blocks( self::inner_blocks( $block ) ) );
		}

		return $flattened;
	}

	/**
	 * Get the AssemblyPress profile field key from a parsed Jetpack field block.
	 *
	 * @param array $block Parsed block.
	 * @return string
	 */
	private static function field_key_from_block( $block ) {
		$attrs = self::block_attrs( $block );
		$key   = $attrs['id'] ?? ( $attrs['fieldId'] ?? '' );

		return is_string( $key ) ? sanitize_key( $key ) : '';
	}

	/**
	 * Get the field label from block attributes or nested Jetpack label blocks.
	 *
	 * @param array  $block     Parsed block.
	 * @param string $field_key Field key.
	 * @return string
	 */
	private static function field_label_from_block( $block, $field_key ) {
		$attrs = self::block_attrs( $block );

		if ( ! empty( $attrs['label'] ) && is_string( $attrs['label'] ) ) {
			return sanitize_text_field( $attrs['label'] );
		}

		foreach ( self::inner_blocks( $block ) as $inner_block ) {
			if ( 'jetpack/label' !== self::block_name( $inner_block ) ) {
				continue;
			}

			$label_attrs = self::block_attrs( $inner_block );

			if ( ! empty( $label_attrs['label'] ) && is_string( $label_attrs['label'] ) ) {
				return sanitize_text_field( $label_attrs['label'] );
			}
		}

		return ucwords( str_replace( '_', ' ', $field_key ) );
	}

	/**
	 * Get select field options from Jetpack field block attributes.
	 *
	 * @param array $block Parsed block.
	 * @return array
	 */
	private static function field_options_from_block( $block ) {
		$attrs   = self::block_attrs( $block );
		$options = $attrs['options'] ?? array();

		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $options as $option ) {
			if ( is_array( $option ) ) {
				$value = sanitize_key( $option['value'] ?? ( $option['label'] ?? '' ) );
				$label = sanitize_text_field( $option['label'] ?? $value );
			} else {
				$value = sanitize_key( $option );
				$label = sanitize_text_field( $option );
			}

			if ( '' === $value ) {
				continue;
			}

			$normalized[] = array(
				'value' => $value,
				'label' => $label ? $label : $value,
			);
		}

		return $normalized;
	}
}
