import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import { LAST_BAND_SPACE_KEY, BAND_SPACE_ROUTES } from '../constants/bandSpace.js'

export function useBandSpaceNavigation() {
  const bandSpaceStore = useBandSpaceStore()
  const route = useRoute()
  const router = useRouter()

  const currentSpaceId = computed(() => route.params.id || null)

  const currentSpace = computed(() => {
    if (!currentSpaceId.value) return null
    return bandSpaceStore.getById(currentSpaceId.value)
  })

  function getLastSpaceId() {
    return localStorage.getItem(LAST_BAND_SPACE_KEY)
  }

  function setLastSpaceId(id) {
    if (id) {
      localStorage.setItem(LAST_BAND_SPACE_KEY, id)
    }
  }

  function clearLastSpaceId() {
    localStorage.removeItem(LAST_BAND_SPACE_KEY)
  }

  function navigateToSpace(spaceId, routeName = BAND_SPACE_ROUTES.DASHBOARD) {
    return router.push({ name: routeName, params: { id: spaceId } })
  }

  function navigateToIndex() {
    return router.replace({ name: BAND_SPACE_ROUTES.INDEX })
  }

  function handleRedirect() {
    if (!bandSpaceStore.hasSpaces) {
      bandSpaceStore.openCreateModal()
      return
    }

    const lastSpaceId = getLastSpaceId()
    const lastSpace = lastSpaceId ? bandSpaceStore.getById(lastSpaceId) : null
    const targetSpace = lastSpace || bandSpaceStore.spaces[0]

    router.replace({ name: BAND_SPACE_ROUTES.DASHBOARD, params: { id: targetSpace.id } })
  }

  function validateCurrentSpace() {
    const spaceId = currentSpaceId.value
    if (!spaceId) return true

    const space = bandSpaceStore.getById(spaceId)
    if (!space) {
      navigateToIndex()
      return false
    }
    return true
  }

  return {
    currentSpaceId,
    currentSpace,
    getLastSpaceId,
    setLastSpaceId,
    clearLastSpaceId,
    navigateToSpace,
    navigateToIndex,
    handleRedirect,
    validateCurrentSpace
  }
}
