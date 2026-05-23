<template>
  <Dialog
    v-model:visible="visible"
    header="Ajouter un titre"
    modal
    :style="{ width: '32rem' }"
    @hide="reset"
  >
    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="song">Chanson du répertoire</Tab>
        <Tab value="other">Autre</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="song">
          <div class="flex flex-col gap-4 pt-2">
            <div>
              <label class="block text-sm font-medium mb-1">Chanson</label>
              <AutoComplete
                v-model="songPick"
                :suggestions="songSuggestions"
                option-label="title"
                placeholder="Rechercher dans le répertoire…"
                class="w-full"
                input-class="w-full"
                force-selection
                @complete="filterSongs"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Durée surchargée (s, optionnel)</label>
              <InputNumber
                v-model="songOverrideDuration"
                :min="1"
                :max="86400"
                :useGrouping="false"
                placeholder="Sinon, durée de référence de la chanson"
                class="w-full"
                inputClass="w-full"
              />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <Button label="Annuler" severity="secondary" text @click="visible = false" />
              <Button
                label="Ajouter"
                :disabled="!songPick"
                :loading="isSubmitting"
                @click="submitSong"
              />
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
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
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
const songPick = ref(null)
const songSuggestions = ref([])
const songOverrideDuration = ref(null)

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

watch(visible, (isOpen) => {
  if (isOpen) reset()
})

function reset() {
  activeTab.value = 'song'
  songPick.value = null
  songSuggestions.value = []
  songOverrideDuration.value = null
  otherType.value = 'talk'
  otherLabel.value = ''
  otherDuration.value = null
  labelError.value = null
}

function filterSongs(event) {
  const q = event.query.trim().toLowerCase()
  const pool = songsStore.songs
  songSuggestions.value = q
    ? pool.filter((s) => s.title.toLowerCase().includes(q))
    : [...pool].slice(0, 20)
}

async function submitSong() {
  if (!songPick.value) return
  isSubmitting.value = true
  try {
    await setlistsStore.addItem(props.bandSpaceId, props.setlistId, {
      type: 'song',
      song_id: songPick.value.id,
      duration_override: songOverrideDuration.value ?? null
    })
    toast.add({ severity: 'success', summary: 'Titre ajouté', life: 3000 })
    emit('added')
    visible.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
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
