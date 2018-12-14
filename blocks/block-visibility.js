const { __ } = wp.i18n;
const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody, SelectControl } = wp.components;

const friendsBlockVisibility = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { attributes: {
			friends_visibility,
		}, setAttributes } = props;

		const onChangeVisibility = ( friends_visibility ) => {
			setAttributes( { friends_visibility } );
		}

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody className="friends-block-visibility" title={ __( 'Friends' ) }>
					<SelectControl
							label={ __( 'Block visibility' ) }
							onChange={ onChangeVisibility }
							value={ friends_visibility }
							options={ [
								{
									label: 'For everyone',
									value: 'default',
								},
								{
									label: 'Only friends',
									value: 'only-friends',
								},
								{
									label: 'Everyone except friends',
									value: 'not-friends',
								},
							] }
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, "friendsBlockVisibility" );

addFilter( 'blocks.registerBlockType', 'llms/visibility-attributes', ( settings, name ) => {

	if ( ! settings.attributes ) {
		settings.attributes = {};
	}

	settings.attributes.friends_visibility = {
		default: 'all',
		type: 'string',
	};

	return settings;
} );
addFilter( 'editor.BlockEdit', 'friends/block-visibility', friendsBlockVisibility );
