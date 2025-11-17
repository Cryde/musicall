import * as Cookies from 'es-cookie'
import { jwtDecode } from 'jwt-decode'
import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import securityApi from '../../api/user/security.js'
import router from '../../router/index.js'

export const useUserSecurityStore = defineStore('userSecurity', () => {
  const loginErrors = ref([])
  const isAuthenticated = ref(false)
  const isAuthenticatedLoading = ref(true)
  const user = ref(null)

  async function login(login, password) {
    loginErrors.value = []
    try {
      await securityApi.login(login, password)
      await checkAuthInfo()
      localStorage.setItem('was_logged_in', 'true')
      await router.replace({ name: 'app_home' })
    } catch (e) {
      if (e?.response?.status === 401) {
        loginErrors.value = [e.response.data.message]
      }
    }
  }

  async function checkAuthInfo() {
    isAuthenticatedLoading.value = true
    const jwt = Cookies.get('jwt_hp')
    if (!jwt) {
      if (localStorage.getItem('was_logged_in') === 'true') {
        try {
          await securityApi.refreshToken()
          return checkAuthInfo()
        } catch (_e) {
          localStorage.removeItem('was_logged_in')
        }
      }

      isAuthenticated.value = false
      isAuthenticatedLoading.value = false

      return false
    }

    const decodedJwt = jwtDecode(jwt)
    if (isTokenExpired(decodedJwt)) {
      console.log('is expired')
      await securityApi.refreshToken()

      return checkAuthInfo()
    }

    console.log('is not expired')
    user.value = { roles: decodedJwt.roles, username: decodedJwt.username }
    isAuthenticated.value = true
    isAuthenticatedLoading.value = false

    return true
  }

  function isTokenExpired(decodedJwt) {
    // we refresh it if it expire in 240 seconds
    return decodedJwt.exp - 240 <= (Date.now() / 1000).toFixed(0)
  }

  async function logout() {
    await securityApi.logout()
    user.value = null
    localStorage.removeItem('was_logged_in')
    await checkAuthInfo()
  }

  return {
    login,
    checkAuthInfo,
    logout,
    user: readonly(user),
    isAuthenticated: readonly(isAuthenticated),
    isAuthenticatedLoading: readonly(isAuthenticatedLoading),
    loginErrors: readonly(loginErrors)
  }
})
