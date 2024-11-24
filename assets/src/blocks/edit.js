/**
 * Renders the edit interface for the Meeting block
 */
import {BlockIcon, InspectorControls, MediaUpload, useBlockProps} from '@wordpress/block-editor';
import {
	__experimentalDivider as Divider,
    Button,
    ColorPalette,
    PanelBody,
    Placeholder,
    RangeControl,
} from '@wordpress/components';
import {useSelect} from '@wordpress/data';
import {__} from '@wordpress/i18n';

/**
 * Edit component for customizing styles and attributes of the block
 * @param attributes
 * @param setAttributes
 * @returns {JSX.Element}
 * @constructor
 */
const Edit = ({attributes, setAttributes}) => {
	const {
		alertBackgroundColor,
		alertTextColor,
		backgroundColor,
		borderRadius,
		focusColor,
		fontFamily,
		fontSize,
		inPersonBadgeColor,
		inactiveBadgeColor,
		linkColor,
		onlineBadgeColor,
		onlineBackgroundImage,
		textColor
	} = attributes;
	const colorPalette = useSelect('core/block-editor').getSettings().colors
	const blockProps = useBlockProps({
		style: {
			'--alert-background': alertBackgroundColor,
			'--alert-text': alertTextColor,
			'--background': backgroundColor,
			'--border-radius': borderRadius + 'px',
			'--focus': focusColor,
			'--font-family': fontFamily,
			'--font-size': fontSize + 'px',
			'--in-person': inPersonBadgeColor,
			'--inactive': inactiveBadgeColor,
			'--link': linkColor,
			'--online': onlineBadgeColor,
			'--online-background-image': `url(${onlineBackgroundImage})`,
			'--text': textColor
		}
	})
	const {serverSideRender: ServerSideRender} = wp
	const legendStyles = {
		fontSize: '11px',
		fontWeight: '500',
		lineHeight: '1.4',
		textTransform: 'uppercase',
		display: 'block',
		padding: '0',
		marginBottom: '1.5em'
	}
	const helpStyles = {
		marginTop: '-.5em',
		marginBottom: '1.5em',
		fontSize: '11px',
	}
	return (
		<div {...useBlockProps()}>
			<InspectorControls group="styles">
				<PanelBody title={__('Backgrounds', '12-step-meeting-list')} initialOpen={false}>
					<div style={{marginTop: '1.5em'}}>
						<legend style={{...legendStyles}}>Background color</legend>
						<p style={{...helpStyles}}>
							<em>Applies to entire meeting list block.</em>
						</p>
						<ColorPalette
							value={backgroundColor}
							colors={[...colorPalette]}
							onChange={(value) => setAttributes({backgroundColor: value})}
						/>
					</div>
					<Divider />
					<div>
						<legend style={{...legendStyles}}>Online Background image</legend>
						<p style={{...helpStyles}}>
							<em>Will be shown instead of a map for online meetings (approx 2000px x 2000px).</em>
						</p>
						<MediaUpload
							onSelect={(media) => setAttributes({onlineBackgroundImage: media.url})}
							allowedTypes={['image']}
							value={onlineBackgroundImage}
							render={({open}) => (
								<Button onClick={open} variant="primary" help="test">
									{__('Select Background Image', '12-step-meeting-list')}
								</Button>
							)}
						/>
						{onlineBackgroundImage && (
							<Button
								onClick={() => setAttributes({onlineBackgroundImage: ''})}
								variant="secondary"
								style={{marginTop: '10px', marginBottom: '1.5em'}}
							>
								{__('Remove Background Image', '12-step-meeting-list')}
							</Button>
						)}
					</div>
				</PanelBody>
				<PanelBody title={__('Text Colors', '12-step-meeting-list')} initialOpen={false}>
					<div style={{marginTop: '1.5em'}}>
						<legend style={{...legendStyles}}>Text color</legend>
						<ColorPalette value={textColor} colors={[...colorPalette]} onChange={(value) => setAttributes({textColor: value})} />
					</div>
					<Divider />
					<div>
						<legend style={{...legendStyles}}>Link color</legend>
						<ColorPalette value={linkColor} colors={[...colorPalette]} onChange={(value) => setAttributes({linkColor: value})} />
					</div>
					<Divider />
					<div>
						<legend style={{...legendStyles}}>Input focus shadow color</legend>
						<ColorPalette
							enableAlpha={true}
							value={focusColor}
							colors={[...colorPalette]}
							onChange={(value) => setAttributes({focusColor: value})}
						/>
					</div>
				</PanelBody>
				<PanelBody title={__('Alert Colors', '12-step-meeting-list')} initialOpen={false}>
					<div style={{marginTop: '1.5em'}}>
						<legend style={{...legendStyles}}>Background color</legend>
						<ColorPalette
							value={alertBackgroundColor}
							colors={[...colorPalette]}
							onChange={(value) => setAttributes({alertBackgroundColor: value})}
						/>
					</div>
					<Divider />
					<div>
						<legend style={{...legendStyles}}>Text color</legend>
						<ColorPalette value={alertTextColor} colors={[...colorPalette]} onChange={(value) => setAttributes({alertTextColor: value})} />
					</div>
				</PanelBody>
				<PanelBody title={__('Badge Colors', '12-step-meeting-list')} initialOpen={false}>
					<div style={{marginTop: '1.5em'}}>
						<legend style={{...legendStyles}}>In person meeting</legend>
						<ColorPalette
							value={inPersonBadgeColor}
							colors={[...colorPalette]}
							onChange={(value) => setAttributes({inPersonBadgeColor: value})}
						/>
					</div>
					<Divider />
					<div>
						<legend style={{...legendStyles}}>Inactive meeting</legend>
						<ColorPalette
							value={inactiveBadgeColor}
							colors={[...colorPalette]}
							onChange={(value) => setAttributes({inactiveBadgeColor: value})}
						/>
					</div>
					<Divider />
					<div>
						<legend style={{...legendStyles}}>Online meeting</legend>
						<ColorPalette
							label={__('Online badge color', '12-step-meeting-list')}
							value={onlineBadgeColor}
							colors={[...colorPalette]}
							onChange={(value) => setAttributes({onlineBadgeColor: value})}
						/>
					</div>
				</PanelBody>
				<PanelBody title={__('Border', '12-step-meeting-list')} initialOpen={false}>
					<div style={{marginTop: '1.5em'}}>
						<RangeControl
							label={__('Border Radius (px)', '12-step-meeting-list')}
							value={borderRadius}
							onChange={(newValue) => setAttributes({borderRadius: newValue})}
							min={0}
							max={50}
						/>
					</div>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon={<BlockIcon icon="groups" size="50" />}
				label={__('Meetings', '12-step-meeting-list')}
				instructions={__(
					"View the page to see the block. it's recommended not to put any page content below the block, and to make the block as wide as possible.",
					'12-step-meeting-list'
				)}
			></Placeholder>
			<ServerSideRender block="tsml/meetings" attributes={attributes} />
		</div>
	);
};
export default Edit;
