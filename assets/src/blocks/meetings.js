/**
 * Registers the block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import {registerBlockType} from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import json from './block.json';
import Edit from './edit';

/**
 * Registering the new block type definition.
 *
 */
registerBlockType(json, {
	edit: Edit
});
