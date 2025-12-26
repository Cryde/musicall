import * as Cookies from 'es-cookie'
import { readonly, ref } from 'vue'

const isDarkMode = ref(false)
const isInitialized = ref(false)

/**
 * Composable for managing dark mode state
 * Priority: Cookie (explicit user choice) > System preference
 */
export function useDarkMode() {
  function getSystemPreference() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
  }

  function applyDarkMode(dark) {
    const html = document.querySelector('html')
    if (dark) {
      html.classList.add('dark-mode')
    } else {
      html.classList.remove('dark-mode')
    }
    isDarkMode.value = dark
  }

  function initialize() {
    if (isInitialized.value) return

    // Check cookie first (explicit user preference)
    const cookieValue = Cookies.get('is_dark_mode')

    if (cookieValue !== undefined) {
      // User has explicitly set a preference
      applyDarkMode(cookieValue === '1')
    } else {
      // No cookie, use system preference
      applyDarkMode(getSystemPreference())
    }

    // Listen for system preference changes
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    mediaQuery.addEventListener('change', (e) => {
      // Only auto-switch if user hasn't set an explicit preference
      const cookie = Cookies.get('is_dark_mode')
      if (cookie === undefined) {
        applyDarkMode(e.matches)
      }
    })

    isInitialized.value = true
  }

  function toggle() {
    const newValue = !isDarkMode.value
    Cookies.set('is_dark_mode', newValue ? '1' : '0', { expires: 365 })
    applyDarkMode(newValue)
  }

  function setDarkMode(dark) {
    Cookies.set('is_dark_mode', dark ? '1' : '0', { expires: 365 })
    applyDarkMode(dark)
  }

  return {
    isDarkMode: readonly(isDarkMode),
    initialize,
    toggle,
    setDarkMode
  }
}
