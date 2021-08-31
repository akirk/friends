/**
 * WordPress dependencies
 */
import { loop } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from '../block.json';
import edit from './edit';
import save from './save';

const { name } = metadata;
export { metadata, name };

export const settings = {
	icon: loop,
	edit,
	save,
};

registerBlockType( metadata, settings );
