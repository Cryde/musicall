<template>
  <li>
    <MobileMenuItem
      :to="{ name: 'app_messages' }"
      icon="pi pi-envelope"
      :aria-label="notificationStore.unreadMessages > 0 ? t('user_menu.messages_unread', { count: notificationStore.unreadMessages }) : undefined"
    >
      {{ t('user_menu.messages') }}
      <template v-if="notificationStore.unreadMessages > 0" #end>
        <Badge :value="notificationStore.unreadMessages" severity="danger" class="ml-auto" aria-hidden="true" />
      </template>
    </MobileMenuItem>
  </li>
  <li><MobileMenuNotificationsItem /></li>
</template>

<script setup>
import Badge from 'primevue/badge'
import { defineAsyncComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '../../store/notification/notification.js'
import MobileMenuItem from './MobileMenuItem.vue'

// Its store is the bell's, which a visitor's bundle does not carry.
const MobileMenuNotificationsItem = defineAsyncComponent(
  () => import('../Notification/MobileMenuNotificationsItem.vue')
)

/** A member's messages and notifications in the phone menu, each with its unread count. */
const { t } = useI18n()
const notificationStore = useNotificationStore()
</script>
