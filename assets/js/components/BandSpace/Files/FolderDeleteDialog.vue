<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_files.folder_delete.title')"
    :style="{ width: '32rem' }"
    @hide="resetState"
  >
    <div class="flex flex-col gap-4">
      <i18n-t keypath="band_space_files.folder_delete.intro" tag="p" class="text-sm">
        <template #folder>
          <span class="font-medium">
            {{ t('band_space_files.folder_delete.quoted_name', { name: folder?.name }) }}
          </span>
        </template>
      </i18n-t>

      <div class="flex flex-col gap-3">
        <label class="flex items-start gap-3 cursor-pointer">
          <RadioButton v-model="strategy" value="move_to_root" name="folder-delete-strategy" />
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-medium">
              {{ t('band_space_files.folder_delete.move_to_root') }}
            </span>
            <span class="text-xs text-surface-500">
              {{ t('band_space_files.folder_delete.move_to_root_help') }}
            </span>
          </div>
        </label>

        <label
          v-if="isAdmin"
          class="flex items-start gap-3 cursor-pointer"
        >
          <RadioButton v-model="strategy" value="cascade" name="folder-delete-strategy" />
          <div class="flex flex-col gap-1">
            <span class="text-sm font-medium text-red-600">
              {{ t('band_space_files.folder_delete.cascade') }}
            </span>
            <span class="text-xs text-surface-500">
              {{
                t('band_space_files.folder_delete.cascade_help', {
                  days: filesStore.trashRetentionDays
                })
              }}
            </span>
            <ul class="text-xs text-surface-500 list-disc pl-4">
              <li>
                {{ t('band_space_files.folder_delete.cascade_shares') }}
              </li>
              <li>
                {{
                  t('band_space_files.folder_delete.cascade_attached', {
                    sources: fileSourceListLabel()
                  })
                }}
              </li>
              <li>
                {{ t('band_space_files.folder_delete.cascade_subfolders') }}
              </li>
            </ul>
          </div>
        </label>
      </div>

      <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
    </div>

    <template #footer>
      <Button
        :label="t('band_space_files.common.cancel')"
        severity="secondary"
        text
        :disabled="isSubmitting"
        @click="visible = false"
      />
      <Button
        :label="t('band_space_files.common.delete')"
        severity="danger"
        :loading="isSubmitting"
        @click="handleConfirm"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { fileSourceListLabel } from '../../../constants/fileSources.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  folder: { type: Object, default: null },
  isAdmin: { type: Boolean, default: false }
})

const emit = defineEmits(['deleted'])
const { t } = useI18n()

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const strategy = ref('move_to_root')
const isSubmitting = ref(false)
const globalError = ref(null)

watch(visible, (open) => {
  if (open) {
    strategy.value = 'move_to_root'
    globalError.value = null
  }
})

async function handleConfirm() {
  if (!props.folder) return
  isSubmitting.value = true
  globalError.value = null
  try {
    await filesStore.deleteFolder(props.bandSpaceId, props.folder.id, { strategy: strategy.value })
    emit('deleted', props.folder.id)
    visible.value = false
  } catch (e) {
    globalError.value = e.message
  } finally {
    isSubmitting.value = false
  }
}

function resetState() {
  strategy.value = 'move_to_root'
  isSubmitting.value = false
  globalError.value = null
}
</script>
