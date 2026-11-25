import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SERVICE_KINDS } from '../constants/serviceKinds.js'
import { useUserSecurityStore } from '../store/user/security.js'
import { visibleServiceSearches } from '../utils/serviceSearchLinks.js'

/** The menus' links to each kind's search (#1410, #1411), as visibleServiceSearches() picks them. */
export function useServiceSearchLinks() {
  const { t } = useI18n()
  const userSecurityStore = useUserSecurityStore()

  return computed(() =>
    visibleServiceSearches(SERVICE_KINDS, userSecurityStore.isTester).map(([key, kind]) => ({
      key,
      label: t(kind.search.menuKey),
      icon: kind.icon,
      to: { name: kind.search.route }
    }))
  )
}
