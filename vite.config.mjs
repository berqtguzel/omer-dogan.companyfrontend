import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => {
    const isBuild = command === 'build';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/js/app.jsx',
                ],
                refresh: !isBuild,
            }),
            react(),
        ],

        build: {
            sourcemap: false,
            emptyOutDir: true,
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (id.includes('node_modules/three')) return 'three';
                        if (id.includes('node_modules/gsap')) return 'gsap';
                        if (id.includes('node_modules/ogl')) return 'ogl';
                        if (id.includes('node_modules/framer-motion')) return 'motion';
                        if (id.includes('node_modules/react-icons')) return 'icons';
                    },
                },
            },
        },

        esbuild: {
            drop: isBuild ? ['debugger'] : [],
        },
    };
});
