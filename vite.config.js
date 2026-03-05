import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('recharts')) {
                        return 'charts';
                    }

                    if (id.includes('laravel-echo') || id.includes('pusher-js')) {
                        return 'realtime';
                    }

                    if (id.includes('react-modal-sheet')) {
                        return 'sheet';
                    }

                    if (id.includes('react-router-dom')) {
                        return 'router';
                    }

                    if (id.includes('/react/') || id.includes('react-dom')) {
                        return 'react-vendor';
                    }
                },
            },
        },
    },
});
