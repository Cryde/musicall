import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { DIRECTORY, SERVICE_KINDS } from '../constants/serviceKinds.js'
import { useUserSecurityStore } from '../store/user/security.js'
import { serviceMenuEntries } from '../utils/serviceSearchLinks.js'

/** The menus' links after « Musiciens » (#1410, #1414), as serviceMenuEntries() picks them. */
export function useServiceSearchLinks() {
  const { t } = useI18n()
  const userSecurityStore = useUserSecurityStore()

  return computed(() =>
    serviceMenuEntries(SERVICE_KINDS, DIRECTORY, userSecurityStore.isTester).map((entry) => ({
      key: entry.key,
      label: t(entry.menuKey),
      icon: entry.icon,
      to: { name: entry.route }
    }))
  )
}
