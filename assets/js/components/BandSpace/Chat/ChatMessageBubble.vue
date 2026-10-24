<template>
  <div>
    <!-- Side by side where the list is wide, as on the Band Space tab. Where it is narrow (a phone, or
         the « Messages » pane) the actions go under the bubble rather than past the edge (#1251). The
         list holding the bubble is the `@container` this asks. -->
    <div
      class="flex items-center gap-2 @max-4xl:flex-wrap"
      :class="isMine ? 'flex-row-reverse' : 'flex-row'"
    >
      <!-- The edit box, which the list owns, takes the bubble's place so the conversation keeps its
           shape around it (#966). -->
      <slot v-if="isEditing" />

      <template v-else>
        <!-- A tombstone keeps its place in the block, muted and never coloured by side: what is left
             is the fact that somebody wrote here, not the message (#967). -->
        <div
          v-if="message.is_deleted"
          class="inline-block rounded-2xl bg-surface-100 px-4 py-2 text-sm italic text-surface-600 dark:bg-surface-800 dark:text-surface-300"
          :class="cornerClasses"
        >
          {{ t('band_space_chat.message_list.deleted') }}
        </div>
        <div
          v-else
          class="inline-block min-w-0 max-w-full rounded-2xl px-4 py-2 text-sm break-words text-left"
          :class="[
            isMine
              ? 'bg-primary-700 text-white [&_a]:text-white [&_a]:underline [&_.chat-mention]:font-semibold [&_.chat-mention]:text-white [&_.chat-mention]:underline [&_.chat-mention]:decoration-white/40'
              : 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-0 [&_a]:text-primary-500 [&_a]:underline [&_.chat-mention]:font-semibold [&_.chat-mention]:text-primary-700 dark:[&_.chat-mention]:text-primary-300',
            cornerClasses,
          ]"
        >
          <ChatMessageImage v-if="message.image && imageSrc" :image="message.image" :src="imageSrc" />
          <ChatMessageVoiceNote v-if="message.voice_note && loadVoiceNote" :voice-note="message.voice_note" :fetch-blob="loadVoiceNote" />
          <!-- An attachment-only message has no text (#971), and an empty line here would pad the
               bubble above its cards. -->
          <div v-if="message.content" v-html="autoLink(message.content)" />
          <slot name="attachments" />
        </div>
        <div
          class="flex shrink-0 items-center gap-1"
          :class="{ 'flex-row-reverse': isMine }"
        >
          <!-- Shown without hovering, unlike the time beside it: that an edit happened is part of what
               the message says, not a detail you go looking for. -->
          <span
            v-if="message.update_datetime && !message.is_deleted"
            class="whitespace-nowrap text-[11px] italic text-surface-500 dark:text-surface-400"
          >
            {{ t('band_space_chat.message_list.edited') }}
          </span>
          <!-- Always rendered so hovering shifts nothing, and readable to a screen reader whether or
               not there is a pointer to hover with. -->
          <time
            :datetime="message.creation_datetime"
            class="shrink-0 whitespace-nowrap rounded-full bg-surface-200 px-2 py-0.5 text-[11px] text-surface-600 opacity-0 transition-opacity delay-0 duration-150 group-hover/message:opacity-100 group-hover/message:delay-1000 dark:bg-surface-700 dark:text-surface-300"
          >
            {{ absoluteDate(message.creation_datetime) }}
          </time>
          <!-- Reserves its space like the time above, so the row never moves, but reveals at once
               rather than after a second: this one is there to be clicked. A pinned message keeps its
               marker visible, because the bar says what is pinned and this says which one. -->
          <button
            v-if="canPin && !message.is_deleted"
            type="button"
            class="shrink-0 rounded-full bg-surface-200 px-2 py-0.5 text-[11px] text-surface-600 transition-opacity duration-150 hover:text-primary-700 focus-visible:opacity-100 disabled:opacity-50 group-hover/message:opacity-100 dark:bg-surface-700 dark:text-surface-300 dark:hover:text-primary-300"
            :class="message.is_pinned ? 'opacity-100' : 'opacity-0'"
            :disabled="isPinPending"
            :aria-label="pinLabel"
            @click="emit('pin')"
          >
            <i
              class="pi"
              :class="message.is_pinned ? 'pi-bookmark-fill' : 'pi-bookmark'"
              aria-hidden="true"
            />
          </button>
          <!-- « Ça, c'est une tâche » (#979), open to every member rather than to the author: the one
               who knows the XLR cable will be forgotten is rarely the one who mentioned it. -->
          <button
            v-if="canCreateTask && !message.is_deleted"
            type="button"
            class="shrink-0 rounded-full p-1 text-surface-500 transition-opacity duration-150 hover:text-primary disabled:opacity-50 dark:text-surface-400 lg:opacity-0 lg:focus-visible:opacity-100 lg:group-hover/message:opacity-100"
            :disabled="isTaskPending"
            :aria-label="t('band_space_chat.message_list.create_task')"
            @click="emit('create-task')"
          >
            <i class="pi pi-check-square text-xs" aria-hidden="true" />
          </button>
          <button
            v-if="canReport && !isMine && !message.is_deleted"
            type="button"
            class="shrink-0 rounded-full p-1 text-surface-500 transition-opacity duration-150 hover:text-red-600 focus-visible:opacity-100 lg:opacity-0 lg:group-hover/message:opacity-100 dark:text-surface-400 dark:hover:text-red-400"
            :aria-label="t('band_space_chat.message_list.report')"
            @click="emit('report')"
          >
            <i class="pi pi-flag text-xs" aria-hidden="true" />
          </button>
          <!-- Hidden until hover only from `lg` up: a touch screen has no hover, so a pencil that only
               appeared on one would be unreachable. Opacity rather than display, so revealing it shifts
               nothing, and on focus too, or a keyboard lands on a control nothing on screen explains. -->
          <button
            v-if="canEdit && !message.is_deleted"
            type="button"
            class="shrink-0 rounded-full p-1 text-surface-500 transition-opacity duration-150 hover:text-primary dark:text-surface-400 lg:opacity-0 lg:focus-visible:opacity-100 lg:group-hover/message:opacity-100"
            :aria-label="t('band_space_chat.message_list.edit')"
            @click="emit('edit')"
          >
            <i class="pi pi-pencil text-xs" aria-hidden="true" />
          </button>
          <button
            v-if="canDelete"
            type="button"
            class="shrink-0 rounded-full p-1 text-surface-500 transition-opacity duration-150 hover:text-red-600 focus-visible:opacity-100 lg:opacity-0 lg:group-hover/message:opacity-100 dark:text-surface-300 dark:hover:text-red-400"
            :aria-label="t('band_space_chat.message_list.delete')"
            @click="emit('delete')"
          >
            <i class="pi pi-trash text-xs" aria-hidden="true" />
          </button>
        </div>
      </template>
    </div>

    <!-- A sibling of the bubble, never markup injected into its `v-html`: the preview is built by the
         client from the link, not sent by the server (#975). -->
    <MusicLinkPreview :content="message.content" :align-end="isMine" />

    <ChatMessageReactions
      v-if="reactable"
      :reactions="message.reactions"
      :align-end="isMine"
      @toggle="(emojiKey) => emit('react', emojiKey)"
    />
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import absoluteDate from '../../../helper/date/absolute-date.js'
import { autoLink } from '../../../utils/autoLink.js'
import MusicLinkPreview from '../../Message/MusicLinkPreview.vue'
import ChatMessageImage from './ChatMessageImage.vue'
import ChatMessageReactions from './ChatMessageReactions.vue'
import ChatMessageVoiceNote from './ChatMessageVoiceNote.vue'

/**
 * One message of a conversation, the band chat's or a direct one (#1244). No store inside: the list
 * that holds it says what the viewer may do and answers what they did. The list also wraps it in a
 * `group/message` element, which the hover reveals of the time and the buttons key on, and is itself
 * an `@container`, which decides whether the actions fit beside the bubble.
 */
defineProps({
  message: { type: Object, required: true },
  isMine: { type: Boolean, default: false },
  /** From bubbleCornerClasses(), which needs the message's place in its block. */
  cornerClasses: { type: String, default: '' },
  isEditing: { type: Boolean, default: false },
  imageSrc: { type: String, default: null },
  /** Fetches a voice note as a blob; null where the conversation has none. */
  loadVoiceNote: { type: Function, default: null },
  reactable: { type: Boolean, default: false },
  canPin: { type: Boolean, default: false },
  isPinPending: { type: Boolean, default: false },
  pinLabel: { type: String, default: '' },
  canCreateTask: { type: Boolean, default: false },
  isTaskPending: { type: Boolean, default: false },
  canReport: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false }
})

const emit = defineEmits(['pin', 'create-task', 'report', 'edit', 'delete', 'react'])

const { t } = useI18n()
</script>
