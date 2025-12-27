<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        Messages
      </h1>
      <div class="hidden sm:block">
        <Button
          label="Nouveau message"
          icon="pi pi-plus"
          @click="showSendModal = true"
        />
      </div>
      <div class="sm:hidden">
        <Button
          icon="pi pi-plus"
          rounded
          @click="showSendModal = true"
        />
      </div>
    </div>

    <div class="bg-surface-0 dark:bg-surface-900 rounded-lg border border-surface-200 dark:border-surface-700 overflow-hidden">
      <div class="flex h-[calc(100vh-12rem)] md:h-[70vh]">
        <!-- Thread list - hidden on mobile when a thread is selected -->
        <div
          class="w-full md:w-1/3 border-r border-surface-200 dark:border-surface-700"
          :class="{ 'hidden md:block': messageStore.currentThreadId }"
        >
          <ProgressSpinner v-if="messageStore.isLoading" class="flex justify-center p-4" />
          <ThreadList v-else />
        </div>

        <!-- Conversation - hidden on mobile when no thread selected -->
        <div
          class="flex-1"
          :class="{ 'hidden md:block': !messageStore.currentThreadId }"
        >
          <Thread @back="handleBack" />
        </div>
      </div>
    </div>

    <SendMessageModal v-model:visible="showSendModal" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import Thread from '../../components/Message/Thread.vue'
import ThreadList from '../../components/Message/ThreadList.vue'
import { useMessageStore } from '../../store/message/message.js'

const route = useRoute()
const router = useRouter()
const messageStore = useMessageStore()
const showSendModal = ref(false)

function handleBack() {
  messageStore.clearCurrentThread()
  router.replace({ name: 'app_messages' })
}

onMounted(async () => {
  await messageStore.loadThreads()
  selectInitialThread()
})

function selectInitialThread() {
  if (messageStore.orderedThreads.length === 0) return

  const threadIdFromUrl = route.params.threadId

  if (threadIdFromUrl) {
    // Find thread matching URL
    const threadMeta = messageStore.orderedThreads.find(
      t => t.thread.id === threadIdFromUrl
    )
    if (threadMeta) {
      messageStore.selectThread(threadMeta)
      return
    }
  }

  // Select first thread and update URL
  const firstThread = messageStore.orderedThreads[0]
  messageStore.selectThread(firstThread)
  router.replace({ name: 'app_messages', params: { threadId: firstThread.thread.id } })
}

// Update URL when thread changes
watch(() => messageStore.currentThreadId, (newThreadId) => {
  if (newThreadId && newThreadId !== route.params.threadId) {
    router.replace({ name: 'app_messages', params: { threadId: newThreadId } })
  }
})

// Handle URL changes (browser back/forward)
watch(() => route.params.threadId, (newThreadId) => {
  if (newThreadId && newThreadId !== messageStore.currentThreadId) {
    const threadMeta = messageStore.orderedThreads.find(
      t => t.thread.id === newThreadId
    )
    if (threadMeta) {
      messageStore.selectThread(threadMeta)
    }
  }
})
</script>
