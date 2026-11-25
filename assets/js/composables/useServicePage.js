import { computed, onMounted, ref, toValue, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useUserSecurityStore } from '../store/user/security.js'
import { isPrivateProfileError } from '../utils/profileError.js'
import { quoteDraftKey } from '../utils/quoteRequest.js'
import { hasRequestDraft, openRequestDraftStorage } from '../utils/requestDraft.js'
import { useKeepProfileContactDraft, useProfileContactDraftResumer } from './useContactDraft.js'

/**
 * What a service page does around its own sections (#1410, #1411): load the member's page, refresh it quietly once its
 * owner changed something, play a media, and contact the member, by a question or a quote request kept across a sign
 * in.
 *
 * @param {object} options
 * @param {import('vue').MaybeRefOrGetter<string>} options.kind the ServiceKind value, which picks the quote questions
 * @param {(username: string) => Promise<object>} options.fetchPage
 */
export function useServicePage({ kind, fetchPage }) {
  const route = useRoute()
  const userSecurityStore = useUserSecurityStore()
  const keepProfileContactDraft = useKeepProfileContactDraft()

  const page = ref(null)
  const isLoading = ref(true)
  const notFound = ref(false)
  const isPrivate = ref(false)
  const showForm = ref(false)
  const showPlayer = ref(false)
  const playingMedia = ref(null)
  const showMessageModal = ref(false)
  const showQuoteDialog = ref(false)
  const showAuthModal = ref(false)
  const authModalMessage = ref('')
  const contactRecipient = ref(null)
  const initialMessage = ref('')

  const isOwnPage = computed(() =>
    Boolean(page.value && userSecurityStore.userProfile?.id === page.value.user_id)
  )

  async function loadPage() {
    isLoading.value = true
    notFound.value = false
    isPrivate.value = false
    try {
      page.value = await fetchPage(route.params.username)
    } catch (error) {
      page.value = null
      isPrivate.value = isPrivateProfileError(error)
      notFound.value = !isPrivate.value
    } finally {
      isLoading.value = false
    }
  }

  // A quiet refetch, after its owner changed something: the page stays on screen.
  function refreshPage() {
    fetchPage(route.params.username)
      .then((data) => {
        page.value = data
      })
      .catch(() => {})
  }

  function openMedia(item) {
    playingMedia.value = item
    showPlayer.value = true
  }

  // A visitor writes first (#1305): « Envoyer » asks them to sign in, and this page gives the message back.
  function openMessage(content) {
    contactRecipient.value = { id: page.value.user_id, username: page.value.username }
    initialMessage.value = content
    showMessageModal.value = true
  }

  function handleContactSignInRequired({ content }) {
    authModalMessage.value = keepProfileContactDraft({ profile: page.value, content })
    showAuthModal.value = true
  }

  /**
   * A quote request written before signing in comes back once the visitor is signed in (#1408). Watched rather than
   * called after loading: on a full page load (an OAuth return) the page and the sign-in state arrive in either order.
   */
  watch(
    () => [page.value, userSecurityStore.isAuthenticated, userSecurityStore.userProfile],
    () => {
      if (!page.value || !userSecurityStore.userProfile || showQuoteDialog.value || isOwnPage.value)
        return
      if (
        userSecurityStore.isAuthenticated &&
        hasRequestDraft(
          openRequestDraftStorage(),
          quoteDraftKey(toValue(kind), route.params.username)
        )
      ) {
        showQuoteDialog.value = true
      }
    }
  )

  useProfileContactDraftResumer(
    { isLoading, loadedProfile: () => (notFound.value || isPrivate.value ? null : page.value) },
    ({ content }) => openMessage(content)
  )

  // Another member, or the same member's other kind: one page component serves several routes.
  watch([() => route.params.username, () => toValue(kind)], ([username]) => {
    if (username) loadPage()
  })

  onMounted(loadPage)

  return {
    page,
    isLoading,
    notFound,
    isPrivate,
    isOwnPage,
    showForm,
    showPlayer,
    playingMedia,
    showMessageModal,
    showQuoteDialog,
    showAuthModal,
    authModalMessage,
    contactRecipient,
    initialMessage,
    refreshPage,
    openMedia,
    openMessage,
    handleContactSignInRequired
  }
}
