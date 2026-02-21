import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/App.jsx', 'resources/css/app.css'],
            hotFile: 'dist/hot', // Hot file for detecting dev mode
            publicDirectory: 'dist', // Helper for the plugin
            buildDirectory: 'build', // Output: dist/build
            refresh: true,
        }),
        react(),
    ],
    build: {
        outDir: 'dist/build',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            output: {
                entryFileNames: 'assets/[name].js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: 'assets/[name].[ext]',
            },
        },
    },
});