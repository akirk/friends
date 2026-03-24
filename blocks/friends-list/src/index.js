import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'friends/friends-list', {
	edit: function( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();

		// Build folder options from localized data.
		var folderOptions = [
			{ label: __( '— None —', 'friends' ), value: 0 },
		];
		if ( window.friendsFolders && window.friendsFolders.length ) {
			window.friendsFolders.forEach( function( folder ) {
				folderOptions.push( {
					label: folder.name,
					value: folder.term_id,
				} );
			} );
		}

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
							onChange={ user_types => setAttributes( { user_types, folder: 0 } ) }
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
								{
									label: __( 'Grouped by Folder', 'friends' ),
									value: 'folders',
								},
							] }
						/>
						{ folderOptions.length > 1 && (
							<SelectControl
								label={ __( 'Folder', 'friends' ) }
								onChange={ folder => setAttributes( { folder: parseInt( folder, 10 ), user_types: folder ? 'folder' : 'subscriptions' } ) }
								value={ attributes.folder }
								options={ folderOptions }
							/>
						) }
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
