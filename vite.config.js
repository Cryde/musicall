import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";
import vuePlugin from "@vitejs/plugin-vue2";

export default defineConfig({
    define: {
        global: 'window'
    },
    plugins: [
        vuePlugin(),
        symfonyPlugin(),
    ],
    build: {
        assetsInlineLimit: 0,
        rollupOptions: {
            input: {
                app: "./assets/js/app.js",
                styles: "./assets/css/app.scss"
            },
            output: {
                manualChunks: {
                    vue: ['vue']
                }
            }
        }
    },
});
