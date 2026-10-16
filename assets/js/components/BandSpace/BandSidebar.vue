<template>
  <div v-if="currentSpaceId" class="flex flex-col h-full p-3 gap-1">
    <nav
      class="flex flex-col gap-1 flex-1 min-h-0 overflow-y-auto"
      :aria-label="t('band_space.sidebar.modules_aria')"
    >
      <RouterLink
        v-for="item in workItems"
        :key="item.route"
        :to="{ name: item.route, params: { id: currentSpaceId } }"
        custom
        v-slot="{ isExactActive, href, navigate }"
      >
        <a
          :href="href"
          :aria-current="isExactActive ? 'page' : undefined"
          :aria-label="ariaLabelFor(item)"
          @click="(e) => handleClick(e, navigate)"
          :class="linkClasses(isExactActive)"
          v-tooltip.right="tooltipFor(item.label)"
        >
          <i :class="['pi', item.icon, 'text-base shrink-0']" aria-hidden="true"></i>
          <!-- The badge is pulled into the row's right padding: « Discussion » is the longest label
               that carries one, and at 11rem it needs those few pixels back to avoid ellipsising. -->
          <span v-if="!collapsed" class="font-medium truncate">{{ item.label }}</span>
          <Badge
            v-if="unreadFor(item) > 0"
            :value="unreadLabel(unreadFor(item))"
            severity="danger"
            size="small"
            :class="collapsed ? 'ml-0' : 'ml-auto shrink-0 -mr-1.5'"
          />
        </a>
      </RouterLink>

      <slot name="after-work" />
    </nav>

    <div
      v-if="settingsItem"
      class="mt-auto pt-3 border-t border-surface-200 dark:border-surface-700"
    >
      <slot name="above-settings" />
      <!-- Inside the component rather than passed through the slot above, because AppBandLayout
           renders this sidebar twice (the desktop aside and the mobile drawer) and only the mobile
           one fills slots. -->
      <button
        type="button"
        :disabled="disabled"
        :class="linkClasses(false)"
        :aria-label="t('band_space.sidebar.send_feedback')"
        v-tooltip.right="tooltipFor(t('band_space.sidebar.feedback'))"
        @click="handleFeedbackClick"
      >
        <i class="pi pi-comment text-base shrink-0" aria-hidden="true"></i>
        <span v-if="!collapsed" class="font-medium truncate">{{ t('band_space.sidebar.feedback') }}</span>
      </button>
      <RouterLink
        :to="{ name: settingsItem.route, params: { id: currentSpaceId } }"
        custom
        v-slot="{ isExactActive, href, navigate }"
      >
        <a
          :href="href"
          :aria-current="isExactActive ? 'page' : undefined"
          :aria-label="settingsItem.label"
          @click="(e) => handleClick(e, navigate)"
          :class="linkClasses(isExactActive)"
          v-tooltip.right="tooltipFor(settingsItem.label)"
        >
          <i :class="['pi', settingsItem.icon, 'text-base shrink-0']" aria-hidden="true"></i>
          <span v-if="!collapsed" class="font-medium truncate">{{ settingsItem.label }}</span>
        </a>
      </RouterLink>
    </div>

    <button
      v-if="showToggle"
      type="button"
      :class="[
        'flex items-center mt-2 px-3 py-2 rounded-lg transition-colors duration-150 text-surface-500 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800',
        collapsed ? 'justify-center' : 'justify-end'
      ]"
      :aria-label="collapsed ? t('band_space.sidebar.expand') : t('band_space.sidebar.collapse')"
      v-tooltip.right="collapsed ? t('band_space.sidebar.expand') : null"
      @click="collapsed = !collapsed"
    >
      <i
        :class="['pi', collapsed ? 'pi-angle-double-right' : 'pi-angle-double-left', 'text-sm']"
        aria-hidden="true"
      />
    </button>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, NAVIGATION_ITEMS } from '../../constants/bandSpace.js'
import { useFeedbackStore } from '../../store/feedback.js'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  showToggle: { type: Boolean, default: false }
})

const collapsed = defineModel('collapsed', { type: Boolean, default: false })

const emit = defineEmits(['navigate'])

const { currentSpaceId } = useBandSpaceNavigation()
const userSecurityStore = useUserSecurityStore()
const feedbackStore = useFeedbackStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

/**
 * Only the chat carries a count today. The map is keyed by band space, so the number is this space's
 * and never a sum across the bands a member belongs to.
 */
function unreadFor(item) {
  return item.route === BAND_SPACE_ROUTES.CHAT
    ? notificationStore.chatUnreadFor(currentSpaceId.value)
    : 0
}

// Capped like the notification bell and the message inbox, so a long-ignored conversation cannot
// stretch the rail.
function unreadLabel(count) {
  return count > 99 ? '99+' : String(count)
}

function ariaLabelFor(item) {
  const unread = unreadFor(item)

  return unread > 0
    ? t('band_space.sidebar.unread_aria', { label: item.label, unread })
    : item.label
}

function handleFeedbackClick() {
  // Closes the mobile drawer the same way a navigation does, otherwise the drawer sits on top of
  // the panel that just opened.
  emit('navigate')
  feedbackStore.openDrawer()
}

// `testerOnly` marks a module that is merged but not yet announced and is dropped when it is
// released. Membership role is deliberately not a gate: « Paramètres » is where a member quits the
// space and sets their stage name and instruments, so every member gets the entry, and the page
// itself hides whatever is admin only.
const visibleItems = computed(() =>
  NAVIGATION_ITEMS.filter((item) => !item.testerOnly || userSecurityStore.isTester)
)

const workItems = computed(() =>
  visibleItems.value.filter((item) => item.route !== BAND_SPACE_ROUTES.PARAMETERS)
)

const settingsItem = computed(
  () => visibleItems.value.find((item) => item.route === BAND_SPACE_ROUTES.PARAMETERS) ?? null
)

function linkClasses(isActive) {
  return [
    'flex items-center gap-2 px-3 py-2 rounded-lg transition-colors duration-150',
    collapsed.value ? 'justify-center' : '',
    props.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
    isActive
      ? 'bg-primary text-primary-contrast'
      : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
  ]
}

// Tooltip only shows when collapsed (labels are hidden) — passing null
// suppresses the tooltip in expanded mode.
function tooltipFor(label) {
  return collapsed.value ? label : null
}

function handleClick(event, navigate) {
  if (props.disabled) {
    event.preventDefault()
    return
  }
  navigate(event)
  emit('navigate')
}
</script>
