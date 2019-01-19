const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { CheckboxControl, PanelBody, RangeControl, SelectControl, ServerSideRender, TextControl } = wp.components;

registerBlockType( 'friends/friend-posts', {
	title: __( 'Friend Posts', 'friends' ),
	icon: 'groups',
	category: 'widgets',
	attributes: {
		author_inline: {
			type: 'boolean',
			default: false,
		},
		author_name: {
			type: 'boolean',
			default: true,
		},
		author_avatar: {
			type: 'boolean',
			default: true,
		},
		show_date: {
			type: 'boolean',
			default: true,
		},
		count: {
			type: 'number',
			default: 5,
		},
		exclude_users: {
			type: 'string',
		},
		only_users: {
			type: 'string',
		},
	},

	edit: function( { attributes, setAttributes } ) {
		return (
			<Fragment>
				<InspectorControls>
					<PanelBody>
						<CheckboxControl
							label={ __( 'Display Author name', 'friends' ) }
							checked={ attributes.author_name }
							onChange={ author_name => setAttributes( { author_name } ) }
							/>
						<CheckboxControl
							label={ __( 'Display Author avatar', 'friends' ) }
							checked={ attributes.author_avatar }
							onChange={ author_avatar => setAttributes( { author_avatar } ) }
							/>
						<CheckboxControl
							label={ __( 'Show Author inline', 'friends' ) }
							checked={ attributes.author_inline }
							onChange={ author_inline => setAttributes( { author_inline } ) }
							/>
						<CheckboxControl
							label={ __( 'Show Date', 'friends' ) }
							checked={ attributes.show_date }
							onChange={ show_date => setAttributes( { show_date } ) }
							/>
						<RangeControl
							label={ __( 'Number of posts', 'friends' ) }
							value={ attributes.count }
							onChange={ count => setAttributes( { count } ) }
							min={ 1 }
							max={ 100 }
					       	/>
						<TextControl
						label={ __( 'Exclude these users', 'friends' ) }
							placeholder={ __( 'space and/or comma separated', 'friends' ) }
							value={ attributes.exclude_users }
							onChange={ exclude_users => setAttributes( { exclude_users } ) }
							/>
						<TextControl
							label={ __( 'Only these users', 'friends' ) }
							placeholder={ __( 'space and/or comma separated', 'friends' ) }
							value={ attributes.only_users }
							onChange={ only_users => setAttributes( { only_users } ) }
							/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block="friends/friend-posts"
					attributes={ attributes }
				/>
			</Fragment>
		);
	},

    save() {
    	return null;
    },
} );
