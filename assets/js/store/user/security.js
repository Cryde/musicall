import { identifyUmamiSession } from '@jaseeey/vue-umami-plugin'
import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import securityApi from '../../api/user/security.js'
import router from '../../router/index.js'
import { isSafeReturnUrl } from '../../utils/returnUrl.js'
import { grantsAdmin, grantsTester } from '../../utils/roles.js'
import {
  needsProactiveRefresh,
  readJwtPayload,
  refreshSession,
  secondsUntilExpiry
} from '../../utils/sessionRefresh.js'

/**
 * How often the proactive timer looks at the token.
 *
 * **Strictly shorter than REFRESH_BUFFER_SECONDS, and that is the whole point.** Both used to be 300,
 * and since the timer starts at login and the token expires 3600 seconds later, the checks landed on
 * remaining values of 600, then exactly 300, then 0. The old test was `< 300`, so the buffer never
 * fired once: the only branch that ever recovered was expiry itself, which is after every other
 * request has already been refused (#1008).
 */
const REFRESH_CHECK_INTERVAL = 60 * 1000

export const useUserSecurityStore = defineStore('userSecurity', () => {
  const loginErrors = ref([])
  const isAuthenticated = ref(false)
  const isAuthenticatedLoading = ref(true)
  const user = ref(null)
  const userProfile = ref(null)
  const authError = ref(null)

  let refreshIntervalId = null

  async function login(login, password, returnUrl = null) {
    loginErrors.value = []
    authError.value = null
    try {
      await securityApi.login(login, password)
      await checkAuthInfo()
      localStorage.setItem('was_logged_in', 'true')
      startProactiveRefresh()
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
          await router.replace({ name: 'app_verify_email', query: { email: data.email } })
          return
        }
        loginErrors.value = [data.message]
      }
    }
  }

  /**
   * Shared with the 401 interceptor rather than kept here, so the two cannot renew at the same time
   * and have the second consume a token the first already spent.
   */
  function refreshToken() {
    return refreshSession()
  }

  /**
   * Works out who is signed in, renewing the token when it is missing or nearly out.
   *
   * A missing token is the ordinary state of a tab that has been open an hour, not an error: the
   * browser deletes the cookie the moment the token expires. `was_logged_in` is what says a renewal
   * is worth attempting, and it is cleared only when one genuinely fails, so somebody still holding a
   * valid refresh token is never asked for their password again (#1008).
   *
   * The retry count is what stops a refresh that keeps returning an unusable token from recursing.
   */
  async function checkAuthInfo(retryCount = 0) {
    const MAX_RETRIES = 2

    isAuthenticatedLoading.value = true
    authError.value = null

    const payload = readJwtPayload()

    if (payload === null || needsRenewing(payload)) {
      // A token that is merely old is renewed whatever localStorage says; only the case where there
      // is nothing at all to read needs telling whether this browser was ever signed in.
      const worthTrying = payload !== null || localStorage.getItem('was_logged_in') === 'true'

      if (worthTrying && retryCount < MAX_RETRIES) {
        try {
          await refreshToken()

          return checkAuthInfo(retryCount + 1)
        } catch (e) {
          console.warn('Token refresh failed:', e.message)
          handleAuthFailure('Votre session a expiré. Veuillez vous reconnecter.')
        }
      }

      setUnauthenticated()

      return false
    }

    user.value = { roles: payload.roles, username: payload.username }
    isAuthenticated.value = true
    isAuthenticatedLoading.value = false

    // Fetch full user profile for additional data like profile picture
    fetchUserProfile()

    // Start proactive refresh if not already running
    startProactiveRefresh()

    return true
  }

  function setUnauthenticated() {
    isAuthenticated.value = false
    isAuthenticatedLoading.value = false
    user.value = null
    userProfile.value = null
    stopProactiveRefresh()
  }

  function handleAuthFailure(message) {
    authError.value = message
    localStorage.removeItem('was_logged_in')
  }

  /**
   * The session is over and cannot be renewed, which after #1008 means the refresh token itself was
   * refused rather than merely that the JWT aged out.
   *
   * Marking the store unauthenticated is not cosmetic: `app_login` is `isGuestOnly`, so a redirect
   * sent while this still reads as signed in is bounced straight back to the home page by the router
   * guard and the member never reaches the login form.
   */
  function expireSession() {
    handleAuthFailure('Votre session a expiré. Veuillez vous reconnecter.')
    setUnauthenticated()
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

  /**
   * Whether this token is too near its end to start a page on. Not expiry alone: one with two
   * minutes left is renewed here rather than left to be refused halfway through a navigation.
   */
  function needsRenewing(payload) {
    const remaining = secondsUntilExpiry(payload, nowInSeconds())

    return remaining === 0 || needsProactiveRefresh(remaining)
  }

  function nowInSeconds() {
    return Math.floor(Date.now() / 1000)
  }

  /** Seconds left on the token the browser is holding, 0 when there is none or it cannot be read. */
  function getTokenRemainingTime() {
    return secondsUntilExpiry(readJwtPayload(), nowInSeconds())
  }

  /**
   * Proactive token refresh - periodically check and refresh before expiration
   */
  function startProactiveRefresh() {
    if (refreshIntervalId) return // Already running

    refreshIntervalId = setInterval(async () => {
      if (!isAuthenticated.value) {
        stopProactiveRefresh()
        return
      }

      const remainingTime = getTokenRemainingTime()

      if (needsProactiveRefresh(remainingTime)) {
        try {
          await refreshToken()
          // Re-check auth to update user data
          await checkAuthInfo()
        } catch (e) {
          // Nothing is cleared here: the token is still valid for a few minutes, and if it does run
          // out the 401 path will renew it. Giving up on the session over one failed attempt is the
          // bug this whole change exists to remove.
          console.warn('Proactive token refresh failed:', e.message)
        }
      } else if (remainingTime === 0) {
        // Already expired, so there is nothing to renew proactively; checkAuthInfo goes through the
        // refresh token instead.
        await checkAuthInfo()
      }
    }, REFRESH_CHECK_INTERVAL)
  }

  function stopProactiveRefresh() {
    if (refreshIntervalId) {
      clearInterval(refreshIntervalId)
      refreshIntervalId = null
    }
  }

  async function logout() {
    try {
      await securityApi.logout()
    } catch (e) {
      console.error('Logout API error:', e)
    }

    user.value = null
    userProfile.value = null
    localStorage.removeItem('was_logged_in')
    stopProactiveRefresh()

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
