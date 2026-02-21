import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/__tests__/**/*.{test,spec}.{js,jsx}'],
        setupFiles: ['resources/js/__tests__/setup.js'],
    },
});
