<template>
  <template v-if="currentSpaceId">
    <RouterLink
      v-for="item in NAVIGATION_ITEMS"
      :key="item.route"
      :to="{ name: item.route, params: { id: currentSpaceId } }"
      custom
      v-slot="{ isExactActive, href, navigate }"
    >
      <a
        :href="href"
        @click="(e) => { if (!disabled) navigate(e) }"
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
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { NAVIGATION_ITEMS } from '../../constants/bandSpace.js'

defineProps({
  disabled: {
    type: Boolean,
    default: false
  }
})

const { currentSpaceId } = useBandSpaceNavigation()
</script>
