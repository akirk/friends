import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

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
		return (
			<>
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
			</>
		);
	};
}, 'withFriendsBlockVisibility' );

addFilter( 'editor.BlockEdit', 'friends/block-visibility', friendsBlockVisibility );
