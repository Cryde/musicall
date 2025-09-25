import { PrimeVueResolver } from '@primevue/auto-import-resolver'
import tailwindcss from '@tailwindcss/vite'
import vuePlugin from '@vitejs/plugin-vue'
import Components from 'unplugin-vue-components/vite'
import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'

export default defineConfig({
  plugins: [
    vuePlugin(),
    symfonyPlugin(),
    tailwindcss(),
    Components({
      resolvers: [PrimeVueResolver()]
    })
  ],
  build: {
    assetsInlineLimit: 0,
    rollupOptions: {
      input: {
        app: './assets/js/app.js',
        styles: './assets/style/style.css'
      },
      output: {
        manualChunks: {
          vue: ['vue']
        }
      }
    }
  },
  server: {
    cors: true
  }
})
