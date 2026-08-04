<template>
  <section class="flex flex-col gap-2 min-w-0" aria-label="Légende">
    <div class="flex items-center gap-2">
      <h4 class="font-semibold text-sm">Légende</h4>
      <span class="text-xs text-surface-600 dark:text-surface-300">
        {{ legend.length }} / {{ MAX_LEGEND_ENTRIES }}
      </span>
    </div>

    <!-- What makes a plot readable by somebody who was not in the room, which is the whole point of
         sending it to a venue. -->
    <p v-if="legend.length === 0" class="text-xs text-surface-600 dark:text-surface-300">
      Associez une icône à un mot pour que la salle comprenne le plan sans explication.
    </p>

    <ul v-else class="flex flex-col gap-1">
      <li v-for="(entry, index) in legend" :key="`${entry.icon}-${index}`" class="flex items-center gap-1">
        <img :src="iconImage(entry.icon)" :alt="''" class="w-6 h-6 object-contain shrink-0" />
        <InputText
          :model-value="entry.label ?? ''"
          :disabled="readOnly"
          :maxlength="MAX_LABEL_LENGTH"
          class="w-full"
          size="small"
          :aria-label="`Libellé de légende pour ${iconLabel(entry.icon)}`"
          @update:model-value="(value) => emit('update', { index, label: value })"
        />
        <Button
          v-if="!readOnly"
          icon="pi pi-trash"
          severity="danger"
          text
          rounded
          size="small"
          :aria-label="`Retirer ${iconLabel(entry.icon)} de la légende`"
          v-tooltip.top="'Retirer'"
          @click="emit('remove', index)"
        />
      </li>
    </ul>

    <div v-if="!readOnly && legend.length < MAX_LEGEND_ENTRIES" class="flex gap-1">
      <Select
        v-model="pendingIcon"
        :options="icons"
        option-label="label"
        option-value="slug"
        filter
        class="w-full"
        size="small"
        placeholder="Ajouter une icône"
        aria-label="Icône à ajouter à la légende"
        empty-filter-message="Aucune icône trouvée"
      >
        <template #option="{ option }">
          <span class="flex items-center gap-2">
            <img :src="option.image_url" :alt="''" class="w-5 h-5 object-contain" />
            <span>{{ option.label }}</span>
          </span>
        </template>
      </Select>
      <Button
        label="Ajouter"
        icon="pi pi-plus"
        severity="secondary"
        outlined
        size="small"
        :disabled="!pendingIcon"
        @click="addPending"
      />
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { ref } from 'vue'
import { MAX_LABEL_LENGTH, MAX_LEGEND_ENTRIES } from '../../../../constants/stagePlot.js'

const props = defineProps({
  legend: { type: Array, required: true },
  icons: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['add', 'update', 'remove'])

const pendingIcon = ref(null)

function iconFor(slug) {
  return props.icons.find((icon) => icon.slug === slug) ?? null
}

function iconImage(slug) {
  return iconFor(slug)?.image_url ?? ''
}

function iconLabel(slug) {
  return iconFor(slug)?.label ?? slug
}

function addPending() {
  if (!pendingIcon.value) return
  // Seeded with the icon's own French name, so a legend entry reads correctly before anybody
  // types anything.
  emit('add', { icon: pendingIcon.value, label: iconLabel(pendingIcon.value) })
  pendingIcon.value = null
}
</script>
