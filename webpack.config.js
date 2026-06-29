const path   = require('path');
const fs     = require('fs');
const crypto = require('crypto');

/**
 * Plugin inline: al finalizar la compilación, escribe el BUILD_HASH
 * en storage/build_hash.txt para que PHP lo lea en el layout.
 *
 * Esto evita dependencias externas (webpack-manifest-plugin, etc.).
 */
class BuildHashPlugin {
    apply(compiler) {
        compiler.hooks.afterEmit.tap('BuildHashPlugin', (compilation) => {
            // Hash reproducible basado en todos los assets emitidos
            const hash = compilation.fullHash
                ? compilation.fullHash.slice(0, 8)
                : crypto.randomBytes(4).toString('hex');

            const outDir = path.resolve(__dirname, 'storage');
            if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
            fs.writeFileSync(path.join(outDir, 'build_hash.txt'), hash);

            console.log(`\n✔ BUILD_HASH = ${hash}  →  storage/build_hash.txt\n`);
        });
    }
}

module.exports = (env, argv) => {
    const isProd = argv.mode === 'production';

    return {
        entry: {
            app: './public/assets/js/app.js',
            components: [
                './public/assets/js/Component.js',
                './public/assets/js/FormComponent.js',
                './public/assets/js/TableComponent.js',
                './public/assets/js/ModalComponent.js',
                './public/assets/js/init-components.js',
            ],
        },
        output: {
            path: path.resolve(__dirname, 'public/dist'),
            filename: isProd ? '[name].[contenthash:8].js' : '[name].js',
            clean: true,
        },
        mode: argv.mode || 'development',
        devtool: isProd ? false : 'source-map',
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: { presets: ['@babel/preset-env'] },
                    },
                },
                {
                    test: /\.css$/,
                    use: ['style-loader', 'css-loader'],
                },
            ],
        },
        optimization: {
            splitChunks: {
                chunks: 'all',
            },
        },
        plugins: [
            new BuildHashPlugin(),
        ],
    };
};
