<template>
  <Dialog v-model:visible="visible" modal header="Nom de scène et instruments" :style="{ width: '30rem' }">
    <form v-if="member" class="flex flex-col gap-4" @submit.prevent="handleSave">
      <div>
        <label :for="`${uid}-stage-name`" class="block text-sm font-medium mb-1">Nom de scène</label>
        <InputText
          :id="`${uid}-stage-name`"
          v-model="stageName"
          class="w-full"
          maxlength="60"
          :placeholder="member.username"
          autofocus
        />
        <small class="text-surface-600 dark:text-surface-300">
          Le nom imprimé sur les documents envoyés aux salles. Sans nom de scène, c'est
          « {{ member.username }} » qui apparaîtra.
        </small>
      </div>

      <div>
        <label :for="`${uid}-instruments`" class="block text-sm font-medium mb-1">Instruments</label>
        <MultiSelect
          :id="`${uid}-instruments`"
          v-model="instrumentIds"
          :options="instruments"
          option-label="name"
          option-value="id"
          :loading="isLoadingInstruments"
          :selection-limit="MAX_INSTRUMENTS"
          filter
          display="chip"
          class="w-full"
          placeholder="Choisir un ou plusieurs instruments"
          empty-message="Aucun instrument"
          empty-filter-message="Aucun instrument trouvé"
        />
        <small class="text-surface-600 dark:text-surface-300">
          {{ MAX_INSTRUMENTS }} au maximum. Ils apparaissent à côté du nom sur le tech rider.
        </small>
      </div>

      <Message v-if="error" severity="error" :closable="false" size="small">{{ error }}</Message>

      <div class="flex justify-end gap-2">
        <Button label="Annuler" severity="secondary" text type="button" @click="visible = false" />
        <Button label="Enregistrer" type="submit" :loading="isSaving" />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import { ref, useId, watch } from 'vue'
import instrumentApi from '../../../api/attribute/instrument.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  member: { type: Object, default: null }
})

const emit = defineEmits(['saved'])
const visible = defineModel('visible', { type: Boolean, default: false })

/** Mirrors the Assert\Count on BandSpaceMemberProfile. */
const MAX_INSTRUMENTS = 6

const settingsStore = useBandSpaceSettingsStore()

const stageName = ref('')
const instrumentIds = ref([])
const instruments = ref([])
const isLoadingInstruments = ref(false)
const isSaving = ref(false)
const error = ref(null)

// Fetched once and kept, because the catalogue is the same for every member and the dialog is
// opened repeatedly while filling in a line-up.
async function loadInstruments() {
  if (instruments.value.length > 0) return
  isLoadingInstruments.value = true
  try {
    instruments.value = await instrumentApi.listInstrument()
  } catch (e) {
    error.value = e.message
  } finally {
    isLoadingInstruments.value = false
  }
}

// Reseeded from the member each time the dialog opens, so editing one person and then another
// never carries the first one's values across.
watch(visible, (open) => {
  if (!open) return
  error.value = null
  stageName.value = props.member?.stage_name ?? ''
  instrumentIds.value = (props.member?.instruments ?? []).map((instrument) => instrument.id)
  loadInstruments()
})

const uid = useId()

async function handleSave() {
  if (!props.member) return

  error.value = null
  isSaving.value = true
  try {
    await settingsStore.updateMemberProfile(props.bandSpaceId, props.member.id, {
      stageName: stageName.value.trim() === '' ? null : stageName.value.trim(),
      instrumentIds: instrumentIds.value
    })
    emit('saved')
    visible.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    isSaving.value = false
  }
}
</script>
