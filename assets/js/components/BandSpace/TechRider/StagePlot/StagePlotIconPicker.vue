<template>
  <section class="flex flex-col gap-2 min-w-0" aria-label="Icônes disponibles">
    <h4 class="font-semibold text-sm">Icônes</h4>

    <InputText
      v-model="query"
      class="w-full"
      size="small"
      placeholder="Rechercher..."
      aria-label="Rechercher une icône"
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
      Aucune icône ne correspond.
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
          :aria-label="`Placer ${icon.label} au centre de la scène`"
          v-tooltip.top="icon.label"
          @dragstart="handleDragStart($event, icon)"
          @click="emit('place', icon.slug)"
        >
          <img :src="icon.image_url" :alt="''" class="w-8 h-8 object-contain" />
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

const props = defineProps({
  icons: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['place'])

const query = ref('')
const activeCategory = ref(null)

/**
 * Derived from the catalogue rather than hardcoded, so an icon in a new category appears without a
 * second edit here. Null is the "all" tab.
 */
const categories = computed(() => {
  const seen = new Map()
  for (const icon of props.icons) {
    if (!seen.has(icon.category)) seen.set(icon.category, icon.category_label)
  }

  return [{ value: null, label: 'Tout' }, ...[...seen].map(([value, label]) => ({ value, label }))]
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
