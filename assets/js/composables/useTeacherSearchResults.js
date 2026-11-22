import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { getAgeGroupLabel, getStudentLevelLabel } from '../constants/teacherProfile.js'
import { intlLocale } from '../i18n/formatLocale.js'
import { useTeacherSearchStore } from '../store/search/teacher.js'
import { withInstrumentFirst } from '../utils/teacherPage.js'
import {
  activeSearchFilters,
  distanceToSearchedCity,
  requestAnswersFromSearch,
  searchMatches,
  searchResultsPosition
} from '../utils/teacherResults.js'
import {
  goBackToResults,
  goToNextTeacher,
  goToPreviousTeacher
} from '../utils/teacherResultsNavigation.js'
import { instrumentOf, instrumentOfSlug } from '../utils/teacherSearch.js'

/** Everything the lessons page changes when opened from the teacher search (#1315), so its content stays one. */
export function useTeacherSearchResults(profile) {
  const route = useRoute()
  const router = useRouter()
  const store = useTeacherSearchStore()
  const { t } = useI18n()
  const isLoadingNext = ref(false)

  // Null unless the store holds this teacher: a shared link with `from=search` has no results behind it.
  const position = computed(() =>
    route.query.from === 'search' ? searchResultsPosition(store, route.params.username) : null
  )
  const filters = computed(() => (position.value ? store.filters : null))
  const result = computed(() => position.value?.current ?? null)

  const FILTER_LABELS = {
    instrument: (filter) => filter.name,
    city: (filter) => t('search.teacher.where_value', { city: filter.city, radius: filter.radius }),
    level: (filter) => getStudentLevelLabel(filter.level),
    age_group: (filter) => getAgeGroupLabel(filter.ageGroup),
    online: () => t('search.teacher.online_place'),
    free_trial: () => t('search.teacher.free_trial')
  }
  const labelOf = (filter) => FILTER_LABELS[filter.key](filter)

  const chips = computed(() =>
    activeSearchFilters(filters.value).map((filter) => ({
      key: filter.key,
      label: labelOf(filter)
    }))
  )
  // The chips' words, lower case in the sentence; the distance has words of its own.
  const matches = computed(() =>
    searchMatches(filters.value, result.value).map((match) =>
      match.key === 'distance'
        ? match
        : { key: match.key, label: labelOf(match).toLocaleLowerCase(intlLocale()) }
    )
  )
  const searchDistance = computed(() => distanceToSearchedCity(filters.value, result.value))
  const searchedLevel = computed(() => filters.value?.level ?? null)
  const requestAnswers = computed(() => requestAnswersFromSearch(filters.value))

  // `?instrument=` only reorders, so it works without the results too: a shared link loads the slugs itself.
  watch(
    () => route.query.instrument,
    (slug) => {
      if (slug) store.loadInstruments().catch(() => {})
    },
    { immediate: true }
  )
  const searchedInstrumentId = computed(
    () => instrumentOfSlug(route.query.instrument, store.instruments)?.id ?? null
  )
  const instruments = computed(() =>
    withInstrumentFirst(profile.value?.instruments, searchedInstrumentId.value)
  )
  const musicianInstruments = computed(() =>
    withInstrumentFirst(profile.value?.musician_instruments, searchedInstrumentId.value)
  )
  /** « Cours de basse avec Julien », or null when no instrument the teacher teaches is searched. */
  const searchedTitle = computed(() => {
    const first = instruments.value[0]
    if (!first || first.instrument_id !== searchedInstrumentId.value) return null
    return t('profile.teacher_page.hero_title_instrument', {
      instrument: instrumentOf(first.instrument_name, intlLocale()),
      name: profile.value.display_name
    })
  })

  const goPrevious = () => goToPreviousTeacher({ store, router, route })
  const goNext = () => goToNextTeacher({ store, router, route, isLoading: isLoadingNext })
  const backToResults = () => goBackToResults({ store, router, historyState: window.history.state })

  return {
    position,
    chips,
    matches,
    searchDistance,
    searchedLevel,
    requestAnswers,
    instruments,
    musicianInstruments,
    searchedTitle,
    isLoadingNext,
    goPrevious,
    goNext,
    backToResults
  }
}
