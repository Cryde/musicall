<template>
  <Dialog
    v-model:visible="visible"
    header="Ajouter un titre"
    modal
    :style="{ width: '34rem' }"
    @hide="reset"
  >
    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="song">Chanson du répertoire</Tab>
        <Tab value="other">Autre</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="song">
          <div class="flex flex-col gap-3 pt-2">
            <div class="sticky top-0 bg-surface-0 dark:bg-surface-900 z-10 pb-2">
              <IconField iconPosition="left">
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="query"
                  placeholder="Rechercher dans le répertoire…"
                  class="w-full"
                />
              </IconField>
            </div>

            <ul
              v-if="filteredSongs.length > 0"
              class="list-none p-0 m-0 flex flex-col gap-1 max-h-[22rem] overflow-y-auto border border-surface-200 dark:border-surface-700 rounded-lg"
            >
              <li v-for="song in filteredSongs" :key="song.id">
                <label
                  :class="[
                    'flex items-center gap-3 px-3 py-2.5 cursor-pointer transition-colors duration-150',
                    selectedIds.has(song.id)
                      ? 'bg-primary/10 dark:bg-primary/20'
                      : 'hover:bg-surface-100 dark:hover:bg-surface-800'
                  ]"
                >
                  <Checkbox
                    :modelValue="selectedIds.has(song.id)"
                    :binary="true"
                    @update:modelValue="(v) => toggle(song.id, v)"
                  />
                  <div class="flex-1 min-w-0">
                    <div class="font-medium truncate flex items-center gap-2">
                      {{ song.title }}
                      <span
                        v-if="alreadyAttachedIds.has(song.id)"
                        class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-surface-100 dark:bg-surface-800 text-surface-500"
                      >
                        déjà présent
                      </span>
                    </div>
                    <div class="text-xs text-surface-500 flex items-center gap-2 mt-0.5">
                      <span v-if="song.tonality">{{ song.tonality }}</span>
                      <span v-if="song.tempo">·&nbsp;{{ song.tempo }} BPM</span>
                      <span v-if="song.reference_duration">
                        ·&nbsp;{{ formatDuration(song.reference_duration) }}
                      </span>
                    </div>
                  </div>
                </label>
              </li>
            </ul>
            <div
              v-else
              class="border border-dashed border-surface-200 dark:border-surface-700 rounded-lg p-6 text-center text-surface-500 text-sm"
            >
              {{
                query
                  ? 'Aucun titre ne correspond à votre recherche.'
                  : 'Le répertoire est vide. Ajoutez des titres depuis Répertoire.'
              }}
            </div>

            <div class="flex justify-between items-center gap-2 pt-2">
              <div class="text-xs text-surface-500">
                <span v-if="selectedIds.size > 0">
                  {{ selectedIds.size }} titre{{ selectedIds.size > 1 ? 's' : '' }} sélectionné{{ selectedIds.size > 1 ? 's' : '' }}
                </span>
              </div>
              <div class="flex gap-2">
                <Button label="Annuler" severity="secondary" text @click="visible = false" />
                <Button
                  :label="submitLabel"
                  :disabled="selectedIds.size === 0"
                  :loading="isSubmitting"
                  @click="submitSongs"
                />
              </div>
            </div>
          </div>
        </TabPanel>
        <TabPanel value="other">
          <div class="flex flex-col gap-4 pt-2">
            <div>
              <label class="block text-sm font-medium mb-1">Type</label>
              <SelectButton
                v-model="otherType"
                :options="otherTypeOptions"
                option-label="label"
                option-value="value"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Libellé <span class="text-red-500">*</span></label>
              <InputText
                v-model="otherLabel"
                placeholder="ex. Présentation du groupe"
                class="w-full"
                :invalid="!!labelError"
              />
              <small v-if="labelError" class="text-red-500">{{ labelError }}</small>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Durée (s, optionnel)</label>
              <InputNumber
                v-model="otherDuration"
                :min="1"
                :max="86400"
                :useGrouping="false"
                class="w-full"
                inputClass="w-full"
              />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <Button label="Annuler" severity="secondary" text @click="visible = false" />
              <Button label="Ajouter" :loading="isSubmitting" @click="submitOther" />
            </div>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true }
})

const emit = defineEmits(['added'])
const visible = defineModel('visible', { type: Boolean, default: false })

const setlistsStore = useBandSetlistsStore()
const songsStore = useBandSongsStore()
const toast = useToast()

const activeTab = ref('song')

// Multi-select state: Set of ids + ordered array preserving selection order.
const selectedIds = ref(new Set())
const selectionOrder = ref([])
const query = ref('')

const otherType = ref('talk')
const otherLabel = ref('')
const otherDuration = ref(null)
const labelError = ref(null)

const otherTypeOptions = [
  { label: 'Interlude', value: 'interlude' },
  { label: 'Pause', value: 'break' },
  { label: 'MC', value: 'talk' }
]

const isSubmitting = ref(false)

// Songs already attached to the active setlist — used to show a "déjà
// présent" hint. Not a hard block: users may legitimately want to add
// the same song twice (encore, double feature, etc.).
const alreadyAttachedIds = computed(() => {
  const setlist = setlistsStore.activeSetlist
  if (!setlist || setlist.id !== props.setlistId) return new Set()
  return new Set(setlist.items.filter((i) => i.type === 'song' && i.song?.id).map((i) => i.song.id))
})

const sortedSongs = computed(() =>
  [...songsStore.songs].sort((a, b) =>
    a.title.localeCompare(b.title, 'fr', { sensitivity: 'base' })
  )
)

const filteredSongs = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return sortedSongs.value
  return sortedSongs.value.filter((s) => s.title.toLowerCase().includes(q))
})

const submitLabel = computed(() => {
  const n = selectedIds.value.size
  if (n <= 1) return 'Ajouter'
  return `Ajouter ${n} titres`
})

function toggle(songId, checked) {
  const next = new Set(selectedIds.value)
  if (checked) {
    next.add(songId)
    if (!selectionOrder.value.includes(songId))
      selectionOrder.value = [...selectionOrder.value, songId]
  } else {
    next.delete(songId)
    selectionOrder.value = selectionOrder.value.filter((id) => id !== songId)
  }
  selectedIds.value = next
}

function formatDuration(seconds) {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}′${String(s).padStart(2, '0')}″`
}

watch(visible, (isOpen) => {
  if (isOpen) reset()
})

function reset() {
  activeTab.value = 'song'
  selectedIds.value = new Set()
  selectionOrder.value = []
  query.value = ''
  otherType.value = 'talk'
  otherLabel.value = ''
  otherDuration.value = null
  labelError.value = null
}

async function submitSongs() {
  if (selectedIds.value.size === 0) return
  isSubmitting.value = true
  const ordered = selectionOrder.value.filter((id) => selectedIds.value.has(id))
  const failures = []
  try {
    for (const songId of ordered) {
      try {
        await setlistsStore.addItem(props.bandSpaceId, props.setlistId, {
          type: 'song',
          song_id: songId
        })
      } catch (e) {
        failures.push({ songId, message: e.message })
      }
    }
    const added = ordered.length - failures.length
    if (added > 0) {
      toast.add({
        severity: 'success',
        summary: added === 1 ? 'Titre ajouté' : `${added} titres ajoutés`,
        life: 3000
      })
    }
    if (failures.length > 0) {
      toast.add({
        severity: 'warn',
        summary:
          failures.length === 1
            ? "1 titre n'a pas pu être ajouté"
            : `${failures.length} titres n'ont pas pu être ajoutés`,
        detail: failures[0].message,
        life: 5000
      })
    }
    emit('added')
    visible.value = false
  } finally {
    isSubmitting.value = false
  }
}

async function submitOther() {
  labelError.value = null
  const trimmed = otherLabel.value.trim()
  if (!trimmed) {
    labelError.value = 'Veuillez spécifier un libellé'
    return
  }
  isSubmitting.value = true
  try {
    await setlistsStore.addItem(props.bandSpaceId, props.setlistId, {
      type: otherType.value,
      label: trimmed,
      duration_override: otherDuration.value ?? null
    })
    toast.add({ severity: 'success', summary: 'Élément ajouté', life: 3000 })
    emit('added')
    visible.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isSubmitting.value = false
  }
}
</script>
