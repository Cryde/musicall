<template>
  <!-- Its own group, mounted in App.vue, so the prompt shows under every layout, the layout-less ones included. -->
  <Toast :group="GROUP" :breakpoints="{ '420px': { width: '92vw' } }" @close="handleClose">
    <template #message="{ message }">
      <div class="flex-1">
        <Button
          v-if="message.data"
          :label="t('shared.pending_action.offer', { label: message.data.label })"
          link
          @click="handleResume(message)"
        />
        <p v-else class="m-0">{{ message.detail }}</p>
      </div>
    </template>
  </Toast>
</template>

<script setup>
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { isLocaleSettled } from '../../composables/useLocaleChoice.js'
import { pendingActionResumers } from '../../composables/usePendingAction.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import {
  clearPendingAction,
  isOnPendingActionPage,
  openPendingActionStorage,
  readPendingAction,
  resumePendingAction
} from '../../utils/pendingAction.js'

const GROUP = 'pending-action'
const UNAVAILABLE_LIFE_MS = 8000

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const userSecurityStore = useUserSecurityStore()
const storage = openPendingActionStorage()

// And the page in its final locale: the account's language, applied after sign-in, remounts the page.
const isSignedIn = computed(
  () =>
    !userSecurityStore.isAuthenticatedLoading &&
    userSecurityStore.isAuthenticated &&
    isLocaleSettled.value
)
// The path the « Reprendre » toast is showing on, and whether a resumer is still checking: each offers once. A
// trigger during the check is run after it, so a page that remounts meanwhile still gets its offer.
let offeredOn = null
let isChecking = false
let isRecheckDue = false

// Login reloads the page, so this runs once the boot check says who is signed in, then on each page change and
// each page that registers a resumer, since the page holding the action may mount after the check.
watch(() => [isSignedIn.value, route.path, [...pendingActionResumers.values()]], offerPendingAction)

async function offerPendingAction() {
  if (offeredOn !== null && offeredOn !== route.path) {
    // Hidden without the close event, so the action waits for the member's next visit to its page.
    toast.removeGroup(GROUP)
    offeredOn = null
  }
  if (isChecking) {
    isRecheckDue = true
    return
  }
  if (!isSignedIn.value || offeredOn !== null) return

  const action = readPendingAction(storage)
  if (!action || !isOnPendingActionPage(action, route.path)) return
  const resumer = pendingActionResumers.get(action.kind)
  if (!resumer) return

  const path = route.path
  isChecking = true
  try {
    await resumePendingAction(action, resumer, {
      drop: () => clearPendingAction(storage),
      offer: (label, resume) => {
        offeredOn = path
        toast.add({ group: GROUP, severity: 'info', data: { label, resume } })
      },
      inform: (sentence) =>
        toast.add({ group: GROUP, severity: 'warn', detail: sentence, life: UNAVAILABLE_LIFE_MS }),
      // The resumer compared too: one replaced by a remount would act on a component that is gone.
      isStillOnPage: () => route.path === path && pendingActionResumers.get(action.kind) === resumer
    })
  } catch (error) {
    console.warn('The pending action could not be resumed:', error)
  } finally {
    isChecking = false
  }
  if (isRecheckDue) {
    isRecheckDue = false
    await offerPendingAction()
  }
}

/** Closing the « Reprendre » toast dismisses the action for good. */
function handleClose({ message }) {
  if (!message.data) return
  clearPendingAction(storage)
  offeredOn = null
}

function handleResume(message) {
  toast.remove(message)
  message.data.resume()
}
</script>
