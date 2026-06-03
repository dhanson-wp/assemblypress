/* eslint-disable */
( function ( blocks, blockEditor, components, element, i18n, apiFetch ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useEffect = element.useEffect;
	var useState = element.useState;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'assemblypress/profile-field', {
		edit: function ( props ) {
			var fieldKey = ( props.attributes.fieldKey || '' ).trim();
			var fieldState = useProfileFieldsState( fieldKey );
			var memberSource = props.attributes.memberSource || 'current';
			var usesSelectedMember =
				'selected' === memberSource || !! props.attributes.userId;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Field settings', 'assemblypress' ) },
						fieldState.error
							? el( TextControl, {
									label: __( 'Field key', 'assemblypress' ),
									help: __(
										'Field options could not be loaded, so enter the key manually.',
										'assemblypress'
									),
									value: props.attributes.fieldKey,
									onChange: function ( value ) {
										props.setAttributes( {
											fieldKey: value,
										} );
									},
							  } )
							: el( SelectControl, {
									label: __(
										'Profile field',
										'assemblypress'
									),
									value: fieldKey,
									options: getFieldOptions(
										fieldKey,
										fieldState
									),
									onChange: function ( value ) {
										props.setAttributes( {
											fieldKey: value,
										} );
									},
							  } ),
						el( SelectControl, {
							label: __( 'Member to display', 'assemblypress' ),
							value: usesSelectedMember ? 'selected' : 'current',
							options: [
								{
									label: __(
										'Current member context',
										'assemblypress'
									),
									value: 'current',
								},
								{
									label: __(
										'Selected user ID',
										'assemblypress'
									),
									value: 'selected',
								},
							],
							onChange: function ( value ) {
								props.setAttributes( {
									memberSource: value,
									userId:
										'current' === value
											? 0
											: props.attributes.userId,
								} );
							},
						} ),
						el( TextControl, {
							label: __( 'User ID', 'assemblypress' ),
							type: 'number',
							help: usesSelectedMember
								? __(
										'Enter a WordPress user ID. If the user does not exist, the block renders empty.',
										'assemblypress'
								  )
								: __(
										'Switch to selected user ID to pin this block to one member.',
										'assemblypress'
								  ),
							disabled: ! usesSelectedMember,
							value: props.attributes.userId || '',
							onChange: function ( value ) {
								props.setAttributes( {
									userId: parseInt( value, 10 ) || 0,
								} );
							},
						} )
					)
				),
				el(
					'div',
					{
						className:
							'assemblypress-editor-preview' +
							( ! fieldState.field ? ' has-warning' : '' ),
					},
					el(
						'span',
						{ className: 'assemblypress-editor-preview__label' },
						__( 'Dynamic block', 'assemblypress' )
					),
					el( 'strong', {}, __( 'Profile Field', 'assemblypress' ) ),
					el( 'p', {}, getFieldStatusText( fieldKey, fieldState ) ),
					fieldKey
						? el(
								'span',
								{
									className:
										'assemblypress-editor-preview__meta',
								},
								__( 'Field key:', 'assemblypress' ) +
									' ' +
									fieldKey
						  )
						: null,
					fieldState.field
						? el(
								'span',
								{
									className:
										'assemblypress-editor-preview__meta',
								},
								__( 'Field label:', 'assemblypress' ) +
									' ' +
									fieldState.field.label
						  )
						: null,
					el(
						'span',
						{ className: 'assemblypress-editor-preview__meta' },
						usesSelectedMember
							? props.attributes.userId
								? __( 'Specific user ID:', 'assemblypress' ) +
								  ' ' +
								  props.attributes.userId
								: __(
										'Selected user ID is required.',
										'assemblypress'
								  )
							: __(
									'Uses current member context, then the logged-in member.',
									'assemblypress'
							  )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	function useProfileFieldsState( fieldKey ) {
		var state = useState( {
			field: null,
			fields: [],
			loaded: false,
			error: false,
		} );
		var fieldState = state[ 0 ];
		var setFieldState = state[ 1 ];

		useEffect(
			function () {
				var isMounted = true;

				setFieldState( {
					field: null,
					fields: [],
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
						} else {
							fields = [];
						}

						if ( isMounted ) {
							setFieldState( {
								field: matchedField || null,
								fields: fields,
								loaded: true,
								error: false,
							} );
						}
					} )
					.catch( function () {
						if ( isMounted ) {
							setFieldState( {
								field: null,
								fields: [],
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

	function getFieldOptions( fieldKey, fieldState ) {
		var options = [
			{
				label: fieldState.loaded
					? __( 'Select a profile field', 'assemblypress' )
					: __( 'Loading profile fields', 'assemblypress' ),
				value: '',
			},
		];
		var hasCurrentField = ! fieldKey;

		fieldState.fields.forEach( function ( field ) {
			options.push( {
				label: field.label + ' (' + field.key + ')',
				value: field.key,
			} );

			if ( field.key === fieldKey ) {
				hasCurrentField = true;
			}
		} );

		if ( fieldKey && ! hasCurrentField ) {
			options.push( {
				label:
					__( 'Unknown field key:', 'assemblypress' ) +
					' ' +
					fieldKey,
				value: fieldKey,
			} );
		}

		return options;
	}

	function getFieldStatusText( fieldKey, fieldState ) {
		if ( ! fieldKey ) {
			return __(
				'No field key set. Add one in block settings.',
				'assemblypress'
			);
		}

		if ( ! fieldState.loaded ) {
			return __(
				'Checking the configured profile field.',
				'assemblypress'
			);
		}

		if ( fieldState.field ) {
			return __(
				'Renders this AssemblyPress profile field on the frontend.',
				'assemblypress'
			);
		}

		if ( fieldState.error ) {
			return __(
				'Could not verify this field key in the editor.',
				'assemblypress'
			);
		}

		return __(
			'Unknown field key. This block will render empty until the field exists.',
			'assemblypress'
		);
	}
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n,
	window.wp.apiFetch
);
