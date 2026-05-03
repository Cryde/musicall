<template>
  <template v-if="currentSpaceId">
    <RouterLink
      v-for="item in visibleItems"
      :key="item.route"
      :to="{ name: item.route, params: { id: currentSpaceId } }"
      custom
      v-slot="{ isExactActive, href, navigate }"
    >
      <a
        :href="href"
        @click="(e) => { if (!disabled) { navigate(e); emit('navigate') } }"
        :class="[
          'flex items-center gap-2 p-2 rounded-lg transition-colors duration-150 border w-full lg:w-auto',
          disabled
            ? 'cursor-not-allowed opacity-50'
            : 'cursor-pointer',
          isExactActive
            ? 'bg-surface-100 dark:bg-surface-800 border-surface-200 dark:border-surface-700'
            : 'border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-200 dark:hover:border-surface-700'
        ]"
      >
        <span class="font-medium">{{ item.label }}</span>
      </a>
    </RouterLink>
  </template>
</template>

<script setup>
import { computed } from 'vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, NAVIGATION_ITEMS } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

defineProps({
  disabled: {
    type: Boolean,
    default: false
  }
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
</script>
