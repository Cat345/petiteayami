const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
let settings = {
    // define entry file and output
    mode: 'production',
    entry: path.resolve('./main.js'),
    output: {
        path: path.resolve('./'),
        filename: 'loader.js',
        pathinfo: false,
    },
    optimization: {
        minimize: true,
        // keep @license comments inline instead of a *.LICENSE.txt file
        minimizer: [new TerserPlugin({ extractComments: false })]
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                loader: 'babel-loader',
                exclude: /node_modules/,
                options: {
                    plugins: ['lodash'],
                    presets: ['@babel/preset-env']
                }
            }
        ]
    }
};
module.exports = settings;