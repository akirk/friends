const { __ } = wp.i18n;
const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody, SelectControl } = wp.components;

const friendsBlockVisibility = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { attributes, setAttributes } = props;
		let friendsVisibility = '';
		if ( typeof attributes.className !== 'undefined' ) {
			if ( /\bonly-friends\b/.test( attributes.className ) ) {
				friendsVisibility = 'only-friends';
			} else if ( /\bnot-friends\b/.test( attributes.className ) ) {
				friendsVisibility = 'not-friends';
			}
		}
		console.log(attributes, friendsVisibility )
		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody className="friends-block-visibility" title={ __( 'Friends Visibility', 'friends' ) }>
					<SelectControl
							label={ __( 'Block visibility', 'friends' ) }
							onChange={ friendsVisibility => {
								const className = ((attributes.className || '').replace( /\b(only|not)-friends\b/g, '' ) + ' ' + friendsVisibility).replace(/^\s+|\s+$/, '' );
								setAttributes( { className } ) }
							}
							value={ friendsVisibility }
							options={ [
								{
									label: __( 'For everyone', 'friends' ),
									value: '',
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
