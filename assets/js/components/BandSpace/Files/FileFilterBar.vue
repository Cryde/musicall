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
import {
  fileSortOptions,
  fileSortValue,
  fileTypeOptions,
  parseFileSort
} from '../../../utils/fileFilters.js'

const props = defineProps({
  filters: { type: Object, required: true },
  tags: { type: Array, default: () => [] }
})

const emit = defineEmits(['update-filter'])
const { t } = useI18n()

const tagOptions = computed(() => props.tags.map((tag) => ({ label: tag.name, value: tag.id })))

const mimeOptions = computed(() => fileTypeOptions())

const sortOptions = computed(() => fileSortOptions())

const sortValue = computed(() => fileSortValue(props.filters))

function handleSortChange(value) {
  const sort = parseFileSort(value)
  if (!sort) return
  emit('update-filter', { key: 'sort', value: sort.sort })
  emit('update-filter', { key: 'order', value: sort.order })
}
</script>
