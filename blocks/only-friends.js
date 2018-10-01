const { __, setLocaleData } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { RichText } = wp.editor;

// setLocaleData( window.friendsData.localeData, 'friends' );

registerBlockType( 'friends/only-friends', {
	title: __( 'Only visible to friends', 'friends' ),
	icon: 'universal-access-alt',
	category: 'layout',
	attributes: {
		content: {
			type: 'array',
			source: 'children',
			selector: 'p',
		},
	},
	edit: ( props ) => {
		const { attributes: { content }, setAttributes, className } = props;
		const onChangeContent = ( newContent ) => {
			setAttributes( { content: newContent } );
		};
		return (
			<RichText
				tagName="p"
				className="only-friends"
				onChange={ onChangeContent }
				value={ content }
				/>
			);
	},
	save: ( props ) => {
		return <RichText.Content tagName="p" value={ props.attributes.content } />;
	},
} );
