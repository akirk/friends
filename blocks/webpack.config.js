let webpack = require( 'webpack' ),
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
			loaders: [
				{
					test: /.js$/,
					loader: 'babel-loader',
					exclude: /node_modules/,
				},
			],
		},
		plugins: [
			new webpack.DefinePlugin( {
				'process.env.NODE_ENV': JSON.stringify( NODE_ENV ),
			} ),
		],
	};

if ( 'production' === NODE_ENV ) {
	webpackConfig.plugins.push( new webpack.optimize.UglifyJsPlugin() );
}

module.exports = webpackConfig;
