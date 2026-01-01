import * as Cookies from 'es-cookie'
import { jwtDecode } from 'jwt-decode'
import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import securityApi from '../../api/user/security.js'
import router from '../../router/index.js'

// Refresh token promise cache to prevent race conditions
let refreshPromise = null

// Proactive refresh interval (check every 5 minutes)
const REFRESH_CHECK_INTERVAL = 5 * 60 * 1000
// Refresh buffer - refresh when token expires in less than this
const REFRESH_BUFFER_SECONDS = 300 // 5 minutes

export const useUserSecurityStore = defineStore('userSecurity', () => {
  const loginErrors = ref([])
  const isAuthenticated = ref(false)
  const isAuthenticatedLoading = ref(true)
  const user = ref(null)
  const userProfile = ref(null)
  const authError = ref(null)

  let refreshIntervalId = null

  async function login(login, password) {
    loginErrors.value = []
    authError.value = null
    try {
      await securityApi.login(login, password)
      await checkAuthInfo()
      localStorage.setItem('was_logged_in', 'true')
      startProactiveRefresh()
      await router.replace({ name: 'app_home' })
    } catch (e) {
      if (e?.response?.status === 401) {
        loginErrors.value = [e.response.data.message]
      }
    }
  }

  /**
   * Refresh token with promise caching to prevent race conditions
   * Multiple simultaneous calls will share the same promise
   */
  async function refreshToken() {
    if (!refreshPromise) {
      refreshPromise = securityApi.refreshToken().finally(() => {
        refreshPromise = null
      })
    }
    return refreshPromise
  }

  /**
   * Check authentication info with retry limit to prevent infinite recursion
   */
  async function checkAuthInfo(retryCount = 0) {
    const MAX_RETRIES = 2

    isAuthenticatedLoading.value = true
    authError.value = null

    const jwt = Cookies.get('jwt_hp')

    if (!jwt) {
      // No JWT cookie - try to refresh if user was previously logged in
      if (localStorage.getItem('was_logged_in') === 'true' && retryCount < MAX_RETRIES) {
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

    try {
      const decodedJwt = jwtDecode(jwt)

      if (isTokenExpired(decodedJwt)) {
        if (retryCount < MAX_RETRIES) {
          await refreshToken()
          return checkAuthInfo(retryCount + 1)
        }
        handleAuthFailure('Impossible de rafraîchir votre session.')
        setUnauthenticated()
        return false
      }

      // Token is valid
      user.value = { roles: decodedJwt.roles, username: decodedJwt.username }
      isAuthenticated.value = true
      isAuthenticatedLoading.value = false

      // Fetch full user profile for additional data like profile picture
      fetchUserProfile()

      // Start proactive refresh if not already running
      startProactiveRefresh()

      return true
    } catch (e) {
      console.error('Failed to decode JWT:', e)
      handleAuthFailure("Erreur d'authentification.")
      setUnauthenticated()
      return false
    }
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

  async function fetchUserProfile() {
    try {
      userProfile.value = await securityApi.getSelf()
    } catch (e) {
      console.error('Failed to fetch user profile:', e)
    }
  }

  const profilePictureUrl = computed(() => {
    return userProfile.value?.profile_picture?.small || null
  })

  function isTokenExpired(decodedJwt) {
    // Refresh if token expires within buffer time
    const currentTime = Math.floor(Date.now() / 1000)
    return decodedJwt.exp - REFRESH_BUFFER_SECONDS <= currentTime
  }

  /**
   * Get remaining time until token expiration in seconds
   */
  function getTokenRemainingTime() {
    const jwt = Cookies.get('jwt_hp')
    if (!jwt) return 0

    try {
      const decodedJwt = jwtDecode(jwt)
      const currentTime = Math.floor(Date.now() / 1000)
      return Math.max(0, decodedJwt.exp - currentTime)
    } catch {
      return 0
    }
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

      // Refresh if less than buffer time remaining
      if (remainingTime > 0 && remainingTime < REFRESH_BUFFER_SECONDS) {
        try {
          await refreshToken()
          // Re-check auth to update user data
          await checkAuthInfo()
        } catch (e) {
          console.warn('Proactive token refresh failed:', e.message)
        }
      } else if (remainingTime === 0) {
        // Token already expired
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

    await router.push({ name: 'app_home' })
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
    logout,
    refreshUserProfile,
    clearAuthError,
    user: readonly(user),
    userProfile: readonly(userProfile),
    profilePictureUrl,
    isAuthenticated: readonly(isAuthenticated),
    isAuthenticatedLoading: readonly(isAuthenticatedLoading),
    loginErrors: readonly(loginErrors),
    authError: readonly(authError)
  }
})
