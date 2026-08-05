import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            // storage/framework/views/*.tmp is Laravel's ephemeral Blade
            // compile cache -- it churns on every request and has no bearing
            // on the asset build, but Vite's default watcher covers the whole
            // project root and sweeps it in. On Windows, fs.watch racing
            // against those files being renamed/deleted mid-watch throws
            // EBUSY and crashes the entire dev server.
            ignored: ['**/storage/**', '**/vendor/**'],
        },
    },
});
