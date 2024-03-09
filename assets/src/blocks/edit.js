/**
 * Renders the edit interface for Meeting blocks
 */
import {InspectorControls, useBlockProps, BlockIcon} from '@wordpress/block-editor';
import {
	Panel,
	PanelBody,
	__experimentalNumberControl as NumberControl,
	SelectControl,
	Placeholder,
	TextControl
} from '@wordpress/components';
import {__} from '@wordpress/i18n';

/**
 * @return {WPElement} Element to render.
 * @param props
 */
const Edit = (props) => {
	const {
		attributes: {blocktype},
		setAttributes
	} = props;
	const {serverSideRender: ServerSideRender} = wp;
	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<Panel>
					<PanelBody title={__('Block Settings', '12-step-meeting-list')} icon="admin-plugins">
						<SelectControl
							label="Select Block Type"
							value={props.attributes.blockType}
							options={[
								{label: 'Meetings UI', value: 'tsml_ui'},
								{label: 'Next Meetings', value: 'tsml_next_meetings'},
								{label: 'Types List', value: 'tsml_types_list'},
								{label: 'Regions List', value: 'tsml_regions_list'}
							]}
							onChange={(newtype) => setAttributes({blockType: newtype})}
						/>
						<NumberControl
							label="Meetings to show"
							value={props.attributes.count}
							className={props.attributes.blockType === 'tsml_next_meetings' ? '' : 'hidden'}
							onChange={(newCount) => setAttributes({count: newCount})}
						/>
						<TextControl
							label="Message if there are no upcoming meetings"
							value={props.attributes.message}
							className={props.attributes.blockType === 'tsml_next_meetings' ? '' : 'hidden'}
							onChange={(newMessage) => setAttributes({message: newMessage})}
						/>
					</PanelBody>
				</Panel>
			</InspectorControls>
			{props.attributes.blockType === 'tsml_ui' && (
				<Placeholder
					icon={<BlockIcon icon="groups" size="50" />}
					label={__('Meetings', 'tsml')}
					instructions={__(
						"View the page to see the block. it's recommended not to put any page content below the block, and to make the block as wide as possible.",
						'12-step-meeting-list'
					)}
				></Placeholder>
			)}
			<ServerSideRender block="tsml/meetings" attributes={props.attributes} />
		</div>
	);
};
export default Edit;
