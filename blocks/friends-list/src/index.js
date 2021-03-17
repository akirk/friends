import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'friends/friends-list', {
	apiVersion: 2,
	edit: function( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		return (
			<>
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
				<div {...blockProps}>
					<ServerSideRender
					block="friends/friends-list"
						attributes={ attributes }
					/>
				</div>
			</>
		);
	},

    save() {
    	return null;
    },
} );
