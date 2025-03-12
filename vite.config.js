import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js',
            'resources/sass/app.scss',
            'resources/sass/fonts.scss'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
