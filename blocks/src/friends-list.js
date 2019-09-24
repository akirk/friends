const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { CheckboxControl, PanelBody, SelectControl, ServerSideRender } = wp.components;

registerBlockType( 'friends/friends-list', {
	title: __( 'Friends list', 'friends' ),
	icon: 'groups',
	category: 'widgets',
	attributes: {
		users_inline: {
			type: 'boolean',
			default: false,
		},
		user_types: {
			type: 'string',
		},
	},

	edit: function( { attributes, setAttributes } ) {
		return (
			<Fragment>
				<InspectorControls>
					<PanelBody>
					<CheckboxControl
						label={ __( 'Display Users inline', 'friends' ) }
						checked={ attributes.users_inline }
						onChange={ users_inline => setAttributes( { users_inline } ) }
						/>
						<SelectControl
							label={ __( 'User Types', 'friends' ) }
							onChange={ user_types => setAttributes( { user_types } ) }
							value={ attributes.user_types }
							options={ [
								{
									label: __( 'Friends', 'friends' ),
									value: 'friends',
								},
								{
									label: __( 'Friend requests', 'friends' ),
									value: 'friend_requests',
								},
								{
									label: __( 'Subscriptions', 'friends' ),
									value: 'subscriptions',
								},
								{
									label: __( 'Friends + Subscriptions', 'friends' ),
									value: 'friends_subscriptions',
								},
							] }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
				block="friends/friends-list"
					attributes={ attributes }
				/>
			</Fragment>
		);
	},

    save() {
    	return null;
    },
} );
