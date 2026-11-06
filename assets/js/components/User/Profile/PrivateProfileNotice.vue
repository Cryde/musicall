<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
    <i class="pi pi-lock text-4xl text-surface-400 mb-4" aria-hidden="true" />
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
      {{ isOwner ? t('profile.public.private_owner_title') : t('profile.public.private_title') }}
    </h2>
    <p class="text-surface-500 dark:text-surface-400 mb-4">
      {{ isOwner ? t('profile.public.private_owner_help') : t('profile.public.private_help') }}
    </p>
    <Button
      v-if="isOwner"
      as="router-link"
      :to="{ name: 'app_user_settings_profile_privacy' }"
      :label="t('profile.public.make_public')"
      icon="pi pi-eye"
      severity="info"
    />
    <Button v-else as="router-link" :to="{ name: 'app_home' }" :label="t('profile.public.back_home')" icon="pi pi-home" severity="info" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { isSameUsername } from '../../../utils/profileError.js'

const props = defineProps({
  username: { type: String, required: true }
})

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()

// The API answers the owner's own private profile with the same 404, so the page says why instead.
const isOwner = computed(() => isSameUsername(userSecurityStore.user?.username, props.username))
</script>
