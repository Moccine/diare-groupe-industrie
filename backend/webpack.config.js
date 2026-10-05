const Encore = require('@symfony/webpack-encore');
const path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('site', '../frontend/assets/js/site.js')
    .addEntry('admin', '../frontend/assets/js/admin.js')
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSassLoader();

const webpackConfig = Encore.getWebpackConfig();

webpackConfig.resolve.modules = [
    path.resolve(__dirname, 'node_modules'),
    ...(webpackConfig.resolve.modules || ['node_modules']),
];

module.exports = webpackConfig;
