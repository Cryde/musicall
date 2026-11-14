import { identifyUmamiSession } from '@jaseeey/vue-umami-plugin'
import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import securityApi from '../../api/user/security.js'
import { i18n } from '../../i18n/index.js'
import router from '../../router/index.js'
import { clearPendingAction, openPendingActionStorage } from '../../utils/pendingAction.js'
import { isSafeReturnUrl } from '../../utils/returnUrl.js'
import { grantsAdmin, grantsTester } from '../../utils/roles.js'
import { createSessionKeeper } from '../../utils/sessionKeeper.js'
import { clearRequestDrafts, openRequestDraftStorage } from '../../utils/trialRequest.js'
import { routeReturningTo } from '../../utils/unauthenticatedRedirect.js'

export const useUserSecurityStore = defineStore('userSecurity', () => {
  const loginErrors = ref([])
  const isAuthenticated = ref(false)
  // Who is signed in is not known yet: true until the first checkAuthInfo() settles, never again, so a
  // renewal mid-session does not blank everything keyed on it (#1022).
  const isAuthenticatedLoading = ref(true)
  const user = ref(null)
  const userProfile = ref(null)
  const authError = ref(null)

  const sessionKeeper = createSessionKeeper({
    session: {
      isSignedIn: () => isAuthenticated.value,
      signedIn(payload) {
        user.value = { roles: payload.roles, username: payload.username }
        isAuthenticated.value = true
        isAuthenticatedLoading.value = false
        // Fetch full user profile for additional data like profile picture
        fetchUserProfile()
      },
      signedOut() {
        isAuthenticated.value = false
        isAuthenticatedLoading.value = false
        user.value = null
        userProfile.value = null
      },
      expired() {
        authError.value = i18n.global.t('auth.session.expired')
      }
    }
  })

  async function login(login, password, returnUrl = null) {
    loginErrors.value = []
    authError.value = null
    try {
      await securityApi.login(login, password)
      await checkAuthInfo()
      // Hard reload (rather than router.replace) to start from a clean Pinia
      // state — prevents any stale data from a prior session (different user
      // on the same browser, expired token) from surviving into the new one.
      // `returnUrl` comes from the URL query string, so it must be validated as
      // a same-origin relative path before redirecting (open-redirect guard).
      if (returnUrl && isSafeReturnUrl(returnUrl)) {
        window.location.href = returnUrl
      } else {
        window.location.href = router.resolve({ name: 'app_home' }).href
      }
    } catch (e) {
      if (e?.response?.status === 401) {
        const data = e.response.data
        if (data.message === 'account_not_verified' && data.email) {
          await router.replace(
            routeReturningTo('app_verify_email', returnUrl, { email: data.email })
          )
          return
        }
        loginErrors.value = [
          data.message === 'account_suspended'
            ? i18n.global.t('auth.session.account_suspended')
            : data.message
        ]
      }
    }
  }

  /**
   * Works out who is signed in, renewing the token when it is missing or nearly out, and keeps them
   * so: see createSessionKeeper().
   */
  function checkAuthInfo() {
    authError.value = null

    return sessionKeeper.check()
  }

  /**
   * The refresh token was refused, so the session is over and cannot be renewed.
   *
   * Marking the store unauthenticated is not cosmetic: `app_login` is `isGuestOnly`, so a redirect
   * sent while this still reads as signed in is bounced straight back to the home page by the router
   * guard and the member never reaches the login form.
   */
  function expireSession() {
    sessionKeeper.expire()
  }

  async function fetchUserProfile() {
    try {
      userProfile.value = await securityApi.getSelf()
      identifyUmamiSession(userProfile.value.id)
    } catch (e) {
      console.error('Failed to fetch user profile:', e)
    }
  }

  const profilePictureUrl = computed(() => {
    return userProfile.value?.profile_picture?.small || null
  })

  /**
   * Both flags read the JWT claim rather than userProfile, because a route guard needs the answer
   * immediately: checkAuthInfo() fills `user` from the token and then calls fetchUserProfile()
   * WITHOUT awaiting it, so userProfile is still empty on the first navigation. Reading it in a
   * guard bounces a legitimate admin whenever they load a gated URL directly or hit reload.
   *
   * isAdmin goes through grantsAdmin rather than includes() because roles arrive unexpanded from
   * both sources, so anything the server hierarchy would have added has to be added client side
   * (#940).
   */
  const isAdmin = computed(() => grantsAdmin(user.value?.roles))

  /**
   * Sees modules that are merged but not yet announced. Presentation only: the APIs behind them
   * stay open, so this draws a curtain rather than protecting anything.
   *
   * Independent of isAdmin, deliberately. A tester is an ordinary account with a flag, so a preview
   * can go to a band member without also giving them the back office (#942).
   */
  const isTester = computed(() => grantsTester(user.value?.roles))

  async function logout() {
    try {
      await securityApi.logout()
    } catch (e) {
      console.error('Logout API error:', e)
    }

    user.value = null
    userProfile.value = null
    sessionKeeper.end()
    clearRequestDrafts(openRequestDraftStorage())
    clearPendingAction(openPendingActionStorage())
    // The Band Space draft name kept before #1304: remove once old drafts have aged out.
    try {
      localStorage.removeItem('band-space-draft-name')
    } catch {
      // Blocked storage holds no draft.
    }

    isAuthenticated.value = false
    isAuthenticatedLoading.value = false

    // Hard reload wipes all Pinia stores so no user-scoped data (messages,
    // drafts, band space, etc.) leaks to the next user on a shared browser.
    window.location.href = router.resolve({ name: 'app_home' }).href
  }

  async function refreshUserProfile() {
    if (isAuthenticated.value) {
      await fetchUserProfile()
    }
  }

  function clearAuthError() {
    authError.value = null
  }

  return {
    login,
    checkAuthInfo,
    expireSession,
    logout,
    refreshUserProfile,
    clearAuthError,
    user: readonly(user),
    userProfile: readonly(userProfile),
    profilePictureUrl,
    isAdmin,
    isTester,
    isAuthenticated: readonly(isAuthenticated),
    isAuthenticatedLoading: readonly(isAuthenticatedLoading),
    loginErrors: readonly(loginErrors),
    authError: readonly(authError)
  }
})
