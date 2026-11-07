<template>
  <div class="flex flex-col gap-3">
    <Button type="button" :label="label" icon="pi pi-plus" size="large" class="w-full" @click="emit('create')" />
    <span class="text-sm text-center text-surface-600 dark:text-surface-400">
      {{ t('home.band_space_presentation.form.prefill_hint') }}
    </span>
    <i18n-t
      v-if="!userSecurityStore.isAuthenticated"
      keypath="home.band_space_presentation.form.have_account"
      tag="span"
      class="text-sm text-center text-surface-600 dark:text-surface-400"
    >
      <template #login>
        <RouterLink :to="{ name: 'app_login' }" class="font-semibold text-primary hover:underline">{{ t('common.login') }}</RouterLink>
      </template>
    </i18n-t>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../../store/user/security.js'

/** The create button with what it promises; under the form on a wide screen, under the demo on a phone. */
defineProps({
  label: { type: String, required: true }
})

const emit = defineEmits(['create'])

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
</script>
