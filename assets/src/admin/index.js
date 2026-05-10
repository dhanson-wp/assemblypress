/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import {
	createRoot,
	render,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { arrowDown, arrowUp, pencil, plus, trash } from '@wordpress/icons';
import {
	DataForm,
	DataViews,
	filterSortAndPaginate,
	useFormValidity,
} from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import './style.css';

/* global assemblyPressAdmin */

const EMPTY_ARRAY = [];

apiFetch.use( apiFetch.createNonceMiddleware( assemblyPressAdmin.nonce ) );

function fieldPath( path = '' ) {
	return `/assemblypress/v1/profile-fields${ path }`;
}

const FIELD_TYPE_OPTIONS = [
	{ value: 'text', label: __( 'Text', 'assemblypress' ) },
	{ value: 'textarea', label: __( 'Textarea', 'assemblypress' ) },
	{ value: 'url', label: __( 'URL', 'assemblypress' ) },
	{ value: 'email', label: __( 'Email', 'assemblypress' ) },
	{ value: 'select', label: __( 'Select', 'assemblypress' ) },
	{ value: 'checkbox', label: __( 'Checkbox', 'assemblypress' ) },
	{ value: 'image', label: __( 'Image', 'assemblypress' ) },
	{ value: 'social_links', label: __( 'Social links', 'assemblypress' ) },
];

const VISIBILITY_OPTIONS = [
	{ value: 'public', label: __( 'Public', 'assemblypress' ) },
	{ value: 'private', label: __( 'Private', 'assemblypress' ) },
];

const emptyField = {
	key: '',
	label: '',
	type: 'text',
	visibility: 'public',
	editable: true,
	required: false,
	description: '',
	order: 0,
	options: [],
};

function isSystemField( field ) {
	return !! field?.is_system;
}

function FieldBadge( { children, variant = 'default' } ) {
	return (
		<span
			className={ `assemblypress-field-badge assemblypress-field-badge--${ variant }` }
		>
			{ children }
		</span>
	);
}

function optionLabel( field, value ) {
	return (
		field.elements.find( ( option ) => option.value === value )?.label ??
		value
	);
}

function parseOptions( optionsText = '' ) {
	return optionsText
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => {
			const [ rawValue, ...labelParts ] = line.split( '|' );
			const value = rawValue.trim();
			const label = labelParts.join( '|' ).trim() || value;

			return { value, label };
		} )
		.filter( ( option ) => option.value );
}

function stringifyOptions( options = [] ) {
	if ( ! Array.isArray( options ) ) {
		return '';
	}

	return options
		.map( ( option ) => {
			if ( option.value === option.label ) {
				return option.value;
			}

			return `${ option.value }|${ option.label }`;
		} )
		.join( '\n' );
}

function prepareFieldForForm( field ) {
	return {
		...emptyField,
		...field,
		optionsText: stringifyOptions( field?.options ),
	};
}

function prepareFieldForSave( field ) {
	const { optionsText, ...fieldData } = field;

	return {
		...fieldData,
		options: 'select' === fieldData.type ? parseOptions( optionsText ) : [],
	};
}

function profileFieldForm( type, isSystem ) {
	const formFields = [
		{
			id: 'basic',
			label: __( 'Basic settings', 'assemblypress' ),
			children: isSystem ? [ 'label' ] : [ 'label', 'key', 'type' ],
			layout: { type: 'card', isOpened: true },
		},
		{
			id: 'display',
			label: __( 'Display', 'assemblypress' ),
			children: [ 'visibility', 'description' ],
			layout: { type: 'card', isOpened: true },
		},
		{
			id: 'rules',
			label: __( 'Rules', 'assemblypress' ),
			children: [ 'editable', 'required', 'order' ],
			layout: { type: 'card', isOpened: true },
		},
	];

	if ( 'select' === type ) {
		formFields.push( {
			id: 'options',
			label: __( 'Options', 'assemblypress' ),
			children: [ 'optionsText' ],
			layout: { type: 'card', isOpened: true },
		} );
	}

	return { fields: formFields };
}

function useProfileFieldDefinitions() {
	return useMemo(
		() => [
			{
				id: 'label',
				type: 'text',
				label: __( 'Label', 'assemblypress' ),
				enableHiding: false,
				enableGlobalSearch: true,
				filterBy: false,
				isValid: { required: true },
			},
			{
				id: 'key',
				type: 'text',
				label: __( 'Key', 'assemblypress' ),
				enableGlobalSearch: true,
				filterBy: false,
				isValid: {
					required: true,
					pattern: '^[a-z0-9_]+$',
				},
				description: __(
					'Lowercase letters, numbers, and underscores only.',
					'assemblypress'
				),
			},
			{
				id: 'type',
				type: 'text',
				label: __( 'Type', 'assemblypress' ),
				elements: FIELD_TYPE_OPTIONS,
				Edit: 'select',
				getValue: ( { item } ) => item.type,
				render: ( { item, field } ) => optionLabel( field, item.type ),
				filterBy: false,
			},
			{
				id: 'status',
				type: 'text',
				label: __( 'Status', 'assemblypress' ),
				getValue: ( { item } ) =>
					isSystemField( item )
						? __( 'System', 'assemblypress' )
						: __( 'Custom', 'assemblypress' ),
				render: ( { item } ) => (
					<FieldBadge
						variant={ isSystemField( item ) ? 'system' : 'custom' }
					>
						{ isSystemField( item )
							? __( 'System', 'assemblypress' )
							: __( 'Custom', 'assemblypress' ) }
					</FieldBadge>
				),
				elements: [
					{
						value: __( 'System', 'assemblypress' ),
						label: __( 'System', 'assemblypress' ),
					},
					{
						value: __( 'Custom', 'assemblypress' ),
						label: __( 'Custom', 'assemblypress' ),
					},
				],
				filterBy: false,
			},
			{
				id: 'visibility',
				type: 'text',
				label: __( 'Visibility', 'assemblypress' ),
				elements: VISIBILITY_OPTIONS,
				Edit: 'select',
				getValue: ( { item } ) => item.visibility,
				render: ( { item, field } ) =>
					optionLabel( field, item.visibility ),
				filterBy: false,
			},
			{
				id: 'description',
				type: 'text',
				label: __( 'Description', 'assemblypress' ),
				enableGlobalSearch: true,
				enableSorting: false,
				Edit: { control: 'textarea', rows: 3 },
				filterBy: false,
			},
			{
				id: 'editable',
				type: 'boolean',
				label: __( 'Member editable', 'assemblypress' ),
				getValue: ( { item } ) => !! item.editable,
				render: ( { item } ) =>
					item.editable
						? __( 'Yes', 'assemblypress' )
						: __( 'No', 'assemblypress' ),
				filterBy: false,
			},
			{
				id: 'required',
				type: 'boolean',
				label: __( 'Required', 'assemblypress' ),
				getValue: ( { item } ) => !! item.required,
				render: ( { item } ) =>
					item.required
						? __( 'Yes', 'assemblypress' )
						: __( 'No', 'assemblypress' ),
				filterBy: false,
			},
			{
				id: 'order',
				type: 'integer',
				label: __( 'Order', 'assemblypress' ),
				filterBy: false,
			},
			{
				id: 'optionsText',
				type: 'text',
				label: __( 'Options', 'assemblypress' ),
				description: __(
					'One option per line. Use value|Label when the stored value should differ from the label.',
					'assemblypress'
				),
				placeholder: 'email|Email\nphone|Phone\nsms|SMS',
				Edit: { control: 'textarea', rows: 6 },
				enableSorting: false,
				filterBy: false,
				isVisible: ( item ) => 'select' === item.type,
			},
		],
		[]
	);
}

function ProfileFieldModal( { field, onClose, onSaved } ) {
	const isNew = ! field?.id;
	const [ data, setData ] = useState( () => prepareFieldForForm( field ) );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const fields = useProfileFieldDefinitions();
	const form = useMemo(
		() => profileFieldForm( data.type, isSystemField( data ) ),
		[ data ]
	);
	const { validity, isValid } = useFormValidity( data, fields, form );

	function onChange( edits ) {
		setData( ( current ) => {
			const next = { ...current, ...edits };
			if (
				Object.prototype.hasOwnProperty.call( edits, 'type' ) &&
				'select' !== edits.type
			) {
				next.optionsText = '';
			}
			if (
				Object.prototype.hasOwnProperty.call( edits, 'label' ) &&
				! current.key
			) {
				next.key = edits.label
					.toLowerCase()
					.replace( /[^a-z0-9]+/g, '_' )
					.replace( /^_|_$/g, '' );
			}
			return next;
		} );
	}

	async function onSave() {
		if ( isSaving || ! isValid ) {
			return;
		}

		setIsSaving( true );
		setError( '' );

		try {
			const saved = await apiFetch( {
				path: isNew ? fieldPath() : fieldPath( `/${ data.id }` ),
				method: isNew ? 'POST' : 'PUT',
				data: prepareFieldForSave( data ),
			} );
			onSaved( saved );
			onClose();
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The profile field could not be saved.',
						'assemblypress'
					)
			);
		} finally {
			setIsSaving( false );
		}
	}

	return (
		<Modal
			className="assemblypress-field-modal"
			title={
				isNew
					? __( 'Create profile field', 'assemblypress' )
					: __( 'Edit profile field', 'assemblypress' )
			}
			onRequestClose={ onClose }
			size="medium"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ isSystemField( data ) && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This system field is connected to WordPress user data. Its label, display settings, and order can be changed, but its key and type are locked.',
						'assemblypress'
					) }
				</Notice>
			) }
			<DataForm
				data={ data }
				fields={ fields }
				form={ form }
				validity={ validity }
				onChange={ onChange }
			/>
			<div className="assemblypress-field-modal__footer">
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					onClick={ onClose }
				>
					{ __( 'Cancel', 'assemblypress' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving || ! isValid }
					accessibleWhenDisabled
					onClick={ onSave }
				>
					{ isNew
						? __( 'Create', 'assemblypress' )
						: __( 'Save', 'assemblypress' ) }
				</Button>
			</div>
		</Modal>
	);
}

function DeleteFieldModal( { fields, onClose, onDeleted } ) {
	const [ isDeleting, setIsDeleting ] = useState( false );
	const [ error, setError ] = useState( '' );
	const count = fields.length;

	async function onDelete() {
		if ( isDeleting ) {
			return;
		}

		setIsDeleting( true );
		setError( '' );

		try {
			await Promise.all(
				fields.map( ( field ) =>
					apiFetch( {
						path: fieldPath( `/${ field.id }` ),
						method: 'DELETE',
					} )
				)
			);
			onDeleted( fields );
			onClose();
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The profile field could not be deleted.',
						'assemblypress'
					)
			);
		} finally {
			setIsDeleting( false );
		}
	}

	return (
		<Modal
			className="assemblypress-field-modal"
			title={ __( 'Delete profile field', 'assemblypress' ) }
			onRequestClose={ onClose }
			size="small"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p>
				{ 1 === count
					? sprintf(
							/* translators: %s: profile field label. */
							__(
								'Delete "%s"? This removes the field definition, not existing member meta.',
								'assemblypress'
							),
							fields[ 0 ].label
					  )
					: sprintf(
							/* translators: %d: number of selected profile fields. */
							__(
								'Delete %d profile fields? This removes the field definitions, not existing member meta.',
								'assemblypress'
							),
							count
					  ) }
			</p>
			<div className="assemblypress-field-modal__footer">
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					onClick={ onClose }
				>
					{ __( 'Cancel', 'assemblypress' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isDestructive
					isBusy={ isDeleting }
					disabled={ isDeleting }
					accessibleWhenDisabled
					onClick={ onDelete }
				>
					{ __( 'Delete', 'assemblypress' ) }
				</Button>
			</div>
		</Modal>
	);
}

function ProfileFieldsScreen() {
	const fields = useProfileFieldDefinitions();
	const [ records, setRecords ] = useState( EMPTY_ARRAY );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ modalField, setModalField ] = useState( null );
	const [ deleteFields, setDeleteFields ] = useState( EMPTY_ARRAY );
	const [ selection, setSelection ] = useState( EMPTY_ARRAY );
	const [ view, setView ] = useState( {
		type: 'table',
		search: '',
		filters: [],
		page: 1,
		perPage: 20,
		sort: { field: 'order', direction: 'asc' },
		fields: [ 'key', 'type', 'status', 'visibility', 'editable' ],
		titleField: 'label',
		layout: {
			density: 'compact',
			styles: {
				label: { minWidth: '220px' },
				key: { minWidth: '160px' },
				order: { width: '90px', align: 'end' },
			},
		},
	} );

	const loadFields = useCallback( async () => {
		setIsLoading( true );
		try {
			setRecords( await apiFetch( { path: fieldPath() } ) );
			setError( '' );
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__( 'Profile fields could not be loaded.', 'assemblypress' )
			);
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadFields();
	}, [ loadFields ] );

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( records, view, fields ),
		[ records, view, fields ]
	);

	const orderedRecords = useMemo(
		() =>
			[ ...records ].sort(
				( a, b ) =>
					( a.order || 0 ) - ( b.order || 0 ) ||
					a.label.localeCompare( b.label )
			),
		[ records ]
	);

	const saveOrderedRecords = useCallback(
		async ( nextRecords ) => {
			const updatedRecords = nextRecords.map( ( record, index ) => ( {
				...record,
				order: ( index + 1 ) * 10,
			} ) );

			setRecords( updatedRecords );

			try {
				await Promise.all(
					updatedRecords.map( ( record ) =>
						apiFetch( {
							path: fieldPath( `/${ record.id }` ),
							method: 'PUT',
							data: record,
						} )
					)
				);
				setError( '' );
			} catch ( apiError ) {
				setError(
					apiError?.message ||
						__(
							'The profile field order could not be saved.',
							'assemblypress'
						)
				);
				loadFields();
			}
		},
		[ loadFields ]
	);

	const moveField = useCallback(
		( field, direction ) => {
			const currentIndex = orderedRecords.findIndex(
				( record ) => record.id === field.id
			);
			const nextIndex = currentIndex + direction;

			if (
				currentIndex < 0 ||
				nextIndex < 0 ||
				nextIndex >= orderedRecords.length
			) {
				return;
			}

			const nextRecords = [ ...orderedRecords ];
			const [ movedRecord ] = nextRecords.splice( currentIndex, 1 );
			nextRecords.splice( nextIndex, 0, movedRecord );
			saveOrderedRecords( nextRecords );
		},
		[ orderedRecords, saveOrderedRecords ]
	);

	const actions = useMemo(
		() => [
			{
				id: 'edit-field',
				label: __( 'Edit', 'assemblypress' ),
				icon: pencil,
				isPrimary: true,
				callback: ( items ) => setModalField( items[ 0 ] ),
			},
			{
				id: 'move-field-up',
				label: __( 'Move up', 'assemblypress' ),
				icon: arrowUp,
				isEligible: ( item ) =>
					orderedRecords.findIndex(
						( record ) => record.id === item.id
					) > 0,
				callback: ( items ) => moveField( items[ 0 ], -1 ),
			},
			{
				id: 'move-field-down',
				label: __( 'Move down', 'assemblypress' ),
				icon: arrowDown,
				isEligible: ( item ) => {
					const index = orderedRecords.findIndex(
						( record ) => record.id === item.id
					);
					return index >= 0 && index < orderedRecords.length - 1;
				},
				callback: ( items ) => moveField( items[ 0 ], 1 ),
			},
			{
				id: 'delete-field',
				label: __( 'Delete', 'assemblypress' ),
				icon: trash,
				isDestructive: true,
				supportsBulk: true,
				isEligible: ( item ) => ! isSystemField( item ),
				callback: ( items ) => {
					const deletableItems = items.filter(
						( item ) => ! isSystemField( item )
					);

					if ( deletableItems.length ) {
						setDeleteFields( deletableItems );
					}
				},
			},
		],
		[ moveField, orderedRecords ]
	);

	function onSaved( saved ) {
		setRecords( ( current ) => {
			const found = current.some( ( item ) => item.id === saved.id );
			if ( found ) {
				return current.map( ( item ) =>
					item.id === saved.id ? saved : item
				);
			}
			return [ ...current, saved ];
		} );
	}

	function onDeleted( deletedFields ) {
		const deletedIds = deletedFields.map( ( field ) => field.id );
		setRecords( ( current ) =>
			current.filter( ( item ) => ! deletedIds.includes( item.id ) )
		);
		setSelection( ( current ) =>
			current.filter( ( id ) => ! deletedIds.includes( id ) )
		);
	}

	return (
		<div className="assemblypress-admin">
			<div className="assemblypress-admin__header">
				<div>
					<h1>{ __( 'Profile Fields', 'assemblypress' ) }</h1>
					<p>
						{ __(
							'Define the member profile fields used by directories, profile pages, and edit forms.',
							'assemblypress'
						) }
					</p>
				</div>
				<Button
					__next40pxDefaultSize
					variant="primary"
					icon={ plus }
					onClick={ () =>
						setModalField( {
							...emptyField,
							order:
								Math.max(
									0,
									...records.map(
										( record ) => record.order || 0
									)
								) + 10,
						} )
					}
				>
					{ __( 'Add field', 'assemblypress' ) }
				</Button>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<DataViews
				data={ data }
				fields={ fields }
				actions={ actions }
				view={ view }
				onChangeView={ setView }
				paginationInfo={ paginationInfo }
				defaultLayouts={ { table: {} } }
				isLoading={ isLoading }
				getItemId={ ( item ) => item.id }
				selection={ selection }
				onChangeSelection={ setSelection }
				empty={
					isLoading ? (
						<div className="assemblypress-admin__loading">
							<Spinner />
							{ __( 'Loading fields…', 'assemblypress' ) }
						</div>
					) : (
						<p>
							{ __(
								'No profile fields found.',
								'assemblypress'
							) }
						</p>
					)
				}
			/>
			{ modalField && (
				<ProfileFieldModal
					field={ modalField }
					onClose={ () => setModalField( null ) }
					onSaved={ onSaved }
				/>
			) }
			{ !! deleteFields.length && (
				<DeleteFieldModal
					fields={ deleteFields }
					onClose={ () => setDeleteFields( EMPTY_ARRAY ) }
					onDeleted={ onDeleted }
				/>
			) }
		</div>
	);
}

domReady( () => {
	const rootElement = document.getElementById( 'assemblypress-admin-root' );

	if ( ! rootElement ) {
		return;
	}

	if ( createRoot ) {
		createRoot( rootElement ).render( <ProfileFieldsScreen /> );
	} else {
		render( <ProfileFieldsScreen />, rootElement );
	}
} );
