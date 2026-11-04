import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserBlockStore } from '../store/user/block.js'
import { useUserSecurityStore } from '../store/user/security.js'

/**
 * Blocking somebody (#1117), for the profile's button and a conversation's menu alike: a block is
 * confirmed first, an unblock is not, and either says how it went in a toast.
 *
 * @param {import('vue').MaybeRefOrGetter<string>} userId
 * @param {import('vue').MaybeRefOrGetter<string>} username
 */
export function useBlockUser(userId, username) {
  const userSecurityStore = useUserSecurityStore()
  const userBlockStore = useUserBlockStore()
  const confirm = useConfirm()
  const toast = useToast()
  const { t } = useI18n()
  const isWorking = ref(false)

  // Offered to a signed-in member on somebody else, once the block list is known.
  const canBlock = computed(
    () =>
      userSecurityStore.isAuthenticated &&
      userBlockStore.isLoaded &&
      Boolean(userSecurityStore.userProfile?.id) &&
      userSecurityStore.userProfile.id !== toValue(userId)
  )
  const isBlocked = computed(() => userBlockStore.isBlocked(toValue(userId)))

  onMounted(() => {
    if (userSecurityStore.isAuthenticated) {
      userBlockStore.loadBlocks().catch(() => {})
    }
  })

  async function run(action, successMessage, onDone) {
    isWorking.value = true
    try {
      await action()
      toast.add({ severity: 'success', summary: successMessage, life: 3000 })
      onDone?.()
    } catch (e) {
      toast.add({
        severity: 'error',
        summary: t('profile.block.error_summary'),
        detail: e?.response?.data?.detail || t('profile.block.error_detail'),
        life: 4000
      })
    } finally {
      isWorking.value = false
    }
  }

  /** Blocks after a confirmation, or unblocks straight away. */
  function toggleBlock({ onBlocked, onUnblocked } = {}) {
    const name = toValue(username)
    if (isBlocked.value) {
      run(
        () => userBlockStore.unblockUser(toValue(userId)),
        t('profile.block.unblocked_toast', { username: name }),
        onUnblocked
      )
      return
    }
    confirm.require({
      header: t('profile.block.confirm_header', { username: name }),
      message: t('profile.block.confirm_message'),
      icon: 'pi pi-ban',
      rejectLabel: t('profile.block.cancel'),
      acceptLabel: t('profile.block.block'),
      acceptProps: { severity: 'danger' },
      accept: () =>
        run(
          () => userBlockStore.blockUser(toValue(userId)),
          t('profile.block.blocked_toast', { username: name }),
          onBlocked
        )
    })
  }

  return { canBlock, isBlocked, isWorking, toggleBlock }
}
