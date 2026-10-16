<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('profile.settings.privacy.title') }}
    </h2>

    <div class="flex flex-col gap-2">
      <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
        {{ t('profile.settings.privacy.blocked_title') }}
      </h3>
      <div class="border-b border-surface-200 dark:border-surface-700" />
      <p class="text-sm text-surface-600 dark:text-surface-400">
        {{ t('profile.settings.privacy.blocked_help') }}
      </p>

      <div v-if="loadError" class="py-4">
        <Message severity="error" :closable="false">{{ loadError }}</Message>
      </div>

      <div v-else-if="!userBlockStore.isLoaded" class="flex justify-center py-8">
        <i class="pi pi-spin pi-spinner text-2xl" :aria-label="t('profile.settings.privacy.loading')" />
      </div>

      <p
        v-else-if="userBlockStore.blocks.length === 0"
        class="py-4 text-surface-600 dark:text-surface-400"
      >
        {{ t('profile.settings.privacy.blocked_empty') }}
      </p>

      <ul v-else class="flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
        <li v-for="block in userBlockStore.blocks" :key="block.user_id" class="flex items-center gap-3 py-3">
          <Avatar
            v-if="block.profile_picture_url"
            :image="block.profile_picture_url"
            :pt="{ image: { alt: t('profile.common.photo_of', { name: block.display_name }) } }"
            shape="circle"
          />
          <Avatar
            v-else
            :label="block.display_name.charAt(0).toUpperCase()"
            :style="getAvatarStyle(block.username)"
            shape="circle"
            role="img"
            :aria-label="t('profile.common.avatar_of', { name: block.display_name })"
          />
          <div class="flex flex-col min-w-0 flex-1">
            <span class="font-medium text-surface-900 dark:text-surface-0 truncate">{{ block.display_name }}</span>
            <span class="text-sm text-surface-600 dark:text-surface-400 truncate">
              {{ t('profile.settings.privacy.blocked_on', { username: `@${block.username}`, date: formatDate(block.creation_datetime) }) }}
            </span>
          </div>
          <Button
            :label="t('profile.block.unblock')"
            icon="pi pi-unlock"
            size="small"
            severity="secondary"
            outlined
            :loading="unblockingId === block.user_id"
            :disabled="unblockingId !== null"
            :aria-label="t('profile.block.unblock_user', { username: block.username })"
            @click="handleUnblock(block)"
          />
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { dateFnsLocale } from '../../../i18n/formatLocale.js'
import { useUserBlockStore } from '../../../store/user/block.js'
import { getAvatarStyle } from '../../../utils/avatar.js'

const userBlockStore = useUserBlockStore()
const toast = useToast()
const { t } = useI18n()
const loadError = ref('')
const unblockingId = ref(null)

function formatDate(value) {
  return format(parseISO(value), 'd MMMM yyyy', { locale: dateFnsLocale() })
}

onMounted(async () => {
  try {
    await userBlockStore.loadBlocks()
  } catch {
    loadError.value = t('profile.settings.privacy.load_error')
  }
})

async function handleUnblock(block) {
  unblockingId.value = block.user_id
  try {
    await userBlockStore.unblockUser(block.user_id)
    toast.add({
      severity: 'success',
      summary: t('profile.block.unblocked_toast', { username: block.username }),
      life: 3000
    })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('profile.block.error_summary'),
      detail: e?.response?.data?.detail || t('profile.block.error_detail'),
      life: 4000
    })
  } finally {
    unblockingId.value = null
  }
}
</script>
