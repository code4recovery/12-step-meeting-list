const path = require('path');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const webpack = require('webpack');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

// Webpack creates empty .js files for CSS-only entry points; this removes them
class RemoveEmptyJsPlugin {
	apply(compiler) {
		compiler.hooks.compilation.tap('RemoveEmptyJsPlugin', (compilation) => {
			compilation.hooks.processAssets.tap(
				{name: 'RemoveEmptyJsPlugin', stage: webpack.Compilation.PROCESS_ASSETS_STAGE_OPTIMIZE_SIZE},
				() => {
					for (const name of Object.keys(compilation.assets)) {
						if (name.startsWith('css/') && name.endsWith('.js')) {
							compilation.deleteAsset(name);
						}
					}
				}
			);
		});
	}
}

const assetConfig = {
	name: 'assets',
	mode: 'production',
	entry: {
		'js/admin.min': [
			'./node_modules/timepicker/jquery.timepicker.min.js',
			'./assets/src/maps.js',
			'./assets/src/admin.js',
		],
		'js/public.min': [
			'./node_modules/mark.js/dist/jquery.mark.js',
			'./assets/js/bootstrap.dropdown.js',
			'./assets/src/maps.js',
			'./assets/src/public.js',
		],
		'css/admin.min': [
			'./node_modules/timepicker/jquery.timepicker.min.css',
			'./assets/src/admin.scss',
		],
		'css/public.min': ['./assets/src/public.scss'],
	},
	output: {
		path: path.resolve(__dirname, 'assets'),
		filename: '[name].js',
	},
	externals: {
		jquery: 'jQuery',
	},
	module: {
		rules: [
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader,
					{loader: 'css-loader', options: {url: false}},
					{
						loader: 'sass-loader',
						options: {
							api: 'modern',
							sassOptions: {
								style: 'compressed',
								// bootstrap-sass (v3) uses deprecated Sass features; can be removed if we upgrade to Bootstrap 5
								silenceDeprecations: [
									'import',
									'global-builtin',
									'color-functions',
									'slash-div',
									'if-function',
								],
							},
						},
					},
				],
			},
			{
				test: /\.css$/,
				use: [MiniCssExtractPlugin.loader, {loader: 'css-loader', options: {url: false}}],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin({
			filename: '[name].css',
		}),
		new RemoveEmptyJsPlugin(),
	],
	optimization: {
		minimizer: [new TerserPlugin({extractComments: false}), new CssMinimizerPlugin()],
	},
	devtool: false,
};

// wp-scripts config for blocks
const CopyPlugin = require('copy-webpack-plugin');
const blocksConfig = {
	...defaultConfig,
	name: 'blocks',
	entry: {
		'blocks/meetings': './assets/src/blocks/meetings.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'assets/build'),
	},
	plugins: [
		...defaultConfig.plugins.filter((p) => p.constructor.name !== 'CopyPlugin'),
		new CopyPlugin({
			patterns: [
				{from: '**/block.json', context: 'assets/src', noErrorOnMissing: true},
				{from: '**/*.php', context: 'assets/src', noErrorOnMissing: true},
			],
		}),
	],
};

module.exports = [assetConfig, blocksConfig];
