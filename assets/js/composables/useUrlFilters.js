import { useDebounceFn } from '@vueuse/core'
import { reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/**
 * Sync a reactive filter object with the URL query string.
 *
 *   const { filters, clear } = useUrlFilters(
 *     { sort: 'desc', styles: [], q: '' },
 *     { preserveKeys: ['page'] }
 *   )
 *
 * - `defaults` is the source of truth for shape, types, and "omit from URL"
 *   logic: when a value equals its default (or is null/undefined/empty), it
 *   is dropped from the URL so shared links stay clean.
 * - Supports primitives (string / number / boolean) and arrays. Arrays are
 *   serialised as comma-joined values. Use multiple flat keys for complex
 *   shapes (e.g. lat / lng / location_name instead of a single object).
 * - URL writes go through `router.replace` (no history spam) and are
 *   debounced (default 200ms) when `autoSync` is true. With `autoSync: false`,
 *   the caller invokes the returned `push()` at the appropriate moment (e.g.
 *   after a search button click) so the URL captures the executed state, not
 *   the in-progress draft.
 * - Unrelated query keys listed in `preserveKeys` (e.g. ?page=N) are
 *   preserved across writes so URL-driven pagination keeps working.
 */
export function useUrlFilters(defaults, options = {}) {
  const { debounceMs = 200, preserveKeys = ['page'], autoSync = true } = options
  const route = useRoute()
  const router = useRouter()

  const filters = reactive(cloneDefaults(defaults))

  // Hydrate from the current URL synchronously - by the time the caller
  // reads `filters` in onMounted, the values reflect ?key=val.
  for (const [key, defaultValue] of Object.entries(defaults)) {
    const raw = route.query[key]
    if (raw === undefined) continue
    filters[key] = parseFromUrl(raw, defaultValue)
  }

  function pushNow() {
    const query = {}
    for (const key of preserveKeys) {
      if (route.query[key] !== undefined) {
        query[key] = route.query[key]
      }
    }
    for (const [key, defaultValue] of Object.entries(defaults)) {
      const value = filters[key]
      if (isDefault(value, defaultValue)) continue
      query[key] = serializeForUrl(value)
    }
    router.replace({ query })
  }

  const push = useDebounceFn(pushNow, debounceMs)

  if (autoSync) {
    watch(filters, push, { deep: true })
  }

  function clear() {
    Object.assign(filters, cloneDefaults(defaults))
  }

  return { filters, clear, push: pushNow }
}

function cloneDefaults(defaults) {
  const cloned = {}
  for (const [key, value] of Object.entries(defaults)) {
    cloned[key] = Array.isArray(value) ? [...value] : value
  }
  return cloned
}

function isDefault(value, defaultValue) {
  if (Array.isArray(defaultValue)) {
    return !Array.isArray(value) || value.length === 0
  }
  if (value === null || value === undefined || value === '') return true
  return value === defaultValue
}

function serializeForUrl(value) {
  if (Array.isArray(value)) return value.join(',')
  if (typeof value === 'boolean') return value ? '1' : '0'
  return String(value)
}

function parseFromUrl(raw, defaultValue) {
  if (Array.isArray(defaultValue)) {
    return String(raw)
      .split(',')
      .map((part) => part.trim())
      .filter(Boolean)
  }
  if (typeof defaultValue === 'number') {
    const n = Number(raw)
    return Number.isFinite(n) ? n : defaultValue
  }
  if (typeof defaultValue === 'boolean') {
    return raw === '1' || raw === 'true'
  }
  return String(raw)
}
