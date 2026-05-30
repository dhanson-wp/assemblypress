/* eslint-disable */
( function ( blocks, blockEditor, components, element, i18n, apiFetch ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useEffect = element.useEffect;
	var useState = element.useState;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'assemblypress/profile-field', {
		edit: function ( props ) {
			var fieldKey = ( props.attributes.fieldKey || '' ).trim();
			var fieldState = useProfileFieldState( fieldKey );

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Field settings', 'assemblypress' ) },
						el( TextControl, {
							label: __( 'Field key', 'assemblypress' ),
							value: props.attributes.fieldKey,
							onChange: function ( value ) {
								props.setAttributes( { fieldKey: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'User ID', 'assemblypress' ),
							type: 'number',
							value: props.attributes.userId || '',
							onChange: function ( value ) {
								props.setAttributes( { userId: parseInt( value, 10 ) || 0 } );
							},
						} )
					)
				),
				el(
					'div',
					{
						className: 'assemblypress-editor-preview' + ( ! fieldState.field ? ' has-warning' : '' ),
					},
					el( 'span', { className: 'assemblypress-editor-preview__label' }, __( 'Dynamic block', 'assemblypress' ) ),
					el( 'strong', {}, __( 'Profile Field', 'assemblypress' ) ),
					el( 'p', {}, getFieldStatusText( fieldKey, fieldState ) ),
					fieldKey
						? el(
							'span',
							{ className: 'assemblypress-editor-preview__meta' },
							__( 'Field key:', 'assemblypress' ) + ' ' + fieldKey
						)
						: null,
					fieldState.field
						? el(
							'span',
							{ className: 'assemblypress-editor-preview__meta' },
							__( 'Field label:', 'assemblypress' ) + ' ' + fieldState.field.label
						)
						: null
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	function useProfileFieldState( fieldKey ) {
		var state = useState( {
			field: null,
			loaded: false,
			error: false,
		} );
		var fieldState = state[ 0 ];
		var setFieldState = state[ 1 ];

		useEffect(
			function () {
				var isMounted = true;

				if ( ! fieldKey ) {
					setFieldState( {
						field: null,
						loaded: true,
						error: false,
					} );
					return function () {
						isMounted = false;
					};
				}

				setFieldState( {
					field: null,
					loaded: false,
					error: false,
				} );

				apiFetch( {
					path: '/assemblypress/v1/profile-fields?_fields=key,label',
				} )
					.then( function ( fields ) {
						var matchedField = null;

						if ( Array.isArray( fields ) ) {
							matchedField = fields.find( function ( field ) {
								return field.key === fieldKey;
							} );
						}

						if ( isMounted ) {
							setFieldState( {
								field: matchedField || null,
								loaded: true,
								error: false,
							} );
						}
					} )
					.catch( function () {
						if ( isMounted ) {
							setFieldState( {
								field: null,
								loaded: true,
								error: true,
							} );
						}
					} );

				return function () {
					isMounted = false;
				};
			},
			[ fieldKey ]
		);

		return fieldState;
	}

	function getFieldStatusText( fieldKey, fieldState ) {
		if ( ! fieldKey ) {
			return __( 'No field key set. Add one in block settings.', 'assemblypress' );
		}

		if ( ! fieldState.loaded ) {
			return __( 'Checking the configured profile field.', 'assemblypress' );
		}

		if ( fieldState.field ) {
			return __( 'Renders this AssemblyPress profile field on the frontend.', 'assemblypress' );
		}

		if ( fieldState.error ) {
			return __( 'Could not verify this field key in the editor.', 'assemblypress' );
		}

		return __( 'Unknown field key. This block will render empty until the field exists.', 'assemblypress' );
	}
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.apiFetch );
