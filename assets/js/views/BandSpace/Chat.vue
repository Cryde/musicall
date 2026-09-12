<template>
  <div
    class="bg-surface-0 dark:bg-surface-900 rounded-2xl overflow-hidden flex flex-col h-[calc(100vh-16rem)] min-h-[400px]"
  >
    <div
      v-if="chatStore.loadError"
      class="flex flex-col items-center justify-center flex-1 p-8 gap-4"
    >
      <Message severity="error" :closable="false">{{ chatStore.loadError }}</Message>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="load" />
    </div>

    <div v-else-if="chatStore.isLoading" class="flex-1 flex items-center justify-center p-8">
      <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
    </div>

    <div
      v-else-if="chatStore.messages.length === 0"
      class="flex flex-col items-center justify-center flex-1 text-center p-8"
    >
      <i class="pi pi-comments text-4xl text-surface-400 dark:text-surface-500" aria-hidden="true" />
      <p class="mt-3 font-medium">Aucun message</p>
      <p class="mt-1 text-surface-600 dark:text-surface-300">
        Écrivez le premier message du groupe, il restera ici pour tout le monde.
      </p>
    </div>

    <ChatMessageList v-else ref="messageList" :band-space-id="bandSpaceId" />

    <ChatComposer :band-space-id="bandSpaceId" @sent="messageList?.scrollToBottom()" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { onMounted, onUnmounted, useTemplateRef } from 'vue'
import { useRoute } from 'vue-router'
import ChatComposer from '../../components/BandSpace/Chat/ChatComposer.vue'
import ChatMessageList from '../../components/BandSpace/Chat/ChatMessageList.vue'
import { useBandSpaceChatStore } from '../../store/bandSpace/bandSpaceChat.js'

const route = useRoute()
// Read once: AppBandLayout keys <router-view> on the space id, so this view is remounted rather than
// reused when the member switches band.
const bandSpaceId = route.params.id

const chatStore = useBandSpaceChatStore()
const messageList = useTemplateRef('messageList')

// Synchronously, before the first render, so another band's conversation never flashes here.
chatStore.clear()

async function load() {
  await chatStore.loadMessages(bandSpaceId)
  // Arriving on the tab is reading it, like the direct message inbox does on selecting a thread.
  // After the load rather than before, so a failed load does not claim the member read anything.
  if (!chatStore.loadError) {
    await chatStore.markAsRead(bandSpaceId)
  }
}

onMounted(load)
onUnmounted(() => chatStore.clear())
</script>
