let webpack = require( 'webpack' ),
TerserPlugin = require('terser-webpack-plugin'),
NODE_ENV = process.env.NODE_ENV || 'development',
webpackConfig = {
	entry: {
		'block-visibility': './src/block-visibility.js',
		'friends-list': './src/friends-list.js',
		'friend-posts': './src/friend-posts.js',
	},
	output: {
		path: __dirname,
		filename: '[name].build.js',
	},
	module: {
		rules: [
		{
			test: /.js$/,
			loader: 'babel-loader',
			exclude: /node_modules/,
		},
		],
	},
	optimization: {
		minimizer: [
		new TerserPlugin({
			terserOptions: {
			}
		}),
		],
	},
	plugins: [
		new webpack.DefinePlugin( {
			'process.env.NODE_ENV': JSON.stringify( NODE_ENV ),
		} ),
	],
};

module.exports = webpackConfig;
