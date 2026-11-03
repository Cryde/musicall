<template>
  <router-link :to="to" class="flex min-w-0 items-center gap-3.5">
    <img
      :src="cover"
      alt=""
      loading="lazy"
      class="h-14 w-24 shrink-0 rounded-lg bg-surface-200 object-cover dark:bg-surface-800"
    />
    <span class="flex min-w-0 flex-1 flex-col">
      <span class="line-clamp-2 text-sm font-semibold text-surface-900 dark:text-surface-0">{{ title }}</span>
      <i18n-t keypath="publication.common.by" tag="span" class="text-sm text-surface-600 dark:text-surface-300">
        <template #author>
          <UserName :username="author.username" :display-name="displayName(author)" variant="tooltip" />
        </template>
      </i18n-t>
    </span>
    <slot name="aside" />
  </router-link>
</template>

<script setup>
import { displayName } from '../../helper/user/displayName.js'
import UserName from '../User/UserName.vue'

/** A publication as one compact line: its cover, its title and who posted it. The caller sets the padding. */
defineProps({
  to: { type: [Object, String], required: true },
  cover: { type: String, default: null },
  title: { type: String, required: true },
  author: { type: Object, required: true }
})
</script>
