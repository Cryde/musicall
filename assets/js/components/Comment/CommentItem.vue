<template>
  <div class="flex gap-4">
    <div class="shrink-0">
      <Avatar
        v-if="comment.author.profile_picture?.small && !comment.author.deletion_datetime"
        :image="comment.author.profile_picture.small"
        :pt="{ image: { alt: `Photo de ${authorName}` } }"
        shape="circle"
        size="large"
        role="img"
        :aria-label="`Photo de ${authorName}`"
      />
      <Avatar
        v-else
        :label="authorName.charAt(0).toUpperCase()"
        :style="getAvatarStyle(authorName)"
        shape="circle"
        size="large"
        role="img"
        :aria-label="`Avatar de ${authorName}`"
      />
    </div>
    <div class="flex-1">
      <div class="bg-surface-100 dark:bg-surface-800 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="font-semibold text-surface-900 dark:text-surface-0">
            {{ authorName }}
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
import { computed } from 'vue'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const props = defineProps({
  comment: {
    type: Object,
    required: true
  }
})

const authorName = computed(() => displayName(props.comment.author))
</script>
