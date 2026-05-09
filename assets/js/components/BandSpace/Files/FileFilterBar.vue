<template>
  <div class="flex flex-wrap items-center gap-3">
    <IconField class="flex-1 min-w-[200px]">
      <InputIcon class="pi pi-search" />
      <InputText
        :model-value="filters.query"
        placeholder="Rechercher un fichier"
        size="small"
        class="w-full"
        @update:model-value="(v) => emit('update-filter', { key: 'query', value: v })"
      />
    </IconField>

    <Select
      :model-value="filters.tagId"
      :options="tagOptions"
      option-label="label"
      option-value="value"
      placeholder="Étiquette"
      size="small"
      :show-clear="true"
      class="min-w-[160px]"
      @update:model-value="(v) => emit('update-filter', { key: 'tagId', value: v })"
    />

    <Select
      :model-value="filters.mime"
      :options="mimeOptions"
      option-label="label"
      option-value="value"
      placeholder="Type"
      size="small"
      :show-clear="true"
      class="min-w-[140px]"
      @update:model-value="(v) => emit('update-filter', { key: 'mime', value: v })"
    />

    <Select
      :model-value="sortValue"
      :options="sortOptions"
      option-label="label"
      option-value="value"
      placeholder="Trier"
      size="small"
      class="min-w-[160px]"
      @update:model-value="handleSortChange"
    />
  </div>
</template>

<script setup>
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'

const props = defineProps({
  filters: { type: Object, required: true },
  tags: { type: Array, default: () => [] }
})

const emit = defineEmits(['update-filter'])

const tagOptions = computed(() => props.tags.map((t) => ({ label: t.name, value: t.id })))

const mimeOptions = [
  { label: 'Audio', value: 'audio/' },
  { label: 'Image', value: 'image/' },
  { label: 'PDF', value: 'application/pdf' },
  { label: 'Vidéo', value: 'video/' }
]

const sortOptions = [
  { label: 'Plus récents', value: 'date:desc' },
  { label: 'Plus anciens', value: 'date:asc' },
  { label: 'Nom (A→Z)', value: 'name:asc' },
  { label: 'Nom (Z→A)', value: 'name:desc' },
  { label: 'Taille (grand→petit)', value: 'size:desc' },
  { label: 'Taille (petit→grand)', value: 'size:asc' }
]

const sortValue = computed(() => `${props.filters.sort}:${props.filters.order}`)

function handleSortChange(value) {
  if (!value) return
  const [sort, order] = value.split(':')
  emit('update-filter', { key: 'sort', value: sort })
  emit('update-filter', { key: 'order', value: order })
}
</script>
