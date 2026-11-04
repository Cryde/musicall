import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../store/user/security.js'
import { userMenuEntries } from '../utils/userMenu.js'

/** The account menu, translated, and the way out: what every menu offering it needs. */
export function useUserMenu() {
  const { t } = useI18n()
  const userSecurityStore = useUserSecurityStore()

  const entries = computed(() =>
    userMenuEntries({
      username: userSecurityStore.user?.username,
      isAdmin: userSecurityStore.isAdmin
    }).map((entry) => ({ ...entry, label: t(entry.labelKey) }))
  )

  function logout() {
    trackUmamiEvent('user-logout')
    userSecurityStore.logout()
  }

  return { entries, logout }
}
