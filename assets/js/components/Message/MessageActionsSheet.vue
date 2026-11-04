<template>
  <BottomSheet v-model:visible="visible" :title="t('message.actions.open')">
    <template #header>
      <time
        v-if="message"
        :datetime="message.creation_datetime"
        class="text-sm text-surface-600 dark:text-surface-400"
      >
        {{ absoluteDate(message.creation_datetime) }}
      </time>
    </template>

    <template v-if="message">
      <div class="flex justify-between gap-1 px-4 pb-3" role="group" :aria-label="t('band_space_chat.reaction.choose')">
        <button
          v-for="choice in CHAT_REACTIONS"
          :key="choice.key"
          type="button"
          class="inline-flex size-10 items-center justify-center rounded-full text-xl transition-colors"
          :class="hasReacted(message.reactions, choice.key) ? 'bg-primary-100 ring-2 ring-primary dark:bg-primary-950' : 'bg-surface-100 hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700'"
          :aria-label="chatReactionLabel(choice.key)"
          :aria-pressed="hasReacted(message.reactions, choice.key)"
          @click="act(() => emit('react', choice.key))"
        >
          <span aria-hidden="true">{{ choice.emoji }}</span>
        </button>
      </div>

      <ul class="m-0 list-none divide-y divide-surface-200 border-t border-surface-200 p-0 dark:divide-surface-700 dark:border-surface-700">
        <li v-if="text">
          <SheetAction icon="pi-copy" @click="act(copy)">{{ t('message.actions.copy') }}</SheetAction>
        </li>
        <li v-if="canEdit">
          <SheetAction icon="pi-pencil" @click="act(() => emit('edit'))">{{ t('band_space_chat.message_list.edit') }}</SheetAction>
        </li>
        <li v-if="canPin">
          <SheetAction
            :icon="message.is_pinned ? 'pi-bookmark-fill' : 'pi-bookmark'"
            :disabled="isPinPending"
            @click="act(() => emit('pin'))"
          >
            {{ message.is_pinned ? t('band_space_chat.pin.unpin') : t('band_space_chat.pin.pin') }}
          </SheetAction>
        </li>
        <li v-if="canCreateTask">
          <SheetAction icon="pi-check-square" :disabled="isTaskPending" @click="act(() => emit('create-task'))">
            {{ t('band_space_chat.message_list.create_task') }}
          </SheetAction>
        </li>
        <li v-if="canReport">
          <SheetAction icon="pi-flag" danger @click="act(() => emit('report'))">{{ t('band_space_chat.message_list.report') }}</SheetAction>
        </li>
        <!-- Last, as in the app: the one action that cannot be taken back. -->
        <li v-if="canDelete">
          <SheetAction icon="pi-trash" danger @click="act(() => emit('delete'))">{{ t('band_space_chat.message_list.delete') }}</SheetAction>
        </li>
      </ul>
    </template>
  </BottomSheet>
</template>

<script setup>
import { useToast } from 'primevue/usetoast'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CHAT_REACTIONS, chatReactionLabel } from '../../constants/chatReactions.js'
import absoluteDate from '../../helper/date/absolute-date.js'
import { hasReacted } from '../../utils/chatReactionToggle.js'
import { htmlToText } from '../../utils/messageText.js'
import BottomSheet from '../Global/BottomSheet.vue'
import SheetAction from '../Global/SheetAction.vue'

/**
 * What a long press on a message opens where there is no hover, in a direct conversation and in the
 * band chat: the reactions, then copy, edit, pin, make a task, report and delete as the message allows,
 * in the Android app's order. The conversation does the work; this only asks.
 */
const props = defineProps({
  message: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  canReport: { type: Boolean, default: false },
  // The band chat only.
  canPin: { type: Boolean, default: false },
  isPinPending: { type: Boolean, default: false },
  canCreateTask: { type: Boolean, default: false },
  isTaskPending: { type: Boolean, default: false }
})

const emit = defineEmits(['react', 'edit', 'pin', 'create-task', 'report', 'delete'])

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const toast = useToast()

// The rendered text, mentions as names: the band chat's `editable_content` keeps its `@[uuid]` tokens.
const text = computed(() => {
  if (!props.message || props.message.is_deleted) return ''
  return htmlToText(props.message.content)
})

async function copy() {
  try {
    await navigator.clipboard.writeText(text.value)
    toast.add({ severity: 'success', summary: t('message.actions.copied'), life: 2000 })
  } catch {
    toast.add({ severity: 'error', summary: t('message.actions.copy_failed'), life: 3000 })
  }
}

function act(action) {
  visible.value = false
  action()
}
</script>
