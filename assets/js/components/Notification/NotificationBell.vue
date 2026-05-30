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
            <Button
              v-if="activeTab === 'unread' && store.items.length > 0"
              label="Voir toutes les notifications"
              link
              size="small"
              class="mt-1"
              @click="activeTab = 'all'"
            />
          </div>
          <NotificationItem
            v-for="notification in visibleItems"
            :key="notification.id"
            :notification="notification"
            @navigate="onItemNavigate"
          />
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
import { useUserNotificationStore } from '../../store/notification/userNotification.js'
import NotificationItem from './NotificationItem.vue'

const POLL_INTERVAL_MS = 60_000

const emit = defineEmits(['navigate'])

const store = useUserNotificationStore()

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
}

onMounted(() => {
  store.loadCount()
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
