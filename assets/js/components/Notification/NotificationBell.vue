<template>
  <div>
    <button
      type="button"
      class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
      :aria-label="store.unreadCount > 0 ? `Notifications (${store.unreadCount} non lues)` : 'Notifications'"
      aria-haspopup="dialog"
      :aria-expanded="isPopoverOpen"
      @click="toggle"
    >
      <OverlayBadge v-if="store.unreadCount > 0" :value="badgeValue" severity="danger" size="small">
        <i class="pi pi-bell text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
      </OverlayBadge>
      <i v-else class="pi pi-bell text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
    </button>

    <Popover
      ref="popover"
      :pt="{ root: { 'aria-label': 'Notifications' } }"
      @show="onPopoverShow"
      @hide="isPopoverOpen = false"
    >
      <div class="w-80 sm:w-96">
        <div class="flex items-center justify-between gap-2 px-2 pb-3">
          <span class="text-lg font-semibold">Notifications</span>
          <Button
            label="Tout marquer comme lu"
            link
            size="small"
            :disabled="store.unreadCount === 0"
            @click="store.markAllRead()"
          />
        </div>

        <div
          class="flex gap-1 border-b border-surface px-2 pb-2 mb-1"
          role="tablist"
          aria-label="Filtrer les notifications"
        >
          <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.value"
            class="px-3 py-1 text-sm rounded-md transition-colors"
            :class="
              activeTab === tab.value
                ? 'bg-surface-100 dark:bg-surface-800 font-semibold'
                : 'text-surface-500 hover:text-surface-700 dark:hover:text-surface-200'
            "
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="max-h-96 overflow-y-auto">
          <div v-if="store.isLoading" class="py-8 text-center text-sm text-surface-500">
            Chargement...
          </div>
          <div v-else-if="visibleItems.length === 0" class="py-8 text-center text-sm text-surface-500">
            <p>{{ activeTab === 'unread' ? 'Aucune notification non lue' : 'Aucune notification' }}</p>
          </div>
          <NotificationItem
            v-for="notification in visibleItems"
            :key="notification.id"
            :notification="notification"
            @navigate="onItemNavigate"
          />
        </div>

        <div
          v-if="store.items.length > 0"
          class="border-t border-surface px-2 pt-2 mt-1 text-center"
        >
          <RouterLink
            :to="{ name: 'app_notifications_index' }"
            class="text-sm text-primary hover:underline"
            @click="onItemNavigate"
          >
            Voir toutes les notifications
          </RouterLink>
        </div>
      </div>
    </Popover>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import OverlayBadge from 'primevue/overlaybadge'
import Popover from 'primevue/popover'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserNotificationStore } from '../../store/notification/userNotification.js'
import NotificationItem from './NotificationItem.vue'

// A fallback, not the mechanism: the count arrives over Mercure now (#950). This only has to cover
// a hub that is down or a stream that has not reconnected yet, so it is minutes rather than the
// minute it used to be. The focus listener below still gives an immediate refresh on tab switch.
const POLL_INTERVAL_MS = 5 * 60_000

const emit = defineEmits(['navigate'])

const store = useUserNotificationStore()
const notificationStore = useNotificationStore()

const popover = ref(null)
const isPopoverOpen = ref(false)
const activeTab = ref('unread')

const tabs = [
  { value: 'unread', label: 'Non lues' },
  { value: 'all', label: 'Toutes' }
]

const badgeValue = computed(() => (store.unreadCount > 99 ? '99+' : store.unreadCount))

// Tabs filter the loaded feed (last 20) client-side; `unreadCount` (the badge) is the
// server total, so with >20 unread the badge can exceed the "Non lues" count until the
// next poll reconciles - an accepted tradeoff for a dropdown (the full list is #719).
const visibleItems = computed(() =>
  activeTab.value === 'unread' ? store.items.filter((n) => n.read_datetime === null) : store.items
)

function toggle(event) {
  popover.value?.toggle(event)
}

function onPopoverShow() {
  isPopoverOpen.value = true
  store.loadFeed()
}

function onItemNavigate() {
  popover.value?.hide()
  emit('navigate')
}

let intervalId = null

function refreshCount() {
  store.loadCount()
  // The other header counters ride this timer rather than running one of their own: the direct
  // message envelope, and the per Band Space chat badge the sidebar reads (#962). Both are live over
  // Mercure now, direct messages since #989 and a Band Space channel since #963, so this is the same
  // fallback as the count above: a hub that is down, or a stream that has not reconnected yet.
  notificationStore.loadNotifications()
  // The self-heal. The store connects as soon as the profile lands, but if that fetch failed there is
  // nothing else that would ever try again, and the poll would mask it perfectly: a live-looking bell
  // that is only ever five minutes fresh. connect() is idempotent, so this costs nothing when the
  // stream is already up.
  store.connect()
}

onMounted(() => {
  store.loadCount()
  // The store has already tried by the time this runs: creating it above ran its immediate watch.
  // This is here for the case where the profile had not landed then, alongside refreshCount() below.
  // Deliberately never closed on unmount, because a layout switch unmounts this component and the
  // stream should outlive that.
  store.connect()
  intervalId = setInterval(refreshCount, POLL_INTERVAL_MS)
  window.addEventListener('focus', refreshCount)
})

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId)
  }
  window.removeEventListener('focus', refreshCount)
})
</script>
