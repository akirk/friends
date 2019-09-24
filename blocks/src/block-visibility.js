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

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody className="friends-block-visibility" title={ __( 'Friends Visibility', 'friends' ) }>
					<SelectControl
							label={ __( 'Block visibility', 'friends' ) }
							onChange={ friends_visibility => setAttributes( { friends_visibility } ) }
							value={ friends_visibility }
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
}, 'friendsBlockVisibility' );

addFilter( 'editor.BlockEdit', 'friends/block-visibility', friendsBlockVisibility );
