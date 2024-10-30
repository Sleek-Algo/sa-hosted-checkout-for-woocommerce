const webpack = require( 'webpack' );
const path = require( 'path' );
const FileManagerPlugin = require( 'filemanager-webpack-plugin' );
const create_zip = process.env.CREATE_ZIP || 'no'; // Change this to 'yes' to create the zip
const NODE_ENV = process.env.NODE_ENV || 'development';
const TerserPlugin = require( 'terser-webpack-plugin' );

/**
 * WordPress Dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

// Base plugins array from default config
const plugins = [ ...defaultConfig.plugins ];

// Conditionally add FileManagerPlugin based on create_zip
if ( create_zip === 'yes' ) {
	const DevelopmentZipVersionFolder =
		'../../../sahcfwc-backups/sahcfwc-development-latest-version/sa-hosted-checkout-for-woocommerce';
	const DevelopmentZipFileSource =
		'../../../sahcfwc-backups/sahcfwc-development-latest-version';
	const ProductionZipVersionFolder =
		'../../../sahcfwc-backups/sahcfwc-production-latest-version/sa-hosted-checkout-for-woocommerce';
	const ProductionZipFileSource =
		'../../../sahcfwc-backups/sahcfwc-production-latest-version';

	plugins.push(
		new FileManagerPlugin( {
			events: {
				onStart: {
					delete: [
						{
							source: DevelopmentZipVersionFolder,
							options: {
								force: true,
							},
						},
						{
							source: DevelopmentZipVersionFolder + '.zip',
							options: {
								force: true,
							},
						},
						{
							source: ProductionZipVersionFolder,
							options: {
								force: true,
							},
						},
						{
							source: ProductionZipVersionFolder + '.zip',
							options: {
								force: true,
							},
						},
					],
				},
				onEnd: {
					mkdir: [
						ProductionZipVersionFolder,
						DevelopmentZipVersionFolder,
					],
					copy: [
						/**
						 * Start - Development Version
						 */
						{
							source: '../assets',
							destination:
								DevelopmentZipVersionFolder + '/assets',
						},
						{
							source: '../freemius',
							destination:
								DevelopmentZipVersionFolder + '/freemius',
						},
						{
							source: '../includes',
							destination:
								DevelopmentZipVersionFolder + '/includes',
						},
						{
							source: '../languages',
							destination:
								DevelopmentZipVersionFolder + '/languages',
						},
						{
							source: '../libraries',
							destination:
								DevelopmentZipVersionFolder + '/libraries',
						},
						{
							source: '../vendor',
							destination:
								DevelopmentZipVersionFolder + '/vendor',
						},
						{
							source: '../readme.txt',
							destination:
								DevelopmentZipVersionFolder + '/readme.txt',
						},
						{
							source: '../composer.json',
							destination:
								DevelopmentZipVersionFolder + '/composer.json',
						},
						{
							source: '../sa-hosted-checkout-for-woocommerce.php',
							destination:
								DevelopmentZipVersionFolder +
								'/sa-hosted-checkout-for-woocommerce.php',
						},
						{
							source: './free-app',
							destination:
								DevelopmentZipVersionFolder +
								'/node-dev/free-app',
						},
						{
							source: './package.json',
							destination:
								DevelopmentZipVersionFolder +
								'/node-dev/package.json',
						},
						{
							source: './webpack.config.js',
							destination:
								DevelopmentZipVersionFolder +
								'/node-dev/webpack.config.js',
						},
						/**
						 * End - Development Version
						 */

						/**
						 * Start - Production Version
						 */
						{
							source: '../assets',
							destination: ProductionZipVersionFolder + '/assets',
						},
						{
							source: '../freemius',
							destination:
								ProductionZipVersionFolder + '/freemius',
						},
						{
							source: '../includes',
							destination:
								ProductionZipVersionFolder + '/includes',
						},
						{
							source: '../languages',
							destination:
								ProductionZipVersionFolder + '/languages',
						},
						{
							source: '../libraries',
							destination:
								ProductionZipVersionFolder + '/libraries',
						},
						{
							source: '../vendor',
							destination: ProductionZipVersionFolder + '/vendor',
						},
						{
							source: '../readme.txt',
							destination:
								ProductionZipVersionFolder + '/readme.txt',
						},
						{
							source: '../composer.json',
							destination:
								ProductionZipVersionFolder + '/composer.json',
						},
						{
							source: '../sa-hosted-checkout-for-woocommerce.php',
							destination:
								ProductionZipVersionFolder +
								'/sa-hosted-checkout-for-woocommerce.php',
						},
						/**
						 * End - Production Version
						 */
					],
					archive: [
						{
							source: DevelopmentZipFileSource,
							destination: DevelopmentZipVersionFolder + '.zip',
							options: {
								globOptions: {
									// https://github.com/Yqnn/node-readdir-glob#options
									dot: true,
								},
							},
						},
						{
							source: ProductionZipFileSource,
							destination: ProductionZipVersionFolder + '.zip',
							options: {
								globOptions: {
									// https://github.com/Yqnn/node-readdir-glob#options
									dot: true,
								},
							},
						},
					],
				},
			},
		} )
	);
}

module.exports = {
	...defaultConfig,
	...{
		mode: NODE_ENV,
		entry: {
			'free-app': './free-app/index.jsx',
		},
		output: {
			path: path.resolve( __dirname, '../assets/backend' ),
			filename: '[name].js',
		},
		plugins: plugins,
		optimization: {
			minimizer: [
				new TerserPlugin( {
					terserOptions: {
						output: {
							comments: /<\/?fs_premium_only>/i, // Retain only Freemius tags
						},
					},
					extractComments: false, // Don't extract comments to separate file
				} ),
			],
		},
	},
};