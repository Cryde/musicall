<template>
  <!-- A preview of the latest conversations rather than a link: looking marks nothing read. -->
  <button
    ref="envelope"
    type="button"
    class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
    :aria-label="notificationStore.unreadMessages > 0 ? t('user_menu.messages_unread', notificationStore.unreadMessages) : t('user_menu.messages')"
    aria-haspopup="dialog"
    :aria-expanded="isPreviewOpen"
    @click="togglePreview"
  >
    <OverlayBadge
      v-if="notificationStore.unreadMessages > 0"
      :value="notificationStore.unreadMessages > 99 ? '99+' : String(notificationStore.unreadMessages)"
      severity="danger"
      size="small"
    >
      <i class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
    </OverlayBadge>
    <i v-else class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
  </button>
  <InboxPreview v-if="hasOpenedPreview" v-model:open="isPreviewOpen" :anchor="envelope" />
  <NotificationBell />
  <!-- Admins only, and only while something is waiting: an always-on icon would be permanent
       clutter for the one or two people who see it. Replaces the email notification the
       feedback module would otherwise need. A phone has no room for a fourth icon, so there the
       phone menu lists it instead. -->
  <RouterLink
    v-if="userSecurityStore.isAdmin && notificationStore.newFeedbacks > 0"
    :to="{ name: 'admin_feedbacks_index' }"
    class="flex max-sm:hidden items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
    :aria-label="t('user_menu.feedbacks_pending', { count: notificationStore.newFeedbacks })"
  >
    <OverlayBadge :value="notificationStore.newFeedbacks" severity="warn" size="small">
      <i class="pi pi-comment text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
    </OverlayBadge>
  </RouterLink>
</template>

<script setup>
import OverlayBadge from 'primevue/overlaybadge'
import { defineAsyncComponent, onMounted, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'

// Rendered once signed in, so the bell and its notification machinery load on demand and anonymous
// visitors never download them.
const NotificationBell = defineAsyncComponent(() => import('../Notification/NotificationBell.vue'))
// The envelope's preview, with the message store, the same way: loaded on the first tap.
const InboxPreview = defineAsyncComponent(() => import('../Message/InboxPreview.vue'))

/** A member's inboxes in the header, on desktop and on a phone: messages, notifications, feedback. */
const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()

const envelope = useTemplateRef('envelope')
const hasOpenedPreview = ref(false)
const isPreviewOpen = ref(false)

function togglePreview() {
  hasOpenedPreview.value = true
  isPreviewOpen.value = !isPreviewOpen.value
}

onMounted(() => {
  if (userSecurityStore.isAuthenticated) {
    notificationStore.loadNotifications()
  }
})
</script>
