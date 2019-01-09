const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody, CheckboxControl, ServerSideRender } = wp.components;

registerBlockType( 'friends/friend-posts', {
	title: __( 'Friend Posts', 'friends' ),
	icon: 'groups',
	category: 'widgets',
	attributes: {
		show_author: {
			type: 'boolean',
			default: true,
		},
		show_date: {
			type: 'boolean',
			default: true,
		},
	},

	edit: function( { attributes, setAttributes } ) {
		return (
			<Fragment>
				<InspectorControls>
					<PanelBody>
						<CheckboxControl
						label={ __( 'Show Author', 'friends' ) }
						checked={ attributes.show_author }
						onChange={ show_author => setAttributes( { show_author } ) }
						/>
						<CheckboxControl
						label={ __( 'Show Date', 'friends' ) }
						checked={ attributes.show_date }
						onChange={ show_date => setAttributes( { show_date } ) }
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
