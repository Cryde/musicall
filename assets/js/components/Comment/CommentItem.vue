<template>
  <div class="flex gap-4">
    <div class="shrink-0">
      <Avatar
        v-if="comment.author.profile_picture?.small && !comment.author.deletion_datetime"
        :image="comment.author.profile_picture.small"
        :pt="{ image: { alt: `Photo de ${authorName}` } }"
        shape="circle"
        :size="isReply ? 'normal' : 'large'"
        role="img"
        :aria-label="`Photo de ${authorName}`"
      />
      <Avatar
        v-else
        :label="authorName.charAt(0).toUpperCase()"
        :style="getAvatarStyle(authorName)"
        shape="circle"
        :size="isReply ? 'normal' : 'large'"
        role="img"
        :aria-label="`Avatar de ${authorName}`"
      />
    </div>
    <div class="flex-1 min-w-0">
      <div class="bg-surface-100 dark:bg-surface-800 rounded-lg p-4">
        <div class="flex items-center flex-wrap gap-x-2 mb-2">
          <span class="font-semibold text-surface-900 dark:text-surface-0">
            {{ authorName }}
          </span>
          <span
            v-if="isReply && parentAuthorName"
            class="text-sm text-surface-500 dark:text-surface-400"
          >
            en réponse à
            <span class="font-medium text-surface-700 dark:text-surface-300">@{{ parentAuthorName }}</span>
          </span>
          <span class="text-sm text-surface-500 dark:text-surface-400">
            {{ relativeDate(comment.creation_datetime) }}
          </span>
        </div>
        <div
          class="text-surface-700 dark:text-surface-300"
          v-html="comment.content"
        />
        <div class="flex items-center gap-1 mt-2">
          <Button
            icon="pi pi-thumbs-up"
            text
            rounded
            size="small"
            :severity="localUserVote === 1 ? 'success' : 'secondary'"
            :loading="isVoting"
            aria-label="J'aime"
            @click="handleVote(1)"
          />
          <span class="text-sm font-semibold min-w-6 text-center">{{ score }}</span>
          <Button
            icon="pi pi-thumbs-down"
            text
            rounded
            size="small"
            :severity="localUserVote === -1 ? 'danger' : 'secondary'"
            :loading="isVoting"
            aria-label="Je n'aime pas"
            @click="handleVote(-1)"
          />
          <Button
            v-if="!isReply && userSecurityStore.isAuthenticated"
            icon="pi pi-reply"
            label="Répondre"
            text
            size="small"
            severity="secondary"
            class="ml-2"
            @click="toggleReplyForm"
          />
        </div>
      </div>

      <div v-if="!isReply" class="ml-4 mt-3 flex flex-col gap-3">
        <CommentForm
          v-if="showReplyForm"
          :parent-id="comment.id"
          :placeholder="`Répondre à ${authorName}...`"
          :autofocus="true"
          @posted="showReplyForm = false"
          @cancel="showReplyForm = false"
        />

        <div v-if="replies.length > 0" class="flex flex-col gap-3">
          <button
            v-if="replies.length > REPLIES_INLINE_THRESHOLD && !showAllReplies"
            type="button"
            class="self-start text-sm text-primary hover:underline flex items-center gap-1"
            @click="showAllReplies = true"
          >
            <i class="pi pi-chevron-down text-xs" />
            Voir les {{ replies.length }} réponses
          </button>

          <template v-if="showAllReplies || replies.length <= REPLIES_INLINE_THRESHOLD">
            <CommentItem
              v-for="reply in replies"
              :key="reply.id"
              :comment="reply"
              :replies="[]"
              :is-reply="true"
              :parent-author-name="authorName"
            />

            <button
              v-if="replies.length > REPLIES_INLINE_THRESHOLD"
              type="button"
              class="self-start text-sm text-surface-500 hover:underline flex items-center gap-1"
              @click="showAllReplies = false"
            >
              <i class="pi pi-chevron-up text-xs" />
              Masquer les réponses
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import commentApi from '../../api/comment/comment.js'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import CommentForm from './CommentForm.vue'

const REPLIES_INLINE_THRESHOLD = 3

const props = defineProps({
  comment: { type: Object, required: true },
  replies: { type: Array, default: () => [] },
  isReply: { type: Boolean, default: false },
  parentAuthorName: { type: String, default: null }
})

const userSecurityStore = useUserSecurityStore()

const authorName = computed(() => displayName(props.comment.author))

const localUpvotes = ref(props.comment.upvotes ?? 0)
const localDownvotes = ref(props.comment.downvotes ?? 0)
const localUserVote = ref(props.comment.user_vote ?? null)
const isVoting = ref(false)

const showReplyForm = ref(false)
const showAllReplies = ref(false)

const score = computed(() => localUpvotes.value - localDownvotes.value)

function toggleReplyForm() {
  showReplyForm.value = !showReplyForm.value
}

async function handleVote(value) {
  if (isVoting.value) return
  isVoting.value = true
  try {
    const data = await commentApi.voteComment(props.comment.id, value)
    localUpvotes.value = data.upvotes
    localDownvotes.value = data.downvotes
    localUserVote.value = data.user_vote
  } finally {
    isVoting.value = false
  }
}
</script>
