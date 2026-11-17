<template>
  <!-- Loading state -->
  <div v-if="isLoading" class="flex justify-center py-12">
    <ProgressSpinner />
  </div>

  <!-- Private profile state -->
  <PrivateProfileNotice v-else-if="isPrivate" />

  <!-- Not found state -->
  <div v-else-if="notFound" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
    <i class="pi pi-user-minus text-4xl text-surface-400 mb-4" />
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
      {{ notFoundTitle }}
    </h2>
    <p class="text-surface-500 dark:text-surface-400 mb-4">
      {{ notFoundHelp }}
    </p>
    <Button
      :label="t('profile.common.view_profile')"
      icon="pi pi-user"
      severity="info"
      @click="$router.push({ name: 'app_user_public_profile', params: { username } })"
    />
  </div>

  <slot v-else />
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { useI18n } from 'vue-i18n'
import PrivateProfileNotice from '../../User/Profile/PrivateProfileNotice.vue'

/** What a service page shows before it has its service (#1410): loading, a private profile, or nothing found. */
defineProps({
  isLoading: { type: Boolean, required: true },
  isPrivate: { type: Boolean, required: true },
  notFound: { type: Boolean, required: true },
  notFoundTitle: { type: String, required: true },
  notFoundHelp: { type: String, required: true },
  /** The member whose page this is, for « Voir le profil ». */
  username: { type: String, required: true }
})

const { t } = useI18n()
</script>
