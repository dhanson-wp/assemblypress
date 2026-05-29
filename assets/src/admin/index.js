/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	Modal,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
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
import {
	arrowDown,
	arrowUp,
	check,
	group,
	image,
	link,
	menu,
	paragraph,
	pencil,
	plus,
	postAuthor,
	trash,
	typography,
} from '@wordpress/icons';
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

function groupPath( path = '' ) {
	return `/assemblypress/v1/profile-field-groups${ path }`;
}

const FIELD_TYPES = {
	text: {
		label: __( 'Text', 'assemblypress' ),
		icon: typography,
		category: 'text',
	},
	textarea: {
		label: __( 'Textarea', 'assemblypress' ),
		icon: paragraph,
		category: 'text',
	},
	email: {
		label: __( 'Email', 'assemblypress' ),
		icon: postAuthor,
		category: 'text',
	},
	url: {
		label: __( 'URL', 'assemblypress' ),
		icon: link,
		category: 'text',
	},
	select: {
		label: __( 'Select', 'assemblypress' ),
		icon: menu,
		category: 'choice',
	},
	checkbox: {
		label: __( 'Checkbox', 'assemblypress' ),
		icon: check,
		category: 'choice',
	},
	image: {
		label: __( 'Image', 'assemblypress' ),
		icon: image,
		category: 'media',
	},
	social_links: {
		label: __( 'Social links', 'assemblypress' ),
		icon: group,
		category: 'profile',
	},
};

const FIELD_TYPE_CATEGORIES = [
	{ id: 'text', label: __( 'Text', 'assemblypress' ) },
	{ id: 'choice', label: __( 'Choice', 'assemblypress' ) },
	{ id: 'media', label: __( 'Media', 'assemblypress' ) },
	{ id: 'profile', label: __( 'Profile', 'assemblypress' ) },
];

const FIELD_TYPE_OPTIONS = Object.entries( FIELD_TYPES ).map(
	( [ value, config ] ) => ( { value, label: config.label } )
);

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
	group_id: 0,
	options: [],
};

const emptyGroup = {
	key: '',
	label: '',
	description: '',
	order: 0,
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

function GroupCount( { count } ) {
	return (
		<span className="assemblypress-group-count">
			{ sprintf(
				/* translators: %d: field count. */
				__( '%d fields', 'assemblypress' ),
				count
			) }
		</span>
	);
}

function optionLabel( field, value ) {
	return (
		field.elements.find( ( option ) => option.value === value )?.label ??
		value
	);
}

function groupLabel( groups, groupId ) {
	return (
		groups.find( ( groupItem ) => groupItem.id === groupId )?.label ??
		__( 'Ungrouped', 'assemblypress' )
	);
}

function groupOptions( groups ) {
	return groups.map( ( groupItem ) => ( {
		value: String( groupItem.id ),
		label: groupItem.label,
	} ) );
}

function groupedFieldTypes() {
	return FIELD_TYPE_CATEGORIES.map( ( category ) => ( {
		...category,
		types: Object.entries( FIELD_TYPES )
			.filter( ( [ , config ] ) => config.category === category.id )
			.map( ( [ id, config ] ) => ( { id, ...config } ) ),
	} ) ).filter( ( category ) => category.types.length );
}

function makeKey( label ) {
	return label
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_|_$/g, '' );
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
		group_id: field?.group_id ? String( field.group_id ) : '',
		optionsText: stringifyOptions( field?.options ),
	};
}

function prepareFieldForSave( field ) {
	const { optionsText, ...fieldData } = field;

	return {
		...fieldData,
		group_id: parseInt( fieldData.group_id, 10 ) || 0,
		options: 'select' === fieldData.type ? parseOptions( optionsText ) : [],
	};
}

function prepareGroupForForm( groupItem ) {
	return {
		...emptyGroup,
		...groupItem,
	};
}

function FieldTypePicker( { children, onSelect, variant = 'primary' } ) {
	return (
		<Dropdown
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					__next40pxDefaultSize
					variant={ variant }
					icon={ plus }
					onClick={ onToggle }
					aria-expanded={ isOpen }
				>
					{ children }
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<div className="assemblypress-field-type-picker">
					{ groupedFieldTypes().map( ( category ) => (
						<MenuGroup key={ category.id } label={ category.label }>
							{ category.types.map( ( type ) => (
								<MenuItem
									key={ type.id }
									icon={ type.icon }
									iconPosition="left"
									onClick={ () => {
										onSelect( type.id );
										onClose();
									} }
								>
									{ type.label }
								</MenuItem>
							) ) }
						</MenuGroup>
					) ) }
				</div>
			) }
		/>
	);
}

function profileFieldForm( type, isSystem, isNew ) {
	const formFields = [
		{
			id: 'basic',
			label: __( 'Basic settings', 'assemblypress' ),
			children:
				isSystem || isNew ? [ 'label' ] : [ 'label', 'key', 'type' ],
			layout: { type: 'card', isOpened: true },
		},
		{
			id: 'organization',
			label: __( 'Organization', 'assemblypress' ),
			children: [ 'group_id' ],
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

function useProfileFieldDefinitions( groups ) {
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
				render: ( { item } ) => (
					<span className="assemblypress-field-label">
						<span>{ item.label }</span>
						{ isSystemField( item ) && (
							<FieldBadge variant="system">
								{ __( 'System', 'assemblypress' ) }
							</FieldBadge>
						) }
					</span>
				),
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
				id: 'group',
				type: 'text',
				label: __( 'Group', 'assemblypress' ),
				getValue: ( { item } ) => groupLabel( groups, item.group_id ),
				elements: groups.map( ( groupItem ) => ( {
					value: groupItem.label,
					label: groupItem.label,
				} ) ),
				enableHiding: false,
				enableSorting: false,
			},
			{
				id: 'group_id',
				type: 'text',
				label: __( 'Group', 'assemblypress' ),
				elements: groupOptions( groups ),
				Edit: 'select',
				getValue: ( { item } ) => String( item.group_id || '' ),
				render: ( { item } ) => groupLabel( groups, item.group_id ),
				filterBy: false,
				isValid: { required: true },
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
				id: 'sort_order',
				type: 'integer',
				label: __( 'Sort order', 'assemblypress' ),
				filterBy: false,
				enableHiding: false,
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
		[ groups ]
	);
}

function ProfileFieldModal( { field, groups, onClose, onSaved } ) {
	const isNew = ! field?.id;
	const [ data, setData ] = useState( () => prepareFieldForForm( field ) );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const fields = useProfileFieldDefinitions( groups );
	const form = useMemo(
		() => profileFieldForm( data.type, isSystemField( data ), isNew ),
		[ data, isNew ]
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
				next.key = makeKey( edits.label );
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
			{ isNew && (
				<div className="assemblypress-selected-field-type">
					<span>
						{ sprintf(
							/* translators: %s: field type label. */
							__( 'Field type: %s', 'assemblypress' ),
							FIELD_TYPES[ data.type ]?.label || data.type
						) }
					</span>
					<FieldTypePicker
						variant="secondary"
						onSelect={ ( type ) => onChange( { type } ) }
					>
						{ __( 'Change type', 'assemblypress' ) }
					</FieldTypePicker>
				</div>
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

function ProfileGroupModal( { groupItem, onClose, onSaved } ) {
	const isNew = ! groupItem?.id;
	const [ data, setData ] = useState( () =>
		prepareGroupForForm( groupItem )
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	function updateData( edits ) {
		setData( ( current ) => {
			const next = { ...current, ...edits };
			if (
				Object.prototype.hasOwnProperty.call( edits, 'label' ) &&
				! current.key
			) {
				next.key = makeKey( edits.label );
			}
			return next;
		} );
	}

	async function onSave() {
		if ( isSaving || ! data.label || ! data.key ) {
			return;
		}

		setIsSaving( true );
		setError( '' );

		try {
			const saved = await apiFetch( {
				path: isNew ? groupPath() : groupPath( `/${ data.id }` ),
				method: isNew ? 'POST' : 'PUT',
				data: {
					...data,
					order: parseInt( data.order, 10 ) || 0,
				},
			} );
			onSaved( saved );
			onClose();
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The profile field group could not be saved.',
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
					? __( 'Create field group', 'assemblypress' )
					: __( 'Edit field group', 'assemblypress' )
			}
			onRequestClose={ onClose }
			size="small"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Label', 'assemblypress' ) }
				value={ data.label }
				onChange={ ( label ) => updateData( { label } ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Key', 'assemblypress' ) }
				help={ __(
					'Lowercase letters, numbers, and underscores only.',
					'assemblypress'
				) }
				value={ data.key }
				onChange={ ( key ) => updateData( { key: makeKey( key ) } ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Description', 'assemblypress' ) }
				value={ data.description }
				onChange={ ( description ) => updateData( { description } ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Order', 'assemblypress' ) }
				type="number"
				value={ data.order }
				onChange={ ( order ) => updateData( { order } ) }
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
					disabled={ isSaving || ! data.label || ! data.key }
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

function DeleteGroupModal( { groupItem, onClose, onDeleted } ) {
	const [ isDeleting, setIsDeleting ] = useState( false );
	const [ error, setError ] = useState( '' );

	async function onDelete() {
		if ( isDeleting ) {
			return;
		}

		setIsDeleting( true );
		setError( '' );

		try {
			await apiFetch( {
				path: groupPath( `/${ groupItem.id }` ),
				method: 'DELETE',
			} );
			onDeleted( groupItem );
			onClose();
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The profile field group could not be deleted.',
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
			title={ __( 'Delete field group', 'assemblypress' ) }
			onRequestClose={ onClose }
			size="small"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p>
				{ sprintf(
					/* translators: %s: field group label. */
					__(
						'Delete "%s"? Only empty field groups can be deleted.',
						'assemblypress'
					),
					groupItem.label
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
	const [ records, setRecords ] = useState( EMPTY_ARRAY );
	const [ groups, setGroups ] = useState( EMPTY_ARRAY );
	const [ selectedGroupId, setSelectedGroupId ] = useState( 0 );
	const fields = useProfileFieldDefinitions( groups );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ modalField, setModalField ] = useState( null );
	const [ modalGroup, setModalGroup ] = useState( null );
	const [ deleteFields, setDeleteFields ] = useState( EMPTY_ARRAY );
	const [ deleteGroup, setDeleteGroup ] = useState( null );
	const [ selection, setSelection ] = useState( EMPTY_ARRAY );
	const [ view, setView ] = useState( {
		type: 'table',
		search: '',
		filters: [],
		page: 1,
		perPage: 20,
		sort: { field: 'sort_order', direction: 'asc' },
		fields: [ 'key', 'type', 'visibility' ],
		titleField: 'label',
		layout: {
			density: 'compact',
			styles: {
				label: { minWidth: '260px' },
				key: { minWidth: '160px' },
				order: { width: '90px', align: 'end' },
			},
		},
	} );

	const loadData = useCallback( async () => {
		setIsLoading( true );
		try {
			const [ nextGroups, nextRecords ] = await Promise.all( [
				apiFetch( { path: groupPath() } ),
				apiFetch( { path: fieldPath() } ),
			] );
			setGroups( nextGroups );
			setRecords( nextRecords );
			setSelectedGroupId(
				( current ) => current || nextGroups[ 0 ]?.id || 0
			);
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
		loadData();
	}, [ loadData ] );

	useEffect( () => {
		if (
			groups.length &&
			! groups.some( ( groupItem ) => groupItem.id === selectedGroupId )
		) {
			setSelectedGroupId( groups[ 0 ].id );
		}
	}, [ groups, selectedGroupId ] );

	useEffect( () => {
		setSelection( EMPTY_ARRAY );
		setView( ( current ) => ( { ...current, page: 1 } ) );
	}, [ selectedGroupId ] );

	const groupOrder = useMemo(
		() =>
			groups.reduce( ( order, groupItem, index ) => {
				order[ groupItem.id ] = groupItem.order || ( index + 1 ) * 10;
				return order;
			}, {} ),
		[ groups ]
	);

	const recordsWithGroups = useMemo(
		() =>
			records.map( ( record ) => ( {
				...record,
				group: groupLabel( groups, record.group_id ),
				sort_order:
					( groupOrder[ record.group_id ] || 0 ) * 100000 +
					( record.order || 0 ),
			} ) ),
		[ groupOrder, groups, records ]
	);

	const selectedGroup = useMemo(
		() =>
			groups.find( ( groupItem ) => groupItem.id === selectedGroupId ) ||
			groups[ 0 ] ||
			null,
		[ groups, selectedGroupId ]
	);

	const selectedGroupRecords = useMemo(
		() =>
			recordsWithGroups.filter(
				( record ) => record.group_id === selectedGroup?.id
			),
		[ recordsWithGroups, selectedGroup ]
	);

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( selectedGroupRecords, view, fields ),
		[ selectedGroupRecords, view, fields ]
	);

	const orderedRecords = useMemo(
		() =>
			[ ...records ].sort(
				( a, b ) =>
					( groupOrder[ a.group_id ] || 0 ) -
						( groupOrder[ b.group_id ] || 0 ) ||
					( a.order || 0 ) - ( b.order || 0 ) ||
					a.label.localeCompare( b.label )
			),
		[ groupOrder, records ]
	);

	const saveOrderedRecords = useCallback(
		async ( groupId, nextRecords ) => {
			const updatedRecords = nextRecords.map( ( record, index ) => ( {
				...record,
				order: ( index + 1 ) * 10,
			} ) );

			setRecords( ( current ) =>
				current.map(
					( record ) =>
						updatedRecords.find(
							( item ) => item.id === record.id
						) || record
				)
			);

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
						sprintf(
							/* translators: %s: field group label. */
							__(
								'The profile field order in %s could not be saved.',
								'assemblypress'
							),
							groupLabel( groups, groupId )
						)
				);
				loadData();
			}
		},
		[ groups, loadData ]
	);

	const moveField = useCallback(
		( field, direction ) => {
			const groupRecords = orderedRecords.filter(
				( record ) => record.group_id === field.group_id
			);
			const currentIndex = groupRecords.findIndex(
				( record ) => record.id === field.id
			);
			const nextIndex = currentIndex + direction;

			if (
				currentIndex < 0 ||
				nextIndex < 0 ||
				nextIndex >= groupRecords.length
			) {
				return;
			}

			const nextRecords = [ ...groupRecords ];
			const [ movedRecord ] = nextRecords.splice( currentIndex, 1 );
			nextRecords.splice( nextIndex, 0, movedRecord );
			saveOrderedRecords( field.group_id, nextRecords );
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
					orderedRecords.filter(
						( record ) => record.group_id === item.group_id
					)[ 0 ]?.id !== item.id,
				callback: ( items ) => moveField( items[ 0 ], -1 ),
			},
			{
				id: 'move-field-down',
				label: __( 'Move down', 'assemblypress' ),
				icon: arrowDown,
				isEligible: ( item ) => {
					const groupRecords = orderedRecords.filter(
						( record ) => record.group_id === item.group_id
					);
					return (
						groupRecords.length > 1 &&
						groupRecords[ groupRecords.length - 1 ]?.id !== item.id
					);
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

	function nextFieldOrder( groupId ) {
		return (
			Math.max(
				0,
				...records
					.filter( ( record ) => record.group_id === groupId )
					.map( ( record ) => record.order || 0 )
			) + 10
		);
	}

	function onAddField( type ) {
		const groupId = selectedGroup?.id || groups[ 0 ]?.id || 0;
		setModalField( {
			...emptyField,
			type,
			group_id: groupId,
			order: nextFieldOrder( groupId ),
		} );
	}

	function onSavedField( saved ) {
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

	function onDeletedFields( deletedFields ) {
		const deletedIds = deletedFields.map( ( field ) => field.id );
		setRecords( ( current ) =>
			current.filter( ( item ) => ! deletedIds.includes( item.id ) )
		);
		setSelection( ( current ) =>
			current.filter( ( id ) => ! deletedIds.includes( id ) )
		);
	}

	function onSavedGroup( saved ) {
		setGroups( ( current ) => {
			const found = current.some( ( item ) => item.id === saved.id );
			if ( found ) {
				return current
					.map( ( item ) => ( item.id === saved.id ? saved : item ) )
					.sort(
						( a, b ) =>
							( a.order || 0 ) - ( b.order || 0 ) ||
							a.label.localeCompare( b.label )
					);
			}
			return [ ...current, saved ].sort(
				( a, b ) =>
					( a.order || 0 ) - ( b.order || 0 ) ||
					a.label.localeCompare( b.label )
			);
		} );
		setSelectedGroupId( saved.id );
	}

	function onDeletedGroup( deleted ) {
		setGroups( ( current ) => {
			const nextGroups = current.filter(
				( item ) => item.id !== deleted.id
			);
			if ( selectedGroupId === deleted.id ) {
				setSelectedGroupId( nextGroups[ 0 ]?.id || 0 );
			}
			return nextGroups;
		} );
	}

	function fieldCountForGroup( groupId ) {
		return records.filter( ( record ) => record.group_id === groupId )
			.length;
	}

	return (
		<div className="assemblypress-admin">
			<div className="assemblypress-admin__header">
				<div>
					<h1>{ __( 'Profile Fields', 'assemblypress' ) }</h1>
					<p>
						{ __(
							'Define the member profile fields used by directories, profile pages, and the temporary profile-value editor.',
							'assemblypress'
						) }
					</p>
				</div>
				<FieldTypePicker onSelect={ onAddField }>
					{ __( 'Add field', 'assemblypress' ) }
				</FieldTypePicker>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="assemblypress-profile-builder">
				<aside
					className="assemblypress-profile-builder__sidebar"
					aria-label={ __( 'Profile sections', 'assemblypress' ) }
				>
					<div className="assemblypress-profile-builder__sidebar-header">
						<h2>{ __( 'Sections', 'assemblypress' ) }</h2>
					</div>
					<div className="assemblypress-section-list">
						{ groups.map( ( groupItem ) => (
							<button
								key={ groupItem.id }
								type="button"
								className={
									groupItem.id === selectedGroup?.id
										? 'assemblypress-section-list__item is-selected'
										: 'assemblypress-section-list__item'
								}
								aria-current={
									groupItem.id === selectedGroup?.id
										? 'true'
										: undefined
								}
								onClick={ () =>
									setSelectedGroupId( groupItem.id )
								}
							>
								<span className="assemblypress-section-list__label">
									{ groupItem.label }
								</span>
								<GroupCount
									count={ fieldCountForGroup( groupItem.id ) }
								/>
							</button>
						) ) }
					</div>
					<Button
						__next40pxDefaultSize
						className="assemblypress-profile-builder__add-section"
						variant="secondary"
						icon={ plus }
						onClick={ () =>
							setModalGroup( {
								...emptyGroup,
								order:
									Math.max(
										0,
										...groups.map(
											( groupItem ) =>
												groupItem.order || 0
										)
									) + 10,
							} )
						}
					>
						{ __( 'Add section', 'assemblypress' ) }
					</Button>
				</aside>
				<section className="assemblypress-profile-builder__main">
					{ selectedGroup && (
						<div className="assemblypress-section-header">
							<div className="assemblypress-section-header__content">
								<div className="assemblypress-section-header__eyebrow">
									{ __( 'Profile section', 'assemblypress' ) }
								</div>
								<h2>{ selectedGroup.label }</h2>
								{ selectedGroup.description && (
									<p>{ selectedGroup.description }</p>
								) }
								<GroupCount
									count={ fieldCountForGroup(
										selectedGroup.id
									) }
								/>
							</div>
							<div className="assemblypress-section-header__actions">
								<Button
									__next40pxDefaultSize
									variant="secondary"
									icon={ pencil }
									onClick={ () =>
										setModalGroup( selectedGroup )
									}
								>
									{ __( 'Edit section', 'assemblypress' ) }
								</Button>
								<Button
									__next40pxDefaultSize
									variant="tertiary"
									icon={ trash }
									isDestructive
									disabled={
										fieldCountForGroup( selectedGroup.id ) >
										0
									}
									accessibleWhenDisabled
									onClick={ () =>
										setDeleteGroup( selectedGroup )
									}
								>
									{ __( 'Delete', 'assemblypress' ) }
								</Button>
							</div>
						</div>
					) }
					<div className="assemblypress-fields-panel">
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
										{ __(
											'Loading fields…',
											'assemblypress'
										) }
									</div>
								) : (
									<p>
										{ __(
											'No profile fields in this section yet.',
											'assemblypress'
										) }
									</p>
								)
							}
						/>
					</div>
				</section>
			</div>
			{ modalField && (
				<ProfileFieldModal
					field={ modalField }
					groups={ groups }
					onClose={ () => setModalField( null ) }
					onSaved={ onSavedField }
				/>
			) }
			{ modalGroup && (
				<ProfileGroupModal
					groupItem={ modalGroup }
					onClose={ () => setModalGroup( null ) }
					onSaved={ onSavedGroup }
				/>
			) }
			{ !! deleteFields.length && (
				<DeleteFieldModal
					fields={ deleteFields }
					onClose={ () => setDeleteFields( EMPTY_ARRAY ) }
					onDeleted={ onDeletedFields }
				/>
			) }
			{ deleteGroup && (
				<DeleteGroupModal
					groupItem={ deleteGroup }
					onClose={ () => setDeleteGroup( null ) }
					onDeleted={ onDeletedGroup }
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
