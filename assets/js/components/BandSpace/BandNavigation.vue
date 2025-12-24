<template>
  <template v-if="currentSpaceId">
    <RouterLink
      v-for="(item, i) in navigationItems"
      :key="i"
      :to="item.to"
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
import { computed } from 'vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'

defineProps({
  disabled: {
    type: Boolean,
    default: false
  }
})

const { currentSpaceId } = useBandSpaceNavigation()

const navigationItems = computed(() => [
  {
    label: 'Dashboard',
    to: { name: BAND_SPACE_ROUTES.DASHBOARD, params: { id: currentSpaceId.value } }
  },
  {
    label: 'Agenda',
    to: { name: BAND_SPACE_ROUTES.AGENDA, params: { id: currentSpaceId.value } }
  },
  {
    label: 'Notes',
    to: { name: BAND_SPACE_ROUTES.NOTES, params: { id: currentSpaceId.value } }
  },
  {
    label: 'Social',
    to: { name: BAND_SPACE_ROUTES.SOCIAL, params: { id: currentSpaceId.value } }
  },
  {
    label: 'Fichiers',
    to: { name: BAND_SPACE_ROUTES.FILES, params: { id: currentSpaceId.value } }
  },
  {
    label: 'Paramètres',
    to: { name: BAND_SPACE_ROUTES.PARAMETERS, params: { id: currentSpaceId.value } }
  }
])
</script>
