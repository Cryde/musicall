<template>
  <div class="flex-auto py-6 lg:py-8 px-8 lg:px-20">
    <div class="flex flex-col items-center justify-center py-20">
      <i class="pi pi-users text-6xl text-surface-400 mb-6"></i>
      <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
        Aucun Band Space
      </h2>
      <p class="text-surface-600 dark:text-surface-400 mb-6 text-center max-w-md">
        Vous n'avez pas encore de Band Space. Créez-en un pour commencer à gérer votre groupe.
      </p>
      <Button
        label="Créer un Band Space"
        icon="pi pi-plus"
        @click="bandSpaceStore.openCreateModal()"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const bandSpaceStore = useBandSpaceStore()
</script>
