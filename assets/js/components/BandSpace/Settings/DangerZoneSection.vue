<template>
  <div class="flex flex-col gap-6">
    <!-- Pending deletion: only an admin can call it off -->
    <div
      v-if="scheduledFor"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6 border border-amber-500/40"
    >
      <div class="flex items-start gap-3">
        <i class="pi pi-exclamation-triangle text-amber-600 dark:text-amber-400 mt-1" aria-hidden="true" />
        <div class="flex-1 min-w-0">
          <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100">
            {{ t('band_space.danger.scheduled_title') }}
          </h3>
          <i18n-t keypath="band_space.danger.scheduled_text" tag="p" class="text-sm text-surface-600 dark:text-surface-300 mt-1">
            <template #date>
              <strong>{{ formatDateLong(scheduledFor) }}</strong>
            </template>
          </i18n-t>
        </div>
        <Button
          v-if="isAdmin"
          :label="t('band_space.danger.restore')"
          icon="pi pi-undo"
          outlined
          class="shrink-0"
          :loading="settingsStore.isRestoring"
          @click="handleRestore"
        />
      </div>
    </div>

    <!-- Delete the space -->
    <div v-if="isAdmin && !scheduledFor" class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
          <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100">
            {{ t('band_space.danger.delete_title') }}
          </h3>
          <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
            {{ t('band_space.danger.delete_text', { days: GRACE_PERIOD_DAYS }) }}
          </p>
        </div>
        <Button
          :label="t('band_space.danger.delete')"
          icon="pi pi-trash"
          severity="danger"
          outlined
          class="shrink-0 self-start sm:self-auto"
          @click="isDeleteDialogOpen = true"
        />
      </div>
    </div>

    <div
      v-else-if="!isAdmin && !scheduledFor"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6"
    >
      <p class="text-sm text-surface-500 dark:text-surface-400">
        {{ t('band_space.danger.admin_only') }}
      </p>
    </div>

    <Dialog
      v-model:visible="isDeleteDialogOpen"
      modal
      :header="t('band_space.danger.dialog_header')"
      :style="{ width: '32rem' }"
      @hide="resetDialog"
    >
      <div class="flex flex-col gap-4">
        <i18n-t keypath="band_space.danger.dialog_intro" tag="p" class="text-sm">
          <template #name>
            <span class="font-medium">{{ t('band_space.danger.quoted_name', { name: currentSpace?.name }) }}</span>
          </template>
        </i18n-t>

        <ul class="list-disc list-inside text-sm text-surface-600 dark:text-surface-300 space-y-1">
          <li>
            {{ t('band_space.danger.dialog_grace', { days: GRACE_PERIOD_DAYS }) }}
          </li>
          <li>{{ t('band_space.danger.dialog_members_notified') }}</li>
          <li>{{ t('band_space.danger.dialog_admin_can_cancel') }}</li>
          <li>{{ t('band_space.danger.dialog_permanent') }}</li>
        </ul>

        <div class="flex flex-col gap-2">
          <i18n-t keypath="band_space.danger.confirm_label" tag="label" for="delete-confirmation" class="text-sm text-surface-700 dark:text-surface-200">
            <template #word>
              <strong>{{ confirmationWord }}</strong>
            </template>
          </i18n-t>
          <InputText
            id="delete-confirmation"
            v-model="confirmation"
            autocomplete="off"
            :placeholder="confirmationWord"
            @keyup.enter="handleDelete"
          />
        </div>

        <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
      </div>

      <template #footer>
        <Button
          :label="t('band_space.common.cancel')"
          severity="secondary"
          text
          :disabled="settingsStore.isSchedulingDeletion"
          @click="isDeleteDialogOpen = false"
        />
        <Button
          :label="t('band_space.danger.delete')"
          severity="danger"
          :disabled="!isConfirmed"
          :loading="settingsStore.isSchedulingDeletion"
          @click="handleDelete"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { formatDateLong } from '../../../utils/date.js'

// Mirrors BandSpaceDeleteProcessor::GRACE_PERIOD_DAYS; shown so the admin knows what they are agreeing
// to before the space reports its own scheduled date.
const GRACE_PERIOD_DAYS = 30

const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const confirmationWord = computed(() => t('band_space.danger.confirmation_word'))
const settingsStore = useBandSpaceSettingsStore()
const { currentSpace } = useBandSpaceNavigation()

const bandSpaceId = route.params.id

const isDeleteDialogOpen = ref(false)
const confirmation = ref('')
const globalError = ref(null)

const isAdmin = computed(() => currentSpace.value?.role === 'admin')
const scheduledFor = computed(() => currentSpace.value?.deletion_scheduled_datetime ?? null)

// Case-insensitive so a stray capital does not stand between an admin and their own space, but the word
// still has to be typed out: that deliberate keystroke is the point of the confirmation.
const isConfirmed = computed(
  () => confirmation.value.trim().toLowerCase() === confirmationWord.value.toLowerCase()
)

async function handleDelete() {
  if (!isConfirmed.value) return

  globalError.value = null
  try {
    await settingsStore.scheduleDeletion(bandSpaceId)
    isDeleteDialogOpen.value = false
    toast.add({ severity: 'warn', summary: t('band_space.danger.scheduled_title'), life: 4000 })
  } catch (e) {
    globalError.value = e.message
  }
}

async function handleRestore() {
  try {
    await settingsStore.restore(bandSpaceId)
    toast.add({ severity: 'success', summary: t('band_space.danger.restored'), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function resetDialog() {
  confirmation.value = ''
  globalError.value = null
}
</script>
