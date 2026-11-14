import { PrimeVueResolver } from '@primevue/auto-import-resolver'
import tailwindcss from '@tailwindcss/vite'
import vuePlugin from '@vitejs/plugin-vue'
import Components from 'unplugin-vue-components/vite'
import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'

// Names this build, so a tab can tell when a deploy has replaced the code it runs (#1105). Baked into the
// bundle below and written next to it as version.json, which the server reads for its X-App-Version
// header. A timestamp, so every build differs and a reader can tell when it was made.
const BUILD_ID = new Date().toISOString().replace(/\D/g, '')

function buildVersionFile() {
  return {
    name: 'musicall-build-version',
    apply: 'build',
    generateBundle() {
      this.emitFile({
        type: 'asset',
        fileName: 'version.json',
        source: JSON.stringify({ version: BUILD_ID })
      })
    }
  }
}

export default defineConfig({
  plugins: [
    vuePlugin(),
    symfonyPlugin(),
    buildVersionFile(),
    tailwindcss(),
    Components({
      resolvers: [PrimeVueResolver()]
    })
  ],
  // vue-i18n's bundler build reads these at runtime unless they are replaced here. Composition API
  // only, so the legacy API is dropped; the message compiler stays because the JSON catalogue is
  // compiled in the browser rather than precompiled by @intlify/unplugin-vue-i18n.
  define: {
    __VUE_I18N_FULL_INSTALL__: true,
    __VUE_I18N_LEGACY_API__: false,
    __INTLIFY_DROP_MESSAGE_COMPILER__: false,
    __INTLIFY_PROD_DEVTOOLS__: false,
    __APP_BUILD_ID__: JSON.stringify(BUILD_ID)
  },
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
