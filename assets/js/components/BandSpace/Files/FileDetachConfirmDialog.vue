<template>
  <!-- A group of its own: the app-wide ConfirmDialog renders a plain string, this one adds the trash option. -->
  <ConfirmDialog :group="group" :style="{ width: '32rem' }" :breakpoints="{ '575px': '95vw' }">
    <template #message="{ message }">
      <div class="flex flex-col gap-4 w-full">
        <div class="flex items-start gap-4">
          <span :class="[message.icon, 'p-confirmdialog-icon mt-1']" aria-hidden="true"></span>
          <span class="whitespace-pre-line">{{ message.message }}</span>
        </div>
        <div v-if="trashOption.offered" class="flex items-center gap-2">
          <Checkbox v-model="alsoTrash" :input-id="checkboxId" binary />
          <label :for="checkboxId" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('band_space_files.detach_dialog.also_trash') }}
          </label>
        </div>
        <p
          v-else-if="trashOption.keptBy.length > 0"
          class="m-0 text-sm text-surface-500 dark:text-surface-400"
        >
          {{ t('band_space_files.detach_dialog.kept_by', { sources: describeFileSources(trashOption.keptBy) }) }}
        </p>
      </div>
    </template>
  </ConfirmDialog>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { describeFileSources } from '../../../constants/fileSources.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { detachTrashOption } from '../../../utils/detachTrashOption.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const { t } = useI18n()
const confirm = useConfirm()
const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()

// Per instance, so two surfaces mounted at once never both answer the same require().
const group = useId()
const checkboxId = useId()
const alsoTrash = ref(false)
const trashOption = ref({ offered: false, keptBy: [] })

/**
 * Asks before detaching `file` from `source`, with the trash option when the endpoint would accept it.
 * Unchecked by default, so a detach keeps the file in the library unless the member opts in.
 *
 * @param {object} request
 * @param {object} request.file the file row, with its `attachments` and `created_by`
 * @param {{type: string, id: string}} request.source what the file is being detached from
 * @param {(archive: boolean) => unknown} request.onDetach runs on confirm
 * Every other key (message, header, icon, labels) goes to confirm.require() as is.
 */
function open({ file, source, onDetach, ...options }) {
  alsoTrash.value = false
  trashOption.value = detachTrashOption(
    file,
    source,
    userSecurityStore.userProfile?.id ?? null,
    bandSpaceStore.getById(props.bandSpaceId)?.role === 'admin'
  )
  confirm.require({
    ...options,
    group,
    accept: () => onDetach(trashOption.value.offered && alsoTrash.value)
  })
}

defineExpose({ open })
</script>
