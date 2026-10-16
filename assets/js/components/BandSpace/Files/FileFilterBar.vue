<template>
  <div class="flex flex-wrap items-center gap-3">
    <IconField class="flex-1 min-w-[200px]">
      <InputIcon class="pi pi-search" />
      <InputText
        :model-value="filters.query"
        :placeholder="t('band_space_files.filter.search_placeholder')"
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
      :placeholder="t('band_space_files.filter.tag_placeholder')"
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
      :placeholder="t('band_space_files.filter.type_placeholder')"
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
      :placeholder="t('band_space_files.filter.sort_placeholder')"
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
import { useI18n } from 'vue-i18n'

const props = defineProps({
  filters: { type: Object, required: true },
  tags: { type: Array, default: () => [] }
})

const emit = defineEmits(['update-filter'])
const { t } = useI18n()

const tagOptions = computed(() => props.tags.map((tag) => ({ label: tag.name, value: tag.id })))

const mimeOptions = computed(() => [
  { label: t('band_space_files.filter.mime.audio'), value: 'audio/' },
  { label: t('band_space_files.filter.mime.image'), value: 'image/' },
  { label: t('band_space_files.filter.mime.pdf'), value: 'application/pdf' },
  { label: t('band_space_files.filter.mime.video'), value: 'video/' }
])

const sortOptions = computed(() => [
  { label: t('band_space_files.filter.sort.newest'), value: 'date:desc' },
  { label: t('band_space_files.filter.sort.oldest'), value: 'date:asc' },
  { label: t('band_space_files.filter.sort.name_asc'), value: 'name:asc' },
  { label: t('band_space_files.filter.sort.name_desc'), value: 'name:desc' },
  { label: t('band_space_files.filter.sort.size_desc'), value: 'size:desc' },
  { label: t('band_space_files.filter.sort.size_asc'), value: 'size:asc' }
])

const sortValue = computed(() => `${props.filters.sort}:${props.filters.order}`)

function handleSortChange(value) {
  if (!value) return
  const [sort, order] = value.split(':')
  emit('update-filter', { key: 'sort', value: sort })
  emit('update-filter', { key: 'order', value: order })
}
</script>
