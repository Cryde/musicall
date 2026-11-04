import { i18n } from '../i18n/index.js'

const t = (key) => i18n.global.t(key)

/** The order a listing opens in: newest first. */
export const DEFAULT_FILE_SORT = Object.freeze({ sort: 'date', order: 'desc' })

/** The file types a listing can be narrowed to, as the `mime` filter value the API reads. */
export function fileTypeOptions() {
  return [
    { label: t('band_space_files.filter.mime.audio'), value: 'audio/' },
    { label: t('band_space_files.filter.mime.image'), value: 'image/' },
    { label: t('band_space_files.filter.mime.pdf'), value: 'application/pdf' },
    { label: t('band_space_files.filter.mime.video'), value: 'video/' }
  ]
}

/** The orders a listing can take, each `sort:order` as the filters hold them. */
export function fileSortOptions() {
  return [
    { label: t('band_space_files.filter.sort.newest'), value: 'date:desc' },
    { label: t('band_space_files.filter.sort.oldest'), value: 'date:asc' },
    { label: t('band_space_files.filter.sort.name_asc'), value: 'name:asc' },
    { label: t('band_space_files.filter.sort.name_desc'), value: 'name:desc' },
    { label: t('band_space_files.filter.sort.size_desc'), value: 'size:desc' },
    { label: t('band_space_files.filter.sort.size_asc'), value: 'size:asc' }
  ]
}

/**
 * @param {{sort: string, order: string}} filters
 * @returns {string}
 */
export function fileSortValue(filters) {
  return `${filters.sort}:${filters.order}`
}

/**
 * The two filters a sort option sets, or null for no option.
 *
 * @param {string|null} value
 * @returns {{sort: string, order: string}|null}
 */
export function parseFileSort(value) {
  if (!value) return null
  const [sort, order] = value.split(':')

  return sort && order ? { sort, order } : null
}

/**
 * What the phone's three filter chips say: the choice when one is made, the filter's name otherwise.
 * The sort always has a choice, so its chip always names it.
 *
 * @param {{tagId: string|null, mime: string|null, sort: string, order: string}} filters
 * @param {Array<{id: string, name: string}>} tags
 * @returns {{tag: string, type: string, sort: string}}
 */
export function fileFilterChipLabels(filters, tags = []) {
  const tag = filters.tagId ? tags.find((candidate) => candidate.id === filters.tagId) : null
  const type = fileTypeOptions().find((option) => option.value === filters.mime)
  const sort = fileSortOptions().find((option) => option.value === fileSortValue(filters))

  return {
    tag: tag?.name ?? t('band_space_files.filter.tag_placeholder'),
    type: type?.label ?? t('band_space_files.filter.type_placeholder'),
    sort: sort?.label ?? t('band_space_files.filter.sort_placeholder')
  }
}
