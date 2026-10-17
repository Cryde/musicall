import { useRoute, useRouter } from 'vue-router'
import { contentLanguageLocation } from '../i18n/localizedLocation.js'

/**
 * For a page showing content written in one language (a text publication, a forum, a topic): moves it under
 * that language's prefix once the content is loaded (#1192). Returns whether it did, in which case the page
 * stops there: the app mounts it again in the content's locale (App.vue's router view is keyed on it), and
 * that copy loads the rest.
 *
 * `loadedPath` is the route's path when the load started: a visitor who has moved on since is left alone.
 */
export function useContentLanguage() {
  const route = useRoute()
  const router = useRouter()

  return function moveToContentLanguage(contentLocale, loadedPath) {
    const location = contentLanguageLocation(route, contentLocale, loadedPath)
    if (location) {
      router.replace(location)
    }

    return location !== null
  }
}
