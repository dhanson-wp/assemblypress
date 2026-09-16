import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Flex,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataForm, DataViews } from '@wordpress/dataviews/wp';
/* global assemblyPressAdmin */
const path = ( resource ) => '/assemblypress/v1/' + resource;
const states = {
	invited: __( 'Invited', 'assemblypress' ),
	pending_email: __( 'Awaiting email verification', 'assemblypress' ),
	pending_review: __( 'Awaiting approval', 'assemblypress' ),
	approved: __( 'Account needs attention', 'assemblypress' ),
	active: __( 'Account created', 'assemblypress' ),
	rejected: __( 'Rejected', 'assemblypress' ),
	revoked: __( 'Revoked', 'assemblypress' ),
};

export function SettingsScreen() {
	const [ config, setConfig ] = useState();
	const [ values, setValues ] = useState();
	const [ notice, setNotice ] = useState();
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		apiFetch( { path: path( 'settings' ) } )
			.then( ( data ) => {
				setConfig( data );
				setValues( data.settings );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);
	}, [] );
	const change = ( key, value ) =>
		setValues( {
			...values,
			[ key ]: value,
			...( key === 'profile_audience' && value === 'members'
				? { directory_audience: 'members' }
				: {} ),
		} );
	const save = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setNotice();
		try {
			const data = await apiFetch( {
				path: path( 'settings' ),
				method: 'POST',
				data: values,
			} );
			setValues( data );
			setNotice( {
				status: 'success',
				text: __( 'Settings saved.', 'assemblypress' ),
			} );
		} catch ( error ) {
			setNotice( { status: 'error', text: error.message } );
		} finally {
			setBusy( false );
		}
	};
	return (
		<div className="assemblypress-admin">
			<h1>{ __( 'Settings', 'assemblypress' ) }</h1>
			{ notice && (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.text }
				</Notice>
			) }
			{ ! values ? (
				<Spinner />
			) : (
				<form className="assemblypress-settings-form" onSubmit={ save }>
					<Flex direction="column" align="stretch" gap={ 6 }>
						<section>
							<h2>{ __( 'Joining', 'assemblypress' ) }</h2>
							<SelectControl
								label={ __(
									'How can people join?',
									'assemblypress'
								) }
								value={ values.joining }
								onChange={ ( value ) =>
									change( 'joining', value )
								}
								options={ [
									{
										value: 'closed',
										label: __(
											'Closed: owners manage access',
											'assemblypress'
										),
									},
									{
										value: 'open',
										label: __(
											'Open registration with email verification',
											'assemblypress'
										),
									},
									{
										value: 'invitation',
										label: __(
											'Invitation only',
											'assemblypress'
										),
									},
									{
										value: 'approval',
										label: __(
											'Email verification and owner approval',
											'assemblypress'
										),
									},
								] }
								help={ __(
									'New members receive subscriber accounts after verification and any required approval. Existing accounts are unchanged. Owners can send invitations in every mode. Saving these settings routes core WordPress registration through AssemblyPress. Review any other plugins that create accounts separately.',
									'assemblypress'
								) }
							/>
							<SelectControl
								label={ __(
									'Registration page',
									'assemblypress'
								) }
								value={ values.join_page }
								options={ [
									{
										value: 0,
										label: __(
											'Choose a page containing Member Registration',
											'assemblypress'
										),
									},
									...config.pages.map( ( page ) => ( {
										value: page.id,
										label: page.title,
									} ) ),
								] }
								onChange={ ( value ) =>
									change( 'join_page', Number( value ) )
								}
							/>
						</section>
						<section>
							<h2>{ __( 'Directory', 'assemblypress' ) }</h2>
							<SelectControl
								label={ __(
									'Directory page',
									'assemblypress'
								) }
								value={ values.directory_page }
								options={ [
									{
										value: 0,
										label: __(
											'Choose a page containing Member Directory',
											'assemblypress'
										),
									},
									...config.pages.map( ( page ) => ( {
										value: page.id,
										label: page.title,
									} ) ),
								] }
								onChange={ ( value ) =>
									change( 'directory_page', Number( value ) )
								}
							/>
							<p>
								{ __(
									'Which account roles appear in the directory?',
									'assemblypress'
								) }
							</p>
							{ Object.entries( config.roles ).map(
								( [ role, label ] ) => (
									<CheckboxControl
										key={ role }
										label={ label }
										checked={ values.eligible_roles.includes(
											role
										) }
										onChange={ ( checked ) =>
											change(
												'eligible_roles',
												checked
													? [
															...values.eligible_roles,
															role,
													  ]
													: values.eligible_roles.filter(
															( item ) =>
																item !== role
													  )
											)
										}
									/>
								)
							) }
							<p>
								{ __(
									'Public select fields available as directory filters',
									'assemblypress'
								) }
							</p>
							{ config.fields.length ? (
								config.fields.map( ( field ) => (
									<CheckboxControl
										key={ field.key }
										label={ field.label }
										checked={ values.filter_fields.includes(
											field.key
										) }
										onChange={ ( checked ) =>
											change(
												'filter_fields',
												checked
													? [
															...values.filter_fields,
															field.key,
													  ]
													: values.filter_fields.filter(
															( item ) =>
																item !==
																field.key
													  )
											)
										}
									/>
								) )
							) : (
								<p>
									{ __(
										'Create a public select field in Profile Fields to offer a filter.',
										'assemblypress'
									) }
								</p>
							) }
						</section>
						<section>
							<h2>{ __( 'Privacy', 'assemblypress' ) }</h2>
							{ [
								[
									'directory_audience',
									__(
										'Who can browse the directory?',
										'assemblypress'
									),
								],
								[
									'profile_audience',
									__(
										'Who can view member profiles?',
										'assemblypress'
									),
								],
							].map( ( [ key, label ] ) => (
								<SelectControl
									key={ key }
									label={ label }
									value={ values[ key ] }
									onChange={ ( value ) =>
										change( key, value )
									}
									options={ [
										{
											value: 'public',
											label: __(
												'Anyone',
												'assemblypress'
											),
										},
										{
											value: 'members',
											label: __(
												'Signed-in site members',
												'assemblypress'
											),
										},
									] }
								/>
							) ) }
							<p>
								{ __(
									'Profiles that require sign-in also make the directory require sign-in. Excluding a role from the directory does not hide its direct profile URLs. Profile access applies to every member. Private fields remain visible only to the member and authorized editors. Previously uploaded image URLs remain public.',
									'assemblypress'
								) }
							</p>
						</section>
						<Button
							type="submit"
							variant="primary"
							isBusy={ busy }
							disabled={ busy }
						>
							{ __( 'Save settings', 'assemblypress' ) }
						</Button>
					</Flex>
				</form>
			) }
		</div>
	);
}

function MemberImage( { value, onChange, label } ) {
	const [ url, setUrl ] = useState( '' );
	useEffect( () => {
		let active = true;
		setUrl( '' );
		if ( value ) {
			apiFetch( { path: '/wp/v2/media/' + value } )
				.then( ( media ) => {
					if ( active ) {
						setUrl(
							media.media_details?.sizes?.thumbnail?.source_url ||
								media.source_url
						);
					}
				} )
				.catch( () => {} );
		}
		return () => {
			active = false;
		};
	}, [ value ] );
	const choose = () => {
		const frame = wp.media( {
			title: label,
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () =>
			onChange( frame.state().get( 'selection' ).first().id )
		);
		frame.open();
	};
	return (
		<Flex direction="column" align="flex-start" gap={ 3 }>
			<strong>{ label }</strong>
			{ url && <img src={ url } width="96" alt={ label } /> }
			<Flex>
				{ wp.media && (
					<Button variant="secondary" onClick={ choose }>
						{ value
							? __( 'Replace image', 'assemblypress' )
							: __( 'Choose image', 'assemblypress' ) }
					</Button>
				) }
				{ !! value && (
					<Button variant="tertiary" onClick={ () => onChange( 0 ) }>
						{ __( 'Remove image', 'assemblypress' ) }
					</Button>
				) }
			</Flex>
			<p>
				{ __(
					'Save profile to apply image changes.',
					'assemblypress'
				) }
			</p>
		</Flex>
	);
}

function MemberLinks( { value = [], onChange, label } ) {
	return (
		<Flex direction="column" gap={ 3 }>
			<strong>{ label }</strong>
			{ value.map( ( item, index ) => (
				<Flex key={ index } wrap>
					<TextControl
						label={ __( 'Link label', 'assemblypress' ) }
						value={ item.label }
						onChange={ ( text ) =>
							onChange(
								value.map( ( link, i ) =>
									i === index
										? { ...link, label: text }
										: link
								)
							)
						}
					/>
					<TextControl
						type="url"
						label={ __( 'Link URL', 'assemblypress' ) }
						value={ item.url }
						onChange={ ( text ) =>
							onChange(
								value.map( ( link, i ) =>
									i === index ? { ...link, url: text } : link
								)
							)
						}
					/>
					<Button
						variant="tertiary"
						onClick={ () =>
							onChange( value.filter( ( _, i ) => i !== index ) )
						}
					>
						{ __( 'Remove link', 'assemblypress' ) }
					</Button>
				</Flex>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [ ...value, { label: '', url: '' } ] )
				}
			>
				{ __( 'Add link', 'assemblypress' ) }
			</Button>
		</Flex>
	);
}

function MemberEditor( { member, onClose } ) {
	const [ profile, setProfile ] = useState();
	const [ values, setValues ] = useState( {} );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		apiFetch( { path: path( 'admin/members/' + member.id ) } )
			.then( ( data ) => {
				setProfile( data );
				setValues(
					Object.fromEntries(
						data.groups
							.flatMap( ( group ) => group.fields )
							.map( ( field ) => [ field.key, field.value ] )
					)
				);
			} )
			.catch( ( e ) => setError( e.message ) );
	}, [ member.id ] );
	const fields = profile
		? profile.groups
				.flatMap( ( group ) => group.fields )
				.map( ( field ) => {
					let type = 'text';
					if ( field.type === 'checkbox' ) {
						type = 'boolean';
					}
					if ( field.type === 'image' ) {
						type = 'integer';
					}
					if ( field.type === 'social_links' ) {
						type = 'array';
					}
					let Edit =
						{
							textarea: 'textarea',
							select: 'select',
							checkbox: 'checkbox',
						}[ field.type ] || 'text';
					if ( field.type === 'image' ) {
						Edit = ( props ) => (
							<MemberImage
								label={ field.label }
								value={ props.data[ field.key ] }
								onChange={ ( value ) =>
									props.onChange( { [ field.key ]: value } )
								}
							/>
						);
					}
					if ( field.type === 'social_links' ) {
						Edit = ( props ) => (
							<MemberLinks
								label={ field.label }
								value={ props.data[ field.key ] }
								onChange={ ( value ) =>
									props.onChange( { [ field.key ]: value } )
								}
							/>
						);
					}
					return {
						id: field.key,
						label: field.label,
						type,
						Edit,
						elements:
							field.type === 'select'
								? [
										...( field.required
											? []
											: [
													{
														value: '',
														label: __(
															'None',
															'assemblypress'
														),
													},
											  ] ),
										...field.options.map( ( option ) => ( {
											value: option.value,
											label: option.label,
										} ) ),
								  ]
								: undefined,
						description: field.description,
					};
				} )
		: [];

	const save = async () => {
		setBusy( true );
		setError( '' );
		try {
			await apiFetch( {
				path: path( 'admin/members/' + member.id ),
				method: 'POST',
				data: { fields: values },
			} );
			onClose();
		} catch ( e ) {
			setError( e.message );
		} finally {
			setBusy( false );
		}
	};
	return (
		<Modal
			title={ __( 'Edit member profile', 'assemblypress' ) }
			onRequestClose={ onClose }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ profile ? (
				<>
					<DataForm
						data={ values }
						fields={ fields }
						form={ {
							layout: { type: 'regular' },
							fields: fields.map( ( field ) => field.id ),
						} }
						onChange={ ( edits ) =>
							setValues( { ...values, ...edits } )
						}
					/>
					<p>
						{ __(
							'Changes take effect when you save. Field visibility still controls what other members and visitors can see.',
							'assemblypress'
						) }
					</p>
					<Button
						variant="primary"
						onClick={ save }
						disabled={ busy }
						isBusy={ busy }
					>
						{ __( 'Save profile', 'assemblypress' ) }
					</Button>
				</>
			) : (
				<Spinner />
			) }
		</Modal>
	);
}

export function MembersScreen( { membership = false } ) {
	const [ view, setView ] = useState( {
		type: 'table',
		perPage: 20,
		page: 1,
		search: '',
		fields: membership
			? [ 'email', 'state', 'kind', 'delivery' ]
			: [ 'name', 'email', 'roles', 'eligibility' ],
		filters: membership
			? []
			: [ { field: 'eligibility', operator: 'is', value: 'listed' } ],
	} );
	const [ result, setResult ] = useState( { items: [], total: 0, pages: 0 } );
	const [ loading, setLoading ] = useState( true );
	const [ refresh, setRefresh ] = useState( 0 );
	const [ notice, setNotice ] = useState();
	const [ selected, setSelected ] = useState();
	const [ inviting, setInviting ] = useState( false );
	const [ email, setEmail ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		let active = true;
		setLoading( true );
		const query = new URLSearchParams( {
			page: view.page,
			per_page: view.perPage,
			search: view.search || '',
		} );
		view.filters?.forEach( ( filter ) =>
			query.set( filter.field, filter.value )
		);
		apiFetch( {
			path: path(
				( membership ? 'admin/membership?' : 'admin/members?' ) + query
			),
		} )
			.then( ( data ) => {
				if ( active ) {
					setResult( data );
				}
			} )
			.catch( ( e ) => {
				if ( active ) {
					setNotice( { status: 'error', text: e.message } );
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ membership, view, refresh ] );
	const mutate = async ( resource, data ) => {
		setBusy( true );
		setNotice();
		try {
			await apiFetch( { path: path( resource ), method: 'POST', data } );
			setRefresh( refresh + 1 );
			setInviting( false );
			setEmail( '' );
			setNotice( {
				status: 'success',
				text: __( 'Membership updated.', 'assemblypress' ),
			} );
		} catch ( e ) {
			setNotice( { status: 'error', text: e.message } );
		} finally {
			setBusy( false );
			setRefresh( ( current ) => current + 1 );
		}
	};
	const fields = (
		membership
			? [
					[ 'email', __( 'Email', 'assemblypress' ) ],
					[ 'state', __( 'Status', 'assemblypress' ) ],
					[ 'kind', __( 'Source', 'assemblypress' ) ],
					[ 'delivery', __( 'Email delivery', 'assemblypress' ) ],
			  ]
			: [
					[ 'name', __( 'Name', 'assemblypress' ) ],
					[ 'email', __( 'Email', 'assemblypress' ) ],
					[ 'roles', __( 'Roles', 'assemblypress' ) ],
					[ 'eligibility', __( 'Directory', 'assemblypress' ) ],
			  ]
	).map( ( [ id, label ] ) => ( {
		id,
		label,
		enableSorting: false,
		enableGlobalSearch: false,
	} ) );
	if ( membership ) {
		Object.assign( fields[ 1 ], {
			elements: Object.entries( states ).map( ( [ value, label ] ) => ( {
				value,
				label,
			} ) ),
			filterBy: { operators: [ 'is' ] },
			render: ( { item } ) =>
				( states[ item.state ] || item.state ) +
				( item.expired ? __( '(link expired)', 'assemblypress' ) : '' ),
		} );
		fields[ 3 ].render = ( { item } ) =>
			item.mail_sent
				? __( 'Sent to mail service', 'assemblypress' )
				: __( 'Sending failed', 'assemblypress' );
	} else {
		Object.assign( fields[ 3 ], {
			elements: [
				{ value: 'listed', label: __( 'Listed', 'assemblypress' ) },
				{
					value: 'excluded',
					label: __( 'Excluded by role', 'assemblypress' ),
				},
			],
			filterBy: { operators: [ 'is' ] },
			render: ( { item } ) =>
				item.eligibility === 'listed'
					? __( 'Listed', 'assemblypress' )
					: __( 'Excluded by role', 'assemblypress' ),
		} );
	}
	const actions = membership
		? [
				[
					'approve',
					__( 'Approve', 'assemblypress' ),
					[ 'pending_review', 'approved' ],
				],
				[
					'reject',
					__( 'Reject', 'assemblypress' ),
					[ 'pending_review', 'approved' ],
				],
				[
					'resend',
					__( 'Send new link', 'assemblypress' ),
					[ 'invited', 'pending_email' ],
				],
				[
					'revoke',
					__( 'Revoke', 'assemblypress' ),
					[ 'invited', 'pending_email' ],
				],
		  ].map( ( [ id, label, allowed ] ) => ( {
				id,
				label,
				isEligible: ( item ) =>
					! busy && allowed.includes( item.state ),
				callback: ( items ) =>
					mutate( 'admin/membership/' + items[ 0 ].id, {
						action: id,
					} ),
		  } ) )
		: [
				{
					id: 'edit',
					label: __( 'Edit profile', 'assemblypress' ),
					isEligible: ( item ) => item.can_edit,
					callback: ( items ) => setSelected( items[ 0 ] ),
				},
				{
					id: 'view',
					label: __( 'View profile', 'assemblypress' ),
					callback: ( items ) => {
						window.location.href = items[ 0 ].profile_url;
					},
				},
				{
					id: 'account',
					label: __( 'WordPress account', 'assemblypress' ),
					isEligible: ( item ) => item.can_edit,
					callback: ( items ) => {
						window.location.href = items[ 0 ].edit_url;
					},
				},
		  ];
	return (
		<div className="assemblypress-admin">
			<Flex>
				<h1>
					{ membership
						? __( 'Invitations and requests', 'assemblypress' )
						: __( 'Members', 'assemblypress' ) }
				</h1>
				{ membership && assemblyPressAdmin.canInvite && (
					<Button
						variant="primary"
						onClick={ () => setInviting( true ) }
					>
						{ __( 'Invite member', 'assemblypress' ) }
					</Button>
				) }
			</Flex>
			<p>
				{ membership
					? __(
							'Verify email before approving requests. Invitations grant membership when accepted. New members receive a password setup email.',
							'assemblypress'
					  )
					: __(
							'Manage member profiles and see which accounts appear in your directory. Remove the Directory filter to see all site accounts.',
							'assemblypress'
					  ) }
			</p>
			{ notice && (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.text }
				</Notice>
			) }
			<DataViews
				data={ result.items }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				paginationInfo={ {
					totalItems: result.total,
					totalPages: result.pages,
				} }
				defaultLayouts={ { table: {} } }
				isLoading={ loading }
				getItemId={ ( item ) => String( item.id ) }
				empty={
					<p>{ __( 'No matching records.', 'assemblypress' ) }</p>
				}
			/>
			{ selected && (
				<MemberEditor
					member={ selected }
					onClose={ () => {
						setSelected();
						setRefresh( refresh + 1 );
					} }
				/>
			) }
			{ inviting && (
				<Modal
					title={ __( 'Invite member', 'assemblypress' ) }
					onRequestClose={ () => setInviting( false ) }
				>
					<TextControl
						type="email"
						label={ __( 'Email address', 'assemblypress' ) }
						value={ email }
						onChange={ setEmail }
					/>
					<p>
						{ __(
							'The invitation expires in seven days. Acceptance verifies this email and creates a subscriber account.',
							'assemblypress'
						) }
					</p>
					<Button
						variant="primary"
						disabled={ busy || ! email }
						isBusy={ busy }
						onClick={ () =>
							mutate( 'admin/invitations', { email } )
						}
					>
						{ __( 'Send invitation', 'assemblypress' ) }
					</Button>
					{ notice?.status === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ notice.text }
						</Notice>
					) }
				</Modal>
			) }
		</div>
	);
}

export function DesignScreen() {
	return (
		<div className="assemblypress-admin">
			<h1>{ __( 'Design', 'assemblypress' ) }</h1>
			<p>
				{ __(
					'Compose member experiences with AssemblyPress blocks and core blocks. Save your layouts as patterns. Shared profile templates apply to every member, and your theme controls their appearance.',
					'assemblypress'
				) }
			</p>
			<Flex direction="column" align="flex-start" gap={ 4 }>
				<Button
					variant="secondary"
					href={ assemblyPressAdmin.directoryEdit }
				>
					{ __( 'Edit directory page', 'assemblypress' ) }
				</Button>
				<Button
					variant="secondary"
					href={ assemblyPressAdmin.profileTemplateUrl }
				>
					{ __( 'Edit member profile template', 'assemblypress' ) }
				</Button>
				<Button
					variant="secondary"
					href={ assemblyPressAdmin.editTemplateUrl }
				>
					{ __(
						'Edit profile maintenance template',
						'assemblypress'
					) }
				</Button>
				<Button
					variant="secondary"
					href={
						assemblyPressAdmin.adminUrl +
						'site-editor.php?path=%2Fpatterns'
					}
				>
					{ __( 'Manage patterns', 'assemblypress' ) }
				</Button>
				<Button variant="link" href={ assemblyPressAdmin.directoryUrl }>
					{ __( 'View directory', 'assemblypress' ) }
				</Button>
			</Flex>
			<p>
				{ __(
					'In the block inserter, choose AssemblyPress patterns for a directory, member profile, or registration page. Template editing requires a block theme.',
					'assemblypress'
				) }
			</p>
		</div>
	);
}
