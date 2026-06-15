/**
 * RAG Chat Block - Registration
 *
 * @package gregius-data
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import edit from './edit';
import './style.scss';

/**
 * Register the RAG Chat block.
 */
registerBlockType(metadata.name, {
	...metadata,
	edit,
	save: () => null, // Dynamic block - rendered by PHP
});
