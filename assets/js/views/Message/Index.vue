<template>
  <div>
    <div class="mb-4 flex items-center justify-between md:mb-6">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('message.index.title') }}
      </h1>
      <div class="hidden md:block">
        <Button
          :label="t('message.index.new_message')"
          icon="pi pi-plus"
          @click="showSendModal = true"
        />
      </div>
    </div>

    <!-- Two columns from `md` up. On a phone the list fills the page edge to edge, and an open
         conversation the screen under the header, its composer at the bottom. -->
    <div
      class="-mx-6 bg-surface-0 md:mx-0 md:overflow-hidden md:rounded-lg md:border md:border-surface-200 dark:bg-surface-900 md:dark:border-surface-700"
    >
      <div class="md:flex md:h-[70vh]">
        <div
          class="w-full md:w-1/3 md:border-r md:border-surface-200 md:dark:border-surface-700"
          :class="{ 'max-md:hidden': messageStore.currentThreadId }"
        >
          <ProgressSpinner v-if="messageStore.isLoading" class="flex justify-center p-4" />
          <ThreadList v-else />
        </div>

        <!-- min-w-0: a flex item never shrinks below its content's minimum width otherwise, and the
             chat composer's editor has one wider than a phone. -->
        <div
          class="min-w-0 flex-1"
          :class="
            messageStore.currentThreadId
              ? 'max-md:fixed max-md:inset-x-0 max-md:top-16 max-md:bottom-0 max-md:z-20 max-md:bg-surface-0 max-md:pb-[env(safe-area-inset-bottom)] max-md:dark:bg-surface-900'
              : 'max-md:hidden'
          "
        >
          <Thread @back="handleBack" />
        </div>
      </div>
    </div>

    <!-- On a phone « Nouveau message » floats over the list, where the thumb is. -->
    <div
      v-if="!messageStore.currentThreadId"
      class="fixed right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 md:hidden"
    >
      <Button
        :label="t('message.index.new_message')"
        icon="pi pi-pen-to-square"
        rounded
        class="shadow-lg"
        @click="showSendModal = true"
      />
    </div>

    <SendMessageModal v-model:visible="showSendModal" />
  </div>
</template>

<script setup>
import { useMediaQuery, useScrollLock } from '@vueuse/core'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { onMounted, onUnmounted, ref, watch, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import Thread from '../../components/Message/Thread.vue'
import ThreadList from '../../components/Message/ThreadList.vue'
import { useMessageStore } from '../../store/message/message.js'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const messageStore = useMessageStore()
const showSendModal = ref(false)

// Two columns, so a conversation can be shown beside the list without being asked for.
const hasTwoColumns = useMediaQuery('(width >= 48rem)')

// The page under a phone's open conversation stays put while the conversation scrolls.
const isPageLocked = useScrollLock(typeof document === 'undefined' ? null : document.body)
watchEffect(() => {
  isPageLocked.value = !hasTwoColumns.value && Boolean(messageStore.currentThreadId)
})

function findThread(threadId) {
  return messageStore.orderedThreads.find((threadMeta) => threadMeta.thread.id === threadId)
}

/**
 * The address says which conversation is open; the page follows it. With nothing asked for, two
 * columns open the newest one beside the list, as before, while a phone stays on the list: opening a
 * conversation marks it read, and nobody chose this one.
 */
function showThreadFromRoute() {
  const threadMeta = route.params.threadId ? findThread(route.params.threadId) : null
  if (threadMeta) {
    if (threadMeta.thread.id !== messageStore.currentThreadId) messageStore.selectThread(threadMeta)
    return
  }

  const newest = messageStore.orderedThreads[0]
  if (hasTwoColumns.value && newest) {
    router.replace({ name: 'app_messages', params: { threadId: newest.thread.id } })
    return
  }
  messageStore.clearCurrentThread()
  // An unknown or blocked conversation goes back to the list rather than keep a dead id in the address.
  if (route.params.threadId) router.replace({ name: 'app_messages' })
}

// Back to the list the reader came from, so the phone's own Back stays inside Messages too.
function handleBack() {
  const listPath = router.resolve({ name: 'app_messages' }).fullPath
  if (window.history.state?.back === listPath) router.back()
  else router.replace({ name: 'app_messages' })
}

// The envelope's « Nouveau message »: straight to the new message window, once. Watched as well as read
// on arrival, since tapping it while already on Messages does not mount the page again.
function openNewMessageFromRoute() {
  if (!route.query.new) return
  showSendModal.value = true
  router.replace({ query: { ...route.query, new: undefined } })
}

// Set once the page is left, so a list that answers after that opens and reads nothing.
let hasLeftPage = false

onMounted(async () => {
  await messageStore.loadThreads()
  if (hasLeftPage) return
  openNewMessageFromRoute()
  showThreadFromRoute()
})

watch(() => route.query.new, openNewMessageFromRoute)

watch(
  () => route.params.threadId,
  () => {
    if (messageStore.hasLoadedThreads) showThreadFromRoute()
  }
)

// A conversation left open would go on being read by every message arriving in it.
onUnmounted(() => {
  hasLeftPage = true
  messageStore.clearCurrentThread()
})
</script>
