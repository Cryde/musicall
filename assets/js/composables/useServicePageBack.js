import { useRoute, useRouter } from 'vue-router'

/** The phone's back arrow on a service page: where the visitor came from, or the member's profile. */
export function useServicePageBack() {
  const route = useRoute()
  const router = useRouter()

  function toProfile() {
    router.push({ name: 'app_user_public_profile', params: { username: route.params.username } })
  }

  return function handleBack() {
    switch (route.query.from) {
      case 'search':
      case 'annonces':
        // Use router.back() to preserve KeepAlive state and scroll position
        router.back()
        break
      case 'home':
        router.push({ name: 'app_home' })
        break
      case 'profile':
        toProfile()
        break
      default:
        // Fallback: try back, or go to public profile
        if (window.history.length > 1) {
          router.back()
        } else {
          toProfile()
        }
    }
  }
}
