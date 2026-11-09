import { useRoute, useRouter } from 'vue-router'
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../store/user/security.js'
import { bandSpaceCreateAction } from '../utils/bandSpaceCreateAction.js'
import { routeReturningTo } from '../utils/unauthenticatedRedirect.js'
import { usePendingAction } from './usePendingAction.js'

/**
 * « Créer » from a page outside the band layout (#1304). A visitor signs up first and finds the create dialog
 * waiting, prefilled, when they come back. A member is never stopped, so the name goes straight to the dialog,
 * which the band layout shows open.
 */
export function useStartBandSpaceCreate() {
  const route = useRoute()
  const router = useRouter()
  const bandSpaceStore = useBandSpaceStore()
  const userSecurityStore = useUserSecurityStore()
  const { keep } = usePendingAction()

  return function startBandSpaceCreate(name = '') {
    // Until the boot check answers, a member would pass for a visitor and be sent to register.
    if (userSecurityStore.isAuthenticatedLoading) return
    if (!userSecurityStore.isAuthenticated) {
      const bandIndexPath = router.resolve({ name: 'app_band_index' }).href
      const action = bandSpaceCreateAction(name, route, bandIndexPath)
      keep(action)
      router.push(routeReturningTo('app_register', action.route))
      return
    }

    bandSpaceStore.openCreateModal(name.trim())
    router.push({ name: 'app_band_index' })
  }
}
