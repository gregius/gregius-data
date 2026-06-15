// Imports @wordpress/scripts abstraction of Webpack
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const buildDir = path.resolve(__dirname, 'build');

// Find the MiniCssExtractPlugin in the default plugins array
const miniCssExtractPlugin = defaultConfig.plugins.find(
	(plugin) => plugin.constructor.name === 'MiniCssExtractPlugin'
);

// Update the MiniCssExtractPlugin configuration
if (miniCssExtractPlugin) {
	miniCssExtractPlugin.options.filename = (pathData) => {
		return pathData.chunk.name === 'editor'
			? 'editor.css'
			: '[name].min.[fullhash].css';
	};
}

// Create a new configuration object
const assetsConfig = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry, // Keep auto-discovered block entries (index.js, view.js)
		frontend: path.resolve(__dirname, 'src/scripts/frontend.js'),
		editor: path.resolve(__dirname, 'src/scripts/editor.js'),
		dashboard: path.resolve(__dirname, 'src/scripts/dashboard.js'),
	},
	output: {
		...defaultConfig.output,
		filename: (chunkData) => {
			return chunkData.chunk.name === 'editor'
				? 'editor.js'
				: '[name].min.[fullhash].js';
		},
		path: buildDir,
	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			// Loader for images and icons (only required if CSS references image files)
			{
				test: /\.(png|jpg|gif|svg)$/,
				type: 'asset/resource',
				generator: {
					filename: 'images/[name][ext]',
				},
			},
		],
	},
	plugins: [
		...defaultConfig.plugins,
		new CleanWebpackPlugin({
			cleanOnceBeforeBuildPatterns: [buildDir],
			protectWebpackAssets: false,
		}),
	],
};

module.exports = assetsConfig;
