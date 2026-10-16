<template>
  <div class="flex flex-col items-center justify-center min-h-[400px] text-center p-4 sm:p-8">
    <i class="pi pi-wallet text-4xl sm:text-6xl text-surface-300 dark:text-surface-600 mb-6"></i>

    <h2 class="text-xl font-semibold text-surface-700 dark:text-surface-200 mb-2">
      {{ t('band_space_finance.bootstrap.title') }}
    </h2>

    <p class="text-surface-500 dark:text-surface-400 mb-6 max-w-md">
      {{ t('band_space_finance.bootstrap.intro') }}
    </p>

    <Button
      :label="t('band_space_finance.bootstrap.create_defaults')"
      icon="pi pi-sparkles"
      :loading="financeStore.isBootstrapping"
      @click="handleBootstrap"
    />

    <button
      class="mt-4 text-sm text-primary hover:underline"
      :disabled="financeStore.isBootstrapping"
      @click="emit('create-manual')"
    >
      {{ t('band_space_finance.bootstrap.create_manually') }}
    </button>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const financeStore = useBandSpaceFinanceStore()

const emit = defineEmits(['bootstrapped', 'create-manual'])

const bandSpaceId = route.params.id

async function handleBootstrap() {
  try {
    await financeStore.bootstrap(bandSpaceId)
    emit('bootstrapped')
    toast.add({
      severity: 'success',
      summary: t('band_space_finance.bootstrap.created'),
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: t('band_space_finance.common.error'),
      detail: t('band_space_finance.bootstrap.create_error'),
      life: 5000
    })
  }
}
</script>
