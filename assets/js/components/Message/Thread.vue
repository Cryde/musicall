<template>
  <div v-if="messageStore.currentThreadId" class="h-full flex flex-col">
    <!-- Header with back button on mobile -->
    <div class="flex items-center gap-3 p-4 border-b border-surface-200 dark:border-surface-700">
      <div class="md:hidden">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          @click="emit('back')"
        />
      </div>
      <Avatar
        v-if="isCurrentChannel"
        icon="pi pi-users"
        :style="getAvatarStyle(title)"
        shape="circle"
        role="img"
        :aria-label="`Discussion du groupe ${title}`"
      />
      <Avatar
        v-else-if="otherParticipant?.profile_picture?.small && !otherParticipant?.deletion_datetime"
        :image="otherParticipant.profile_picture.small"
        :pt="{ image: { alt: `Photo de ${title}` } }"
        shape="circle"
        role="img"
        :aria-label="`Photo de ${title}`"
      />
      <Avatar
        v-else
        :label="title.charAt(0).toUpperCase()"
        :style="getAvatarStyle(title)"
        shape="circle"
        role="img"
        :aria-label="`Avatar de ${title}`"
      />
      <!-- A channel points at the band's own discussion tab, the way a direct message points at the
           other party's profile: the header's name is a way back to where the conversation lives. -->
      <router-link
        v-if="isCurrentChannel"
        :to="{ name: BAND_SPACE_ROUTES.CHAT, params: { id: currentThread.thread.band_space_id } }"
        class="font-semibold text-surface-900 dark:text-surface-0 hover:text-primary transition-colors truncate"
      >{{ title }}</router-link>
      <router-link
        v-else-if="otherParticipant?.username && !otherParticipant?.deletion_datetime"
        :to="{ name: 'app_user_public_profile', params: { username: otherParticipant.username } }"
        class="font-semibold text-surface-900 dark:text-surface-0 hover:text-primary transition-colors"
      >{{ title }}</router-link>
      <span v-else class="font-semibold text-surface-500">
        {{ title }}
      </span>
      <span v-if="isCurrentChannel" class="text-sm text-surface-600 dark:text-surface-400 shrink-0">
        #{{ currentThread.thread.channel_name }}
      </span>
    </div>

    <!-- Messages area -->
    <div
      ref="messagesContainer"
      class="flex-1 overflow-y-auto p-4 space-y-4"
    >
      <ProgressSpinner
        v-if="messageStore.isLoadingMessages"
        class="flex justify-center"
      />

      <template v-else>
        <div
          v-if="messageStore.hasOlderMessages"
          class="flex justify-center"
        >
          <Button
            label="Charger les messages plus anciens"
            severity="secondary"
            outlined
            size="small"
            :loading="messageStore.isLoadingOlderMessages"
            @click="handleLoadOlder"
          />
        </div>
        <Message
          v-if="messageStore.loadOlderError"
          severity="error"
          :closable="false"
        >
          {{ messageStore.loadOlderError }}
        </Message>

        <!-- One block per burst (#1031). The bubbles keep their shape; what stops repeating is the
             name, which only the first of a block carries, and the time, which only the last does. -->
        <template v-for="(block, blockIndex) in messageBlocks" :key="block.messages[0]['@id']">
        <!-- Dated only when the conversation resumes after a silence, so a busy exchange is marked
             once at its start rather than every time the speaker changes. -->
        <p
          v-if="needsTimeSeparator(messageBlocks[blockIndex - 1], block)"
          class="-mb-2 text-center text-xs text-surface-500 dark:text-surface-400"
        >
          <time :datetime="block.messages[0].creation_datetime">
            {{ absoluteDate(block.messages[0].creation_datetime) }}
          </time>
        </p>

        <div
          class="flex flex-col gap-1"
          :class="{ 'items-end': isSender(block.messages[0]) }"
        >
          <div
            v-for="(message, index) in block.messages"
            :key="message['@id']"
            class="flex max-w-[85%] flex-col"
          >
            <div
              class="group/message flex items-center gap-2"
              :class="isSender(message) ? 'flex-row-reverse' : 'flex-row'"
            >
              <div
                class="rounded-2xl px-4 py-2"
                :class="[
                  message.is_deleted
                    ? 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                    : isSender(message)
                      ? 'bg-primary-700 text-white'
                      : 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-0',
                  bubbleCornerClasses(index, block.messages.length, isSender(message)),
                ]"
              >
                <!-- A channel has many authors, so a bubble that is not yours has to say whose it is.
                     A direct message has exactly one other author and the header already names them. -->
                <div
                  v-if="isCurrentChannel && !isSender(message) && index === 0"
                  class="text-xs font-semibold mb-1 opacity-80"
                >
                  {{ message.author?.username }}
                </div>
                <!-- A channel message can be deleted (#967) and comes back with an empty content, so the
                     inbox has to say so too rather than draw an empty bubble. A direct message carries no
                     such flag: there is no endpoint that deletes one. -->
                <p v-if="message.is_deleted" class="text-sm italic">Message supprimé</p>
                <!-- Sent for its attachments alone (#971). This pane draws no attachment card, so it
                     names what is there rather than drawing an empty bubble. -->
                <p v-else-if="message.content === ''" class="text-sm italic opacity-80">Pièce jointe</p>
                <div
                  v-else
                  class="text-sm break-words"
                  :class="isSender(message)
                    ? '[&_a]:text-white [&_a]:underline [&_.chat-mention]:font-semibold [&_.chat-mention]:text-white [&_.chat-mention]:underline [&_.chat-mention]:decoration-white/40'
                    : '[&_a]:text-primary-500 [&_a]:underline [&_.chat-mention]:font-semibold [&_.chat-mention]:text-primary-700 dark:[&_.chat-mention]:text-primary-300'"
                  v-html="autoLink(message.content)"
                />
              </div>
              <!-- Always rendered so hovering shifts nothing, and readable to a screen reader whether
                   or not there is a pointer to hover with. -->
              <time
                :datetime="message.creation_datetime"
                class="shrink-0 whitespace-nowrap rounded-full bg-surface-200 px-2 py-0.5 text-[11px] text-surface-600 opacity-0 transition-opacity delay-0 duration-150 group-hover/message:opacity-100 group-hover/message:delay-1000 dark:bg-surface-700 dark:text-surface-300"
              >
                {{ absoluteDate(message.creation_datetime) }}
              </time>
            </div>
            <!-- A sibling of the bubble, never markup injected into its `v-html`: the preview is
                 built by the client from the link, not sent by the server (#975). -->
            <MusicLinkPreview :content="message.content" :align-end="isSender(message)" />
          </div>
        </div>
        </template>
      </template>
    </div>

    <!-- The band space tab's composer, so a mention is chipped and sent in the same `@[uuid]` format
         from either place. It carries its own border and padding, hence sitting outside the block. -->
    <ChatComposer
      v-if="isCurrentChannel"
      :members="settingsStore.members"
      :send-message="sendToChannel"
      :is-sending="messageStore.isAddingMessage"
      @sent="scrollToBottom"
    />

    <!-- Message input -->
    <div v-else class="border-t border-surface-200 dark:border-surface-700 p-4">
      <p v-if="isRecipientDeleted" class="text-sm text-surface-500 dark:text-surface-400 text-center">
        Vous ne pouvez plus envoyer de message à cet utilisateur.
      </p>
      <template v-else>
        <Message v-if="sendError" severity="error" :closable="true" class="mb-2" @close="sendError = ''">
          {{ sendError }}
        </Message>
        <div class="flex gap-2">
        <Textarea
          ref="messageInput"
          v-model="content"
          :disabled="messageStore.isAddingMessage"
          placeholder="Votre message..."
          rows="2"
          class="flex-1"
          @keydown.enter.exact.prevent="send"
        />
        <Button
          icon="pi pi-send"
          aria-label="Envoyer le message"
          :loading="messageStore.isAddingMessage"
          :disabled="!content.trim() || messageStore.isAddingMessage"
          @click="send"
        />
        </div>
      </template>
    </div>
  </div>

  <div v-else class="hidden md:flex h-full items-center justify-center text-surface-500 dark:text-surface-400 p-4 text-center">
    <div>
      <i class="pi pi-comments text-4xl mb-4 block" />
      <p>Selectionnez une conversation ou envoyez un nouveau message.</p>
    </div>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Textarea from 'primevue/textarea'
import { computed, nextTick, ref, watch } from 'vue'
import ChatComposer from '../../components/BandSpace/Chat/ChatComposer.vue'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import absoluteDate from '../../helper/date/absolute-date.js'
import { useBandSpaceSettingsStore } from '../../store/bandSpace/bandSpaceSettings.js'
import { useMessageStore } from '../../store/message/message.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { autoLink } from '../../utils/autoLink.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { conversationTitle, isChannel } from '../../utils/conversationIdentity.js'
import { bubbleCornerClasses } from '../../utils/messageBubbleCorners.js'
import { groupMessages, needsTimeSeparator } from '../../utils/messageGrouping.js'
import MusicLinkPreview from './MusicLinkPreview.vue'

const emit = defineEmits(['back'])

const messageStore = useMessageStore()
const securityStore = useUserSecurityStore()
// The roster the `@` dropdown filters, from the store the band space tab already fills. It is loaded
// per conversation below rather than once, because switching channel here means switching band.
const settingsStore = useBandSpaceSettingsStore()

const content = ref('')
const messagesContainer = ref(null)
const messageInput = ref(null)
const sendError = ref('')

const currentThread = computed(() => messageStore.currentThread)
const isCurrentChannel = computed(() => isChannel(currentThread.value))

const messageBlocks = computed(() =>
  groupMessages(messageStore.messages, (message) => message.author?.id)
)

const otherParticipant = computed(() => {
  if (!currentThread.value) return null
  return messageStore.getOtherParticipant(currentThread.value)
})

// Shared with the list rather than computed here, which is how the two stopped disagreeing on a
// thread with no participant: this read it as « Conversation » and the list as « Utilisateur
// inconnu » (#994).
const title = computed(() => conversationTitle(currentThread.value, otherParticipant.value))

const isRecipientDeleted = computed(() => !!otherParticipant.value?.deletion_datetime)

function isSender(message) {
  return message.author?.username === securityStore.user?.username
}

function sendToChannel(content) {
  return messageStore.postMessageInThread({ threadId: messageStore.currentThreadId, content })
}

async function send() {
  if (!content.value.trim() || messageStore.isAddingMessage) return

  sendError.value = ''
  try {
    await messageStore.postMessageInThread({
      threadId: messageStore.currentThreadId,
      content: content.value
    })
    trackUmamiEvent('message-send')
    content.value = ''
    scrollToBottom()
    nextTick(() => messageInput.value?.$el?.focus())
  } catch (e) {
    if (e.response?.status === 429) {
      sendError.value = 'Trop de messages envoyés. Veuillez patienter un instant.'
    } else {
      console.error('Failed to send message:', e)
    }
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

/** Within a screenful of the end, which is close enough to count as following the conversation. */
const FOLLOWING_THRESHOLD_PX = 120

function isFollowingTheConversation() {
  const container = messagesContainer.value
  if (!container) {
    return true
  }

  return (
    container.scrollHeight - container.scrollTop - container.clientHeight < FOLLOWING_THRESHOLD_PX
  )
}

/**
 * Loading history keeps the reader where they were.
 *
 * Prepending grows the list upwards, so without compensating, the message being read slides down by
 * however tall the new page is. Measured either side of the DOM update and the difference added back
 * to the offset, which is the whole trick.
 */
async function handleLoadOlder() {
  const container = messagesContainer.value
  const previousScrollHeight = container?.scrollHeight ?? 0
  const previousScrollTop = container?.scrollTop ?? 0

  await messageStore.loadOlderMessages()
  await nextTick()

  if (container) {
    container.scrollTop = previousScrollTop + (container.scrollHeight - previousScrollHeight)
  }
}

// Scroll to bottom when messages change, unless the reader has gone back up. Messages now arrive on
// their own (#989), so yanking the view to the bottom would interrupt somebody rereading history
// rather than follow along with them. Read before the DOM updates, which is what a pre-flush watcher
// gives us, so this is where the reader was rather than where the new content puts them.
//
// Only when the list grew at its *end*. A conversation shorter than its pane has nothing to scroll,
// so isFollowingTheConversation() is trivially true there, and loading history into it would jump to
// the bottom having just been asked for the top. The last message's identity says which end moved.
//
// Reset per thread, because this component is not remounted when you switch conversation: the
// messages route renders through AppBaseLayout's router-view, which carries no `:key`, unlike the
// band space layout. Without this, arriving back at a thread whose last message has not changed since
// you left would read as "nothing grew" and suppress the scroll to the bottom.
let lastTailIri = null
watch(
  () => messageStore.currentThreadId,
  () => {
    lastTailIri = null
    // The roster belongs to the band this conversation is in, so it follows the conversation. Not
    // awaited: a dropdown that is not usable for another moment costs nobody the message they came
    // to read, the same call the band space tab makes on mount.
    const bandSpaceId = currentThread.value?.thread?.band_space_id
    if (bandSpaceId) {
      settingsStore.loadMembers(bandSpaceId).catch(() => {})
    }
  }
)
watch(
  () => messageStore.messages,
  (currentMessages) => {
    const tailIri = currentMessages.at(-1)?.['@id'] ?? null
    const grewAtTheEnd = tailIri !== lastTailIri
    lastTailIri = tailIri

    if (grewAtTheEnd && isFollowingTheConversation()) {
      scrollToBottom()
    }
  },
  { deep: true }
)

// Scroll to bottom when loading finishes
watch(
  () => messageStore.isLoadingMessages,
  (isLoading) => {
    if (!isLoading) {
      scrollToBottom()
    }
  }
)
</script>
