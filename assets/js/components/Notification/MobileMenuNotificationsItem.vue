<template>
  <MobileMenuItem
    :to="{ name: 'app_notifications_index' }"
    icon="pi pi-bell"
    :aria-label="store.unreadCount > 0 ? t('notification.bell.aria_label_unread', { count: store.unreadCount }) : undefined"
  >
    {{ t('notification.title') }}
    <template v-if="store.unreadCount > 0" #end>
      <Badge :value="store.unreadCount" severity="danger" class="ml-auto" aria-hidden="true" />
    </template>
  </MobileMenuItem>
</template>

<script setup>
import Badge from 'primevue/badge'
import { useI18n } from 'vue-i18n'
import { useUserNotificationStore } from '../../store/notification/userNotification.js'
import MobileMenuItem from '../Global/MobileMenuItem.vue'

/** The phone menu's notifications line, apart so its store stays out of the bundle a visitor downloads. */
const { t } = useI18n()
const store = useUserNotificationStore()
</script>
