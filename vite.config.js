import { mkdir, readFile, rm, stat, writeFile } from 'node:fs/promises'
import { join } from 'node:path'
import { alphaTab } from '@coderline/alphatab-vite'
import { PrimeVueResolver } from '@primevue/auto-import-resolver'
import tailwindcss from '@tailwindcss/vite'
import vuePlugin from '@vitejs/plugin-vue'
import Components from 'unplugin-vue-components/vite'
import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'
import {
  GENERAL_USER_DIR,
  GENERAL_USER_MANIFEST,
  GENERAL_USER_SOURCE,
  TAB_ASSETS_DIR
} from './assets/js/constants/tabReader.js'
import { splitSoundFont } from './assets/js/utils/soundFontSplit.js'
import { DRUM_BANK, MELODIC_BANK } from './assets/js/utils/tabSounds.js'

/**
 * GeneralUser GS split into one SoundFont per instrument, so the tab reader downloads a song's few
 * instruments (about 5 MB) instead of the 31 MB bank. Only the main bank and the drum kits ship: the
 * other banks are variations Guitar Pro files do not ask for. Rewritten only when the source changes,
 * told by its size and date rather than by reading 31 MB on every start.
 */
function generalUserSoundFont() {
  const output = join('public', TAB_ASSETS_DIR, GENERAL_USER_DIR)
  const manifestPath = join(output, GENERAL_USER_MANIFEST)
  return {
    name: 'musicall-generaluser-split',
    async buildStart() {
      const { size, mtimeMs } = await stat(GENERAL_USER_SOURCE)
      const previous = await readFile(manifestPath, 'utf8')
        .then(JSON.parse)
        .catch(() => null)
      if (previous?.source?.size === size && previous.source.mtimeMs === mtimeMs) return

      const presets = splitSoundFont(new Uint8Array(await readFile(GENERAL_USER_SOURCE)), {
        keep: (bank) => bank === MELODIC_BANK || bank === DRUM_BANK
      })
      await rm(output, { recursive: true, force: true })
      await mkdir(output, { recursive: true })
      const files = presets.map((preset) => ({
        ...preset,
        file: `${preset.bank}-${preset.program}.sf2`
      }))
      await Promise.all(files.map((preset) => writeFile(join(output, preset.file), preset.bytes)))
      const manifest = {
        source: { size, mtimeMs },
        presets: files.map(({ bank, program, name, file, bytes }) => ({
          bank,
          program,
          name,
          file,
          size: bytes.length
        }))
      }
      await writeFile(manifestPath, JSON.stringify(manifest))
    }
  }
}

export default defineConfig({
  plugins: [
    vuePlugin(),
    symfonyPlugin(),
    // vite-plugin-symfony turns Vite's public folder off, so alphaTab is told where to copy its music
    // font: it lands next to the build in every release (gitignored).
    alphaTab({ assetOutputDir: join('public', TAB_ASSETS_DIR) }),
    generalUserSoundFont(),
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
    __INTLIFY_PROD_DEVTOOLS__: false
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
