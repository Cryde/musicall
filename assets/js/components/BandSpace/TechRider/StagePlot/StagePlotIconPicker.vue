<template>
  <section class="flex flex-col gap-2 min-w-0" :aria-label="t('band_space_tech_rider.icon_picker.label')">
    <h4 class="font-semibold text-sm">{{ t('band_space_tech_rider.icon_picker.title') }}</h4>

    <InputText
      v-model="query"
      class="w-full"
      size="small"
      :placeholder="t('band_space_tech_rider.icon_picker.search_placeholder')"
      :aria-label="t('band_space_tech_rider.icon_picker.search_label')"
    />

    <div class="flex flex-wrap gap-1">
      <Button
        v-for="category in categories"
        :key="category.value ?? 'all'"
        :label="category.label"
        size="small"
        :severity="activeCategory === category.value ? 'primary' : 'secondary'"
        :outlined="activeCategory !== category.value"
        text
        :aria-pressed="activeCategory === category.value"
        @click="activeCategory = category.value"
      />
    </div>

    <p v-if="visibleIcons.length === 0" class="text-xs text-surface-600 dark:text-surface-300 py-2">
      {{ t('band_space_tech_rider.icon_picker.no_match') }}
    </p>

    <ul v-else class="grid grid-cols-3 gap-1 max-h-72 overflow-y-auto pr-1">
      <li v-for="icon in visibleIcons" :key="icon.slug">
        <!-- Both paths on one control: drag it, or focus it and press Enter. The keyboard path is
             not a fallback, it is the only way to place an icon without a pointer. -->
        <button
          type="button"
          class="w-full flex flex-col items-center gap-1 p-1 rounded border border-surface-200 dark:border-surface-700 hover:bg-surface-100 dark:hover:bg-surface-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
          :draggable="!readOnly"
          :disabled="readOnly"
          :aria-label="t('band_space_tech_rider.icon_picker.place', { icon: icon.label })"
          v-tooltip.top="icon.label"
          @dragstart="handleDragStart($event, icon)"
          @click="emit('place', icon.slug)"
        >
          <StagePlotIcon
            :slug="icon.slug"
            :image-url="icon.image_url"
            :colour="icon.category_colour"
            class="w-8 h-8"
          />
          <span class="text-[10px] leading-tight text-center line-clamp-2">{{ icon.label }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { iconCategoryLabel } from '../../../../constants/stagePlot.js'
import StagePlotIcon from './StagePlotIcon.vue'

const props = defineProps({
  icons: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['place'])

const { t } = useI18n()

const query = ref('')
const activeCategory = ref(null)

/**
 * Derived from the catalogue rather than hardcoded, so an icon in a new category appears without a
 * second edit here (its tab reads the raw value until constants/stagePlot.js names it). Null is the
 * "all" tab.
 */
const categories = computed(() => {
  const seen = new Set(props.icons.map((icon) => icon.category))

  return [
    { value: null, label: t('band_space_tech_rider.icon_picker.all') },
    ...[...seen].map((value) => ({ value, label: iconCategoryLabel(value) }))
  ]
})

const visibleIcons = computed(() => {
  const needle = query.value.trim().toLowerCase()

  return props.icons.filter((icon) => {
    if (activeCategory.value !== null && icon.category !== activeCategory.value) return false
    if (needle === '') return true

    // Matched on the slug too, so someone who knows the stored value finds it as easily as
    // someone reading the French label.
    return icon.label.toLowerCase().includes(needle) || icon.slug.includes(needle)
  })
})

function handleDragStart(event, icon) {
  if (props.readOnly) return
  event.dataTransfer.setData('text/plain', icon.slug)
  event.dataTransfer.effectAllowed = 'copy'
}
</script>
