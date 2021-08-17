import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { InnerBlocks } from "@wordpress/block-editor";

registerBlockType( 'friends/message', {
	apiVersion: 2,
	edit: function() {
		return (
			<div { ...useBlockProps() }>
				<InnerBlocks />
			</div>
			);
	},
	save: () => {
		const blockProps = useBlockProps.save();

		return (
			<div { ...blockProps }>
				<InnerBlocks.Content />
			</div>
			);
	},
} );
