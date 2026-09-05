<template>
  <div v-if="currentSpaceId" class="flex flex-col h-full p-3 gap-1">
    <nav
      class="flex flex-col gap-1 flex-1 min-h-0 overflow-y-auto"
      aria-label="Modules du Band Space"
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
          :aria-label="item.label"
          @click="(e) => handleClick(e, navigate)"
          :class="linkClasses(isExactActive)"
          v-tooltip.right="tooltipFor(item.label)"
        >
          <i :class="['pi', item.icon, 'text-base shrink-0']" aria-hidden="true"></i>
          <span v-if="!collapsed" class="font-medium truncate">{{ item.label }}</span>
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
        aria-label="Envoyer un retour"
        v-tooltip.right="tooltipFor('Un retour ?')"
        @click="handleFeedbackClick"
      >
        <i class="pi pi-comment text-base shrink-0" aria-hidden="true"></i>
        <span v-if="!collapsed" class="font-medium truncate">Un retour ?</span>
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
      :aria-label="collapsed ? 'Étendre le menu' : 'Réduire le menu'"
      v-tooltip.right="collapsed ? 'Étendre le menu' : null"
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
import { computed } from 'vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, NAVIGATION_ITEMS } from '../../constants/bandSpace.js'
import { useFeedbackStore } from '../../store/feedback.js'
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

function handleFeedbackClick() {
  // Closes the mobile drawer the same way a navigation does, otherwise the drawer sits on top of
  // the panel that just opened.
  emit('navigate')
  feedbackStore.openDrawer()
}

// `superAdminOnly` marks a module that is merged but not yet announced and is dropped when it is
// released. Membership role is deliberately not a gate: « Paramètres » is where a member quits the
// space and sets their stage name and instruments, so every member gets the entry, and the page
// itself hides whatever is admin only.
const visibleItems = computed(() =>
  NAVIGATION_ITEMS.filter((item) => !item.superAdminOnly || userSecurityStore.isSuperAdmin)
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
