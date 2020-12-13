const { __ } = wp.i18n;
const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody, SelectControl } = wp.components;

const friendsBlockVisibilityAddAttribute = settings => {
	settings.attributes = {
		...settings.attributes,
		friendsVisibility: {
			type: 'string',
			default: '',
		},
	};
	return settings;
};

const friendsBlockVisibilitySave = 	(element, block, attributes) => {
	if (attributes.friendsVisibility) {
		return wp.element.cloneElement(
			element,
			{},
			wp.element.cloneElement(
				element.props.children,
				{rel: attributes.friendsVisibility}
			)
		);
	}
	return element;
};


const friendsBlockVisibility = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { attributes: {
			friendsVisibility,
		}, setAttributes } = props;

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody className="friends-block-visibility" title={ __( 'Friends Visibility', 'friends' ) }>
					<SelectControl
							label={ __( 'Block visibility', 'friends' ) }
							onChange={ friendsVisibility => {
								if ( friendsVisibility ) {
									setAttributes( { friendsVisibility } )
								} else {
									setAttributes( { friendsVisibility: null } )
								}
							} }
							value={ friendsVisibility }
							options={ [
								{
									label: __( 'For everyone', 'friends' ),
									value: 'default',
								},
								{
									label: __( 'Only friends', 'friends' ),
									value: 'only-friends',
								},
								{
									label: __( 'Everyone except friends', 'friends' ),
									value: 'not-friends',
								},
							] }
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withFriendsBlockVisibility' );

addFilter( 'editor.BlockEdit', 'friends/block-visibility', friendsBlockVisibility );
addFilter( 'blocks.registerBlockType', 'friends/block-visibility-attribute', friendsBlockVisibilityAddAttribute );
addFilter( 'blocks.getSaveElement', 'friends/block-visibility-save', friendsBlockVisibilitySave );
