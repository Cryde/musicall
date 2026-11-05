<template>
  <nav
    class="sticky top-16 z-20 -mx-6 flex items-stretch border-b border-surface-300 bg-surface-200 dark:border-surface-700 dark:bg-surface-950"
    :aria-label="t('band_space.settings.phone.sections_nav')"
  >
    <div class="relative min-w-0 flex-1">
      <div ref="strip" class="flex overflow-x-auto pr-10 pl-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <button
          v-for="section in sections"
          :key="section.key"
          :ref="(element) => registerTab(section.key, element)"
          type="button"
          class="shrink-0 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition-colors"
          :class="
            section.key === active
              ? 'border-primary text-primary-700 dark:text-primary-300'
              : 'border-transparent text-surface-600 dark:text-surface-300'
          "
          :aria-current="section.key === active ? 'true' : undefined"
          @click="choose(section.key)"
        >
          {{ section.label }}
        </button>
      </div>
      <!-- Says more tabs follow past the edge. -->
      <span
        class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-surface-200 dark:from-surface-950"
        aria-hidden="true"
      />
    </div>
    <button
      type="button"
      class="mr-2 flex w-11 shrink-0 items-center justify-center text-surface-600 dark:text-surface-300"
      :aria-label="t('band_space.settings.phone.all_sections')"
      aria-haspopup="dialog"
      @click="isSheetOpen = true"
    >
      <i class="pi pi-chevron-down" aria-hidden="true" />
    </button>

    <BottomSheet v-model:visible="isSheetOpen" :title="t('band_space.settings.phone.title')">
      <button
        v-for="section in sections"
        :key="section.key"
        type="button"
        :class="SHEET_CHOICE"
        :aria-current="section.key === active ? 'true' : undefined"
        @click="choose(section.key)"
      >
        <i class="pi w-5 text-center text-surface-600 dark:text-surface-400" :class="section.icon" aria-hidden="true" />
        <span class="flex-1">{{ section.label }}</span>
        <i v-if="section.key === active" class="pi pi-check text-primary" aria-hidden="true" />
      </button>
    </BottomSheet>
  </nav>
</template>

<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { SHEET_CHOICE } from '../../../constants/phoneControls.js'
import BottomSheet from '../../Global/BottomSheet.vue'

/**
 * The settings sections on a phone: an underlined strip that scrolls under the site header, and a
 * « ⌄ » listing them all, so the sections hidden past the edge are still one tap away.
 */
defineProps({
  // [{ key, label, icon }]
  sections: { type: Array, required: true }
})

const active = defineModel({ type: String, default: null })
const { t } = useI18n()

const isSheetOpen = ref(false)
const strip = ref(null)
const tabs = new Map()

function registerTab(key, element) {
  if (element) tabs.set(key, element)
  else tabs.delete(key)
}

// From further down the page a new section would start above the screen: back to the top of it.
function choose(key) {
  active.value = key
  isSheetOpen.value = false
  window.scrollTo({ top: 0 })
}

// The current tab is brought into the strip, so a section chosen from the sheet or a deep link is
// never one hidden past the edge.
async function revealActiveTab() {
  await nextTick()
  const tab = tabs.get(active.value)
  if (!tab || !strip.value) return
  const left = tab.offsetLeft - (strip.value.clientWidth - tab.offsetWidth) / 2
  strip.value.scrollTo({ left: Math.max(0, left), behavior: 'smooth' })
}

watch(active, revealActiveTab)
onMounted(revealActiveTab)
</script>
