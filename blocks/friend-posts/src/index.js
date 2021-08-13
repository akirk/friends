/**
 * WordPress dependencies
 */
import { loop as icon } from '@wordpress/icons';
import { addFilter } from '@wordpress/hooks';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from '../block.json';
import edit from './edit';
import save from './save';
import variations from './variations';
import deprecated from './deprecated';
import queryInspectorControls from './hooks';

const { name } = metadata;
export { metadata, name };

export const settings = {
	icon,
	edit,
	save,
	variations,
	deprecated,
};

// Importing this file includes side effects and is whitelisted
// in block-library/package.json under `sideEffects`.
addFilter( 'editor.BlockEdit', 'friends/friend-posts', queryInspectorControls );
registerBlockType( metadata, settings );
