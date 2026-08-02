<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Choisir un fichier"
    :style="{ width: '40rem' }"
    @show="reset"
  >
    <div class="flex flex-col gap-3">
      <p class="text-sm text-surface-600 dark:text-surface-300">
        Seuls les images et les PDF de vos fichiers peuvent servir de page. Importez le fichier
        depuis le module Fichiers s'il n'apparaît pas ici.
      </p>

      <InputText v-model="query" placeholder="Rechercher un fichier..." class="w-full" @input="debouncedLoad" />

      <div v-if="isLoading" class="py-8 flex justify-center">
        <ProgressSpinner style="width: 2rem; height: 2rem" />
      </div>

      <Message v-else-if="loadError" severity="error" :closable="false">{{ loadError }}</Message>

      <p v-else-if="files.length === 0" class="py-6 text-center text-surface-600 dark:text-surface-300">
        Aucune image ni PDF dans les fichiers de cet espace.
      </p>

      <ul v-else class="flex flex-col gap-1 max-h-80 overflow-y-auto">
        <li v-for="file in files" :key="file.id">
          <button
            type="button"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left hover:bg-surface-100 dark:hover:bg-surface-800 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-500"
            @click="choose(file.id)"
          >
            <i :class="['pi', iconFor(file), 'text-surface-500 shrink-0']" aria-hidden="true" />
            <span class="flex-1 min-w-0 truncate">{{ file.original_name }}</span>
            <span class="text-xs text-surface-500 shrink-0">{{ file.mime_type }}</span>
          </button>
        </li>
      </ul>
    </div>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { onBeforeUnmount, ref } from 'vue'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['choose'])
const visible = defineModel('visible', { type: Boolean, default: false })

const files = ref([])
const query = ref('')
const isLoading = ref(false)
const loadError = ref(null)

let searchTimeout = null
let requestId = 0

/**
 * The server has no "renderable as a page" filter, and adding one for a picker would put a
 * rider concern in the files API. Fetching the two families it does filter on and merging is
 * two small requests against an endpoint that is already paginated.
 */
async function load() {
  const currentRequest = ++requestId
  isLoading.value = true
  loadError.value = null

  try {
    const [images, pdfs] = await Promise.all([
      bandSpaceFilesApi.getFiles(props.bandSpaceId, {
        mime: 'image',
        query: query.value || undefined
      }),
      bandSpaceFilesApi.getFiles(props.bandSpaceId, {
        mime: 'application/pdf',
        query: query.value || undefined
      })
    ])
    if (currentRequest !== requestId) return
    files.value = [...(images.member ?? []), ...(pdfs.member ?? [])].sort((a, b) =>
      a.original_name.localeCompare(b.original_name)
    )
  } catch (e) {
    if (currentRequest !== requestId) return
    loadError.value = e.message
    files.value = []
  } finally {
    if (currentRequest === requestId) {
      isLoading.value = false
    }
  }
}

/** Reopening for a different item should not inherit the last search. */
function reset() {
  query.value = ''
  load()
}

function debouncedLoad() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 300)
}

onBeforeUnmount(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

function iconFor(file) {
  return (file.mime_type ?? '').startsWith('image/') ? 'pi-image' : 'pi-file-pdf'
}

function choose(fileId) {
  emit('choose', fileId)
  visible.value = false
}
</script>
