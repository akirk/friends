import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'friends/friends-list', {
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
									label: __( 'Subscriptions', 'friends' ),
									value: 'subscriptions',
								},
								{
									label: __( 'Starred', 'friends' ),
									value: 'starred',
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
