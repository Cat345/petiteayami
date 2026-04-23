const path = require('path');

module.exports = {
  // Webpack starts bundling the assets from the following file.
  entry: {
    'wfop-divi5-visual-builder': './src/index.ts',
  },

  // Divi Visual Builder use of scripts that is already enqueued by WordPress and available
  // in global scope so those scripts don't need to be included on the bundle.
  externals: {
    // Third party dependencies.
    jquery: 'jQuery',
    lodash: 'lodash',
    react: ['vendor', 'React'],
    'react-dom': ['vendor', 'ReactDOM'],

    // WordPress dependencies.
    '@wordpress/i18n': ['vendor', 'wp', 'i18n'],
    '@wordpress/hooks': ['vendor', 'wp', 'hooks'],

    // Divi dependencies.
    '@divi/rest': ['divi', 'rest'],
    '@divi/data': ['divi', 'data'],
    '@divi/module': ['divi', 'module'],
    '@divi/module-utils': ['divi', 'moduleUtils'],
    '@divi/modal': ['divi', 'modal'],
    '@divi/field-library': ['divi', 'fieldLibrary'],
    '@divi/module-library': ['divi', 'moduleLibrary'],
    '@divi/types': ['divi', 'types'],
  },

  // This option determine how different types of module within the project will be treated.
  module: {
    rules: [
      // Handle `.tsx` and `.ts` files.
      {
        test: /\.tsx?$/,
        use: {
          loader: 'ts-loader',
          options: {
            transpileOnly: true,
            compilerOptions: {
              noEmit: false,
            },
          },
        },
        exclude: /node_modules/,
      },

      // Handle `.jsx` files.
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'thread-loader',
            options: {
              workers: -1,
            },
          },
          {
            loader: 'babel-loader',
            options: {
              compact: false,
              presets: [
                ['@babel/preset-env', {
                  modules: false,
                  targets: '> 5%',
                }],
                '@babel/preset-react',
              ],
              plugins: [
                '@babel/plugin-proposal-class-properties',
              ],
              cacheDirectory: false,
            },
          }
        ]
      },
    ]
  },

  // Determine how modules are resolved.
  resolve: {
    extensions: ['.js', '.jsx', '.tsx', '.ts', '.json'],
  },

  // Determine where the created bundles will be outputted.
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'build'),
  },
  stats: {
    errorDetails: true,
  },
};
