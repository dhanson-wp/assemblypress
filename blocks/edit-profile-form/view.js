/**
 * Enhance schema image fields with WordPress controls and reversible local state.
 * @param {Object} wp WordPress public packages.
 */
( function ( wp ) {
	const {
		createElement: el,
		createRoot,
		useEffect,
		useRef,
		useState,
	} = wp.element;
	const { Button, Flex, FlexItem, FormFileUpload, Notice } = wp.components;
	const { __, sprintf } = wp.i18n;

	function ProfileImage( {
		name,
		label,
		current,
		canUpload,
		limit,
		removed,
	} ) {
		const [ preview, setPreview ] = useState( removed ? '' : current );
		const [ action, setAction ] = useState( removed ? 'remove' : '' );
		const [ error, setError ] = useState( '' );
		const input = useRef( null );
		const uploadButton = useRef( null );
		const objectUrl = useRef( '' );
		const releasePreview = () => {
			if ( objectUrl.current ) {
				URL.revokeObjectURL( objectUrl.current );
				objectUrl.current = '';
			}
		};
		useEffect( () => () => releasePreview(), [] );
		const clearSelection = () => {
			releasePreview();
			if ( input.current ) {
				input.current.value = '';
			}
			setError( '' );
		};
		const selectImage = ( event ) => {
			const file = event.currentTarget.files[ 0 ];
			if ( ! file ) {
				return;
			}
			input.current = event.currentTarget;
			// Keep WordPress's file chooser compatible with the native multipart form.
			input.current.name = name;
			if (
				! [ 'image/jpeg', 'image/png', 'image/webp' ].includes(
					file.type
				) ||
				file.size > limit
			) {
				clearSelection();
				setPreview( current );
				setAction( '' );
				setError(
					__(
						'Choose a JPEG, PNG, or WebP within the size limit shown below.',
						'assemblypress'
					)
				);
				return;
			}
			releasePreview();
			objectUrl.current = URL.createObjectURL( file );
			setPreview( objectUrl.current );
			setAction( 'replace' );
			setError( '' );
		};
		const removeImage = () => {
			clearSelection();
			setPreview( '' );
			setAction( current ? 'remove' : '' );
			uploadButton.current?.focus();
		};
		const undo = () => {
			clearSelection();
			setPreview( current );
			setAction( '' );
			uploadButton.current?.focus();
		};

		return el(
			Flex,
			{ direction: 'column', align: 'flex-start', gap: 3 },
			el(
				FlexItem,
				{},
				preview
					? el( 'img', {
							src: preview,
							alt:
								action === 'replace'
									? __(
											'New profile image preview',
											'assemblypress'
									  )
									: __(
											'Current profile image',
											'assemblypress'
									  ),
							width: 96,
							height: 96,
					  } )
					: el(
							'span',
							{},
							__( 'No profile image', 'assemblypress' )
					  )
			),
			el(
				Flex,
				{ justify: 'flex-start', wrap: true, gap: 2 },
				canUpload &&
					el( FormFileUpload, {
						accept: 'image/jpeg,image/png,image/webp',
						onChange: selectImage,
						render: ( { openFileDialog } ) =>
							el(
								Button,
								{
									id: name,
									'aria-label': preview
										? sprintf(
												/* translators: %s: Image field label. */ __(
													'Replace %s',
													'assemblypress'
												),
												label
										  )
										: sprintf(
												/* translators: %s: Image field label. */ __(
													'Upload %s',
													'assemblypress'
												),
												label
										  ),
									ref: uploadButton,
									variant: 'secondary',
									__next40pxDefaultSize: true,
									type: 'button',
									onClick: openFileDialog,
									'aria-describedby': name + '_help',
								},
								preview
									? __( 'Replace', 'assemblypress' )
									: __( 'Upload image', 'assemblypress' )
							),
					} ),
				preview &&
					el(
						Button,
						{
							variant: 'tertiary',
							isDestructive: true,
							__next40pxDefaultSize: true,
							type: 'button',
							onClick: removeImage,
						},
						__( 'Remove', 'assemblypress' )
					),
				action &&
					el(
						Button,
						{
							variant: 'tertiary',
							__next40pxDefaultSize: true,
							type: 'button',
							onClick: undo,
						},
						__( 'Undo', 'assemblypress' )
					)
			),
			el( 'input', {
				type: 'hidden',
				name: name + '_remove',
				value: action === 'remove' ? '1' : '',
			} ),
			el(
				'div',
				{ role: action ? 'status' : undefined, 'aria-live': 'polite' },
				action &&
					( action === 'remove'
						? __(
								'Image will be removed when you save your profile.',
								'assemblypress'
						  )
						: __(
								'New image selected. Save your profile to apply it.',
								'assemblypress'
						  ) )
			),
			error &&
				el( Notice, { status: 'error', isDismissible: false }, error )
		);
	}

	wp.domReady( () => {
		document
			.querySelectorAll( '.assemblypress-profile-image' )
			.forEach( ( container ) => {
				createRoot( container ).render(
					el( ProfileImage, {
						name: container.dataset.name,
						label: container.dataset.label,
						current: container.dataset.current,
						removed: container.dataset.removed === 'true',
						canUpload: container.dataset.upload === 'true',
						limit: Number( container.dataset.limit ),
					} )
				);
			} );
	} );
} )( window.wp );
