<template>
  <div v-if="comments.length > 0" class="flex flex-col gap-3">
    <div
      v-for="comment in comments"
      :key="comment.id"
      class="flex gap-3"
    >
      <div class="flex-shrink-0">
        <Avatar
          :username="comment.author_username"
          :picture-url="comment.author_profile_picture_url"
          size="md"
        />
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ comment.author_username }}
          </span>
          <span class="text-xs text-surface-400">
            {{ formatRelative(comment.creation_datetime) }}
          </span>
        </div>
        <p class="text-sm text-surface-600 dark:text-surface-300 mt-0.5 whitespace-pre-wrap">
          <template v-for="(part, index) in parts(comment.content)" :key="index">
            <span v-if="part.type === 'mention'" class="text-primary font-semibold">@{{ part.username }}</span>
            <template v-else>{{ part.value }}</template>
          </template>
        </p>
      </div>
    </div>
  </div>
  <p v-else class="text-sm text-surface-400 italic">Aucun commentaire</p>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import { useMentionParser } from '../../../composables/useMentionParser.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  comments: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] }
})

const { parseToParts } = useMentionParser()

function parts(content) {
  return parseToParts(content, props.members)
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: fr })
}
</script>
