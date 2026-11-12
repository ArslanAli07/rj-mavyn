import { defineConfig } from 'vite';
export default defineConfig({
    base: './',
    build: {outDir:'dist/build', emptyOutDir:true, manifest:true, rollupOptions:{input:'resources/js/main.js'}},
});
