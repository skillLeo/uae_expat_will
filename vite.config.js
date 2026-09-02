import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    ssr: {
        // Bundle every dependency into bootstrap/ssr/ssr.js.
        //
        // By default Vite leaves imports of installed packages external, so
        // the renderer needs node_modules beside it at runtime -- 191 MB of it
        // on the old host, and a hard failure the moment it is absent or out
        // of step with the build. The production server has no npm, no
        // package.json and no reason to acquire either: it runs one file.
        noExternal: true,
    },

    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
