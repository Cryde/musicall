<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
    <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4">
      Nom du Band Space
    </h3>

    <!-- Rename form, admin only: the server refuses a rename sent by a plain member -->
    <form v-if="isAdmin" @submit.prevent="handleRename" class="flex flex-col gap-3 sm:flex-row">
      <div class="flex-1 min-w-0">
        <label for="band-space-name" class="sr-only">Nom du Band Space</label>
        <InputText
          id="band-space-name"
          v-model="name"
          class="w-full"
          maxlength="255"
          autocomplete="off"
          placeholder="Nom du Band Space"
          :invalid="Boolean(renameError)"
          :disabled="bandSpaceStore.isRenaming"
        />
        <small class="text-surface-500 dark:text-surface-400 mt-2 block">
          Ce nom apparaît dans le menu, dans les notifications et sur les tech riders.
        </small>
      </div>
      <Button
        type="submit"
        label="Enregistrer"
        icon="pi pi-check"
        class="shrink-0 self-start"
        :loading="bandSpaceStore.isRenaming"
        :disabled="!canSubmit"
      />
    </form>

    <p v-else class="text-surface-700 dark:text-surface-200">
      {{ currentSpace?.name }}
      <span class="text-sm text-surface-500 dark:text-surface-400 block mt-2">
        Seul un administrateur peut renommer cet espace.
      </span>
    </p>

    <Message v-if="renameError" severity="error" :closable="false" class="mt-3">
      {{ renameError }}
    </Message>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'

const toast = useToast()
const bandSpaceStore = useBandSpaceStore()
const { currentSpaceId, currentSpace, isAdmin } = useBandSpaceNavigation()

const name = ref(currentSpace.value?.name ?? '')
const renameError = ref(null)

// The space list is loaded by the band layout, so the name can still be missing on first render, and
// it also changes under us when another tab renames the space or the user switches space.
watch(
  () => currentSpace.value?.name,
  (spaceName) => {
    name.value = spaceName ?? ''
    renameError.value = null
  }
)

const canSubmit = computed(() => {
  const trimmed = name.value.trim()

  return trimmed.length > 0 && trimmed !== currentSpace.value?.name
})

async function handleRename() {
  if (!canSubmit.value) return

  renameError.value = null

  try {
    await bandSpaceStore.renameBandSpace(currentSpaceId.value, name.value.trim())
    toast.add({ severity: 'success', summary: 'Band Space renommé', life: 3000 })
  } catch (e) {
    renameError.value = e.message
  }
}
</script>
