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
            Suppression programmée
          </h3>
          <p class="text-sm text-surface-600 dark:text-surface-300 mt-1">
            Cet espace et tous ses contenus seront définitivement supprimés le
            <strong>{{ formatDateLong(scheduledFor) }}</strong>. D'ici là, les membres peuvent encore
            récupérer leurs fichiers.
          </p>
        </div>
        <Button
          v-if="isAdmin"
          label="Restaurer"
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
            Supprimer ce Band Space
          </h3>
          <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
            L'espace, ses fichiers, son agenda, ses notes, ses tâches, ses setlists et ses finances
            seront supprimés après un délai de {{ GRACE_PERIOD_DAYS }} jours, pendant lequel un
            administrateur peut annuler la suppression.
          </p>
        </div>
        <Button
          label="Supprimer"
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
        Seul un administrateur peut supprimer cet espace. Vous pouvez le quitter depuis la section
        « Membres ».
      </p>
    </div>

    <Dialog
      v-model:visible="isDeleteDialogOpen"
      modal
      header="Supprimer ce Band Space ?"
      :style="{ width: '32rem' }"
      @hide="resetDialog"
    >
      <div class="flex flex-col gap-4">
        <p class="text-sm">
          Vous êtes sur le point de programmer la suppression de
          <span class="font-medium">« {{ currentSpace?.name }} »</span>.
        </p>

        <ul class="list-disc list-inside text-sm text-surface-600 dark:text-surface-300 space-y-1">
          <li>
            L'espace reste accessible {{ GRACE_PERIOD_DAYS }} jours, le temps que chacun récupère ses
            fichiers.
          </li>
          <li>Les autres membres sont prévenus immédiatement.</li>
          <li>Un administrateur peut annuler jusqu'à l'échéance.</li>
          <li>Passé ce délai, tout est supprimé définitivement et rien ne peut être récupéré.</li>
        </ul>

        <div class="flex flex-col gap-2">
          <label for="delete-confirmation" class="text-sm text-surface-700 dark:text-surface-200">
            Pour confirmer, tapez <strong>{{ CONFIRMATION_WORD }}</strong> ci-dessous.
          </label>
          <InputText
            id="delete-confirmation"
            v-model="confirmation"
            autocomplete="off"
            :placeholder="CONFIRMATION_WORD"
            @keyup.enter="handleDelete"
          />
        </div>

        <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
      </div>

      <template #footer>
        <Button
          label="Annuler"
          severity="secondary"
          text
          :disabled="settingsStore.isSchedulingDeletion"
          @click="isDeleteDialogOpen = false"
        />
        <Button
          label="Supprimer"
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
import { useRoute } from 'vue-router'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { formatDateLong } from '../../../utils/date.js'

// Mirrors BandSpaceDeleteProcessor::GRACE_PERIOD_DAYS; shown so the admin knows what they are agreeing
// to before the space reports its own scheduled date.
const GRACE_PERIOD_DAYS = 30

const CONFIRMATION_WORD = 'Supprimer'

const route = useRoute()
const toast = useToast()
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
  () => confirmation.value.trim().toLowerCase() === CONFIRMATION_WORD.toLowerCase()
)

async function handleDelete() {
  if (!isConfirmed.value) return

  globalError.value = null
  try {
    await settingsStore.scheduleDeletion(bandSpaceId)
    isDeleteDialogOpen.value = false
    toast.add({ severity: 'warn', summary: 'Suppression programmée', life: 4000 })
  } catch (e) {
    globalError.value = e.message
  }
}

async function handleRestore() {
  try {
    await settingsStore.restore(bandSpaceId)
    toast.add({ severity: 'success', summary: 'Suppression annulée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function resetDialog() {
  confirmation.value = ''
  globalError.value = null
}
</script>
