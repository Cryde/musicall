<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="header"
    v-bind="sheetDialog"
    @show="name = initialName"
  >
    <!-- AutoComplete keeps Enter to itself: caught on the way down, it saves unless an option is highlighted. -->
    <form class="flex flex-col gap-2" @submit.prevent="submit" @keydown.enter.capture="submitUnlessPicking">
      <label :for="inputId" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        {{ t('band_space_files.song_folders.name_label') }}
      </label>
      <!-- Free text, with the names the space already uses offered first so « Stems » is spelled alike. -->
      <AutoComplete
        v-model="name"
        :input-id="inputId"
        :suggestions="options"
        :maxlength="NAME_MAX_LENGTH"
        complete-on-focus
        :show-empty-message="false"
        fluid
        :pt="{ pcInputText: { root: { autofocus: true } } }"
        :invalid="!!error"
        @complete="options = nameOptions($event.query)"
      />
      <Message v-if="error" severity="error" size="small" variant="simple">{{ error }}</Message>
    </form>
    <template #footer>
      <Button :label="t('band_space_files.common.cancel')" severity="secondary" text @click="visible = false" />
      <Button :label="submitLabel" :loading="saving" :disabled="!name.trim()" @click="submit" />
    </template>
  </Dialog>
</template>

<script setup>
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSheetDialog } from '../../../composables/useSheetDialog.js'
import { songFolderNameOptions } from '../../../utils/songFileFolders.js'

/** Names a folder of a song's files (#1451), new or renamed. The caller saves it and says what failed. */
const props = defineProps({
  header: { type: String, required: true },
  submitLabel: { type: String, required: true },
  initialName: { type: String, default: '' },
  /** Every song folder name of the space, offered first. */
  usedNames: { type: Array, default: () => [] },
  suggestedNames: { type: Array, default: () => [] },
  /** Names the folder's siblings already have, never offered. */
  siblingNames: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: null }
})

const emit = defineEmits(['submit'])
const visible = defineModel('visible', { type: Boolean, default: false })

// SongFileFolder::NAME_MAX_LENGTH.
const NAME_MAX_LENGTH = 100

const { t } = useI18n()
const sheetDialog = useSheetDialog('28rem')
const inputId = useId()
const name = ref('')
const options = ref([])
function submitUnlessPicking(event) {
  // An option highlighted with the arrows is AutoComplete's to pick: the input points at it.
  if (event.target.getAttribute('aria-activedescendant')) return
  event.preventDefault()
  submit()
}

function nameOptions(query) {
  return songFolderNameOptions(props.usedNames, props.suggestedNames, props.siblingNames, query)
}

function submit() {
  const trimmed = name.value.trim()
  if (!trimmed || props.saving) return
  emit('submit', trimmed)
}
</script>
