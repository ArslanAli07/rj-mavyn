import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/admin.css', 'resources/js/admin.js'],
            refresh: true,
        }),
    ],
    build: {
        // Warn if any chunk exceeds 500kB — keeps bundles lean.
        chunkSizeWarningLimit: 500,
        // Split CSS into separate chunk files per JS entry for faster parallel loading.
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                // Separate vendor libraries (Alpine.js) into their own chunk
                // so browser caches them independently from app code.
                manualChunks(id) {
                    if (id.includes('node_modules/bootstrap/')) {
                        return 'bootstrap';
                    }
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },
            },
        },
    },
});

