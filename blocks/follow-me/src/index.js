import { Placeholder, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { InnerBlocks, RichText } from "@wordpress/block-editor";

function getHTML( is_saving ) {
	return (
		<div>
			<input type="text" name="friends_friend_request_url" placeholder="https://example.com/" />
			<button disabled={ is_saving ? null : "disabled" }>{ __( 'Follow this site', 'friends' ) }</button>
		</div>
	);
}

registerBlockType( 'friends/follow-me', {
	apiVersion: 2,
	edit: function( { attributes, isSelected, setAttributes } ) {
		const content = __( 'Do you have your own blog? Enter your URL to connect with me!', 'friends' );
		return (
			<div { ...useBlockProps() }>
				<form method="post">
					<InnerBlocks
						template={
							[
							['core/paragraph', { content }]
							]
						}
					/>

				{ getHTML( false ) }
				</form>
			</div>
			);
	},
	save: () => {
        const blockProps = useBlockProps.save();

        return (
            <div { ...blockProps }>
				<form method="post">
	                <InnerBlocks.Content />
	                { getHTML( true ) }
				</form>
           </div>
        );
    },
} );
