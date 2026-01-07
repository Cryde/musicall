<template>
  <div class="flex gap-4">
    <div class="shrink-0">
      <Avatar
        v-if="comment.author.profile_picture?.small"
        :image="comment.author.profile_picture.small"
        shape="circle"
        size="large"
      />
      <Avatar
        v-else
        :label="comment.author.username.charAt(0).toUpperCase()"
        :style="getAvatarStyle(comment.author.username)"
        shape="circle"
        size="large"
      />
    </div>
    <div class="flex-1">
      <div class="bg-surface-100 dark:bg-surface-800 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="font-semibold text-surface-900 dark:text-surface-0">
            {{ comment.author.username }}
          </span>
          <span class="text-sm text-surface-500 dark:text-surface-400">
            {{ relativeDate(comment.creation_datetime) }}
          </span>
        </div>
        <div
          class="text-surface-700 dark:text-surface-300"
          v-html="comment.content"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import relativeDate from '../../helper/date/relative-date.js'
import { getAvatarStyle } from '../../utils/avatar.js'

defineProps({
  comment: {
    type: Object,
    required: true
  }
})
</script>
