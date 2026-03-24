<template>
  <div class="flex flex-col items-center justify-center min-h-[400px] text-center p-4 sm:p-8">
    <i class="pi pi-wallet text-4xl sm:text-6xl text-surface-300 dark:text-surface-600 mb-6"></i>

    <h2 class="text-xl font-semibold text-surface-700 dark:text-surface-200 mb-2">
      Aucune catégorie de finances configurée
    </h2>

    <p class="text-surface-500 dark:text-surface-400 mb-6 max-w-md">
      Veux-tu partir d'une structure suggérée ? Tu pourras la personnaliser ensuite.
    </p>

    <Button
      label="Créer les catégories par défaut"
      icon="pi pi-sparkles"
      :loading="financeStore.isBootstrapping"
      @click="handleBootstrap"
    />

    <button
      class="mt-4 text-sm text-primary hover:underline"
      :disabled="financeStore.isBootstrapping"
      @click="emit('create-manual')"
    >
      Ou crée ta première catégorie manuellement
    </button>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { useRoute } from 'vue-router'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'

const route = useRoute()
const toast = useToast()
const financeStore = useBandSpaceFinanceStore()

const emit = defineEmits(['bootstrapped', 'create-manual'])

const bandSpaceId = route.params.id

async function handleBootstrap() {
  try {
    await financeStore.bootstrap(bandSpaceId)
    emit('bootstrapped')
    toast.add({ severity: 'success', summary: 'Catégories créées', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer les catégories', life: 5000 })
  }
}
</script>
