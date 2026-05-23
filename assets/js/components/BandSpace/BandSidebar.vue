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
          @click="(e) => handleClick(e, navigate)"
          :class="linkClasses(isExactActive)"
        >
          <i :class="['pi', item.icon, 'text-base shrink-0']" aria-hidden="true"></i>
          <span class="font-medium truncate">{{ item.label }}</span>
        </a>
      </RouterLink>
    </nav>

    <div v-if="settingsItem" class="mt-auto pt-3 border-t border-surface-200 dark:border-surface-700">
      <RouterLink
        :to="{ name: settingsItem.route, params: { id: currentSpaceId } }"
        custom
        v-slot="{ isExactActive, href, navigate }"
      >
        <a
          :href="href"
          @click="(e) => handleClick(e, navigate)"
          :class="linkClasses(isExactActive)"
        >
          <i :class="['pi', settingsItem.icon, 'text-base shrink-0']" aria-hidden="true"></i>
          <span class="font-medium truncate">{{ settingsItem.label }}</span>
        </a>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, NAVIGATION_ITEMS } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const props = defineProps({
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['navigate'])

const { currentSpaceId } = useBandSpaceNavigation()
const bandSpaceStore = useBandSpaceStore()

const visibleItems = computed(() => {
  const space = bandSpaceStore.getById(currentSpaceId.value)
  if (space?.role === 'admin') {
    return NAVIGATION_ITEMS
  }
  return NAVIGATION_ITEMS.filter((item) => item.route !== BAND_SPACE_ROUTES.PARAMETERS)
})

const workItems = computed(() =>
  visibleItems.value.filter((item) => item.route !== BAND_SPACE_ROUTES.PARAMETERS)
)

const settingsItem = computed(
  () => visibleItems.value.find((item) => item.route === BAND_SPACE_ROUTES.PARAMETERS) ?? null
)

function linkClasses(isActive) {
  return [
    'flex items-center gap-2 px-3 py-2 rounded-lg transition-colors duration-150',
    props.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
    isActive
      ? 'bg-primary text-primary-contrast'
      : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
  ]
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
