<template>
  <BottomSheet v-model:visible="visible" :title="t('band_space_files.phone.filters')">
    <section v-for="group in groups" :key="group.key" class="pb-2">
      <h3 :id="`file-filter-${group.key}`" class="m-0 px-5 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
        {{ group.title }}
      </h3>
      <ul class="m-0 list-none p-0" :aria-labelledby="`file-filter-${group.key}`">
        <li v-for="option in group.options" :key="String(option.value)">
          <button
            type="button"
            :class="SHEET_CHOICE"
            :aria-pressed="group.current === option.value"
            @click="choose(group.key, option.value)"
          >
            {{ option.label }}
            <i v-if="group.current === option.value" class="pi pi-check ml-auto text-primary" aria-hidden="true" />
          </button>
        </li>
      </ul>
    </section>
  </BottomSheet>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SHEET_CHOICE } from '../../../constants/phoneControls.js'
import {
  fileSortOptions,
  fileSortValue,
  fileTypeOptions,
  parseFileSort
} from '../../../utils/fileFilters.js'
import BottomSheet from '../../Global/BottomSheet.vue'

/**
 * The phone's tag, type and order, in one sheet the three chips open. A choice applies at once, as the
 * desktop's dropdowns do, and the sheet stays open for the next one.
 */
const props = defineProps({
  filters: { type: Object, required: true },
  tags: { type: Array, default: () => [] }
})

const emit = defineEmits(['update-filter'])
const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()

const groups = computed(() => [
  {
    key: 'tagId',
    title: t('band_space_files.filter.tag_placeholder'),
    current: props.filters.tagId,
    options: [
      { label: t('band_space_files.phone.all_tags'), value: null },
      ...props.tags.map((tag) => ({ label: tag.name, value: tag.id }))
    ]
  },
  {
    key: 'mime',
    title: t('band_space_files.filter.type_placeholder'),
    current: props.filters.mime,
    options: [{ label: t('band_space_files.phone.all_types'), value: null }, ...fileTypeOptions()]
  },
  {
    key: 'sort',
    title: t('band_space_files.filter.sort_placeholder'),
    current: fileSortValue(props.filters),
    options: fileSortOptions()
  }
])

function choose(key, value) {
  if (key !== 'sort') {
    emit('update-filter', { key, value })
    return
  }
  const sort = parseFileSort(value)
  if (!sort) return
  emit('update-filter', { key: 'sort', value: sort.sort })
  emit('update-filter', { key: 'order', value: sort.order })
}
</script>
