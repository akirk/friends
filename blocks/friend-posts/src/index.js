import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody, RangeControl, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'friends/friend-posts', {
	apiVersion: 2,
	edit: function( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		return (
			<>
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
						<CheckboxControl
							label={ __( 'Link to local page', 'friends' ) }
							checked={ attributes.internal_link }
							onChange={ internal_link => setAttributes( { internal_link } ) }
							/>
					</PanelBody>
				</InspectorControls>
				<div {...blockProps}>
					<ServerSideRender
						block="friends/friend-posts"
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
