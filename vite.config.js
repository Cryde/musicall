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
      // @vueuse/core ships a stray `/* #__PURE__ */` annotation that Rolldown
      // can't attach (cosmetic tree-shaking hint). Mute it for node_modules only,
      // so a genuine bad annotation in our own code would still be reported.
      onwarn(warning, defaultHandler) {
        const location = warning.id ?? warning.loc?.file ?? ''
        if (warning.code === 'INVALID_ANNOTATION' && location.includes('node_modules')) {
          return
        }
        defaultHandler(warning)
      },
      input: {
        app: './assets/js/app.js',
        styles: './assets/style/style.css'
      }
    }
  },
  server: {
    cors: true
  }
})
