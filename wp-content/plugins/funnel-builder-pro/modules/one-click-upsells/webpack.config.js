const path = require('path');
let settings = {
    // define entry file and output
    entry: path.resolve('compatibilities/page-builders/divi/includes/main.js'),
    output: {
        path: path.resolve('compatibilities/page-builders/divi/includes/'),
        filename: 'loader.js',
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                loader: 'babel-loader',
                exclude: /node_modules/,
                options: {
                    plugins: ['lodash','@babel/plugin-proposal-class-properties'],
                    presets: ['@babel/preset-env']
                }
            }
        ]
    },
    devServer: {
        disableHostCheck: true,
        port: 9000,
        headers: {
            "Access-Control-Allow-Origin": "*",
            "Access-Control-Allow-Methods": "GET, POST, PUT, DELETE, PATCH, OPTIONS",
            "Access-Control-Allow-Headers": "X-Requested-With, content-type, Authorization"
        }
    }
};
module.exports = settings;