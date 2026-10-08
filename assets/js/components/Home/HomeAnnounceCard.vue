<template>
  <article
    class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
  >
    <div class="flex items-center gap-3">
      <Avatar
        v-if="announce.author.profile_picture_url && !isDeleted"
        :image="announce.author.profile_picture_url"
        :pt="{ image: { alt: '' } }"
        shape="circle"
        size="large"
        class="shrink-0"
      />
      <Avatar
        v-else
        :label="authorName.charAt(0).toUpperCase()"
        :style="getAvatarStyle(announce.author.username)"
        shape="circle"
        size="large"
        class="shrink-0"
        aria-hidden="true"
      />
      <div class="flex flex-col min-w-0 flex-1">
        <UserName
          v-if="!isDeleted"
          :username="announce.author.username"
          :display-name="authorName"
          :to="profileRoute"
          class="font-semibold truncate text-surface-900 dark:text-surface-0 hover:text-primary"
        />
        <span v-else class="font-semibold truncate text-surface-600 dark:text-surface-300">{{ authorName }}</span>
        <span v-if="announce.creation_datetime" class="text-sm text-surface-600 dark:text-surface-300">
          {{ relativeDate(announce.creation_datetime, { showHours: false }) }}
        </span>
      </div>
      <span
        class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
        :class="
          announce.type === TYPES_ANNOUNCE_MUSICIAN
            ? 'bg-teal-100 text-teal-800 dark:bg-teal-400/15 dark:text-teal-300'
            : 'bg-primary-100 text-primary-800 dark:bg-primary-400/15 dark:text-primary-300'
        "
      >
        {{ announceKindLabel(announce) }}
      </span>
    </div>

    <h3 class="m-0 text-lg font-bold text-surface-900 dark:text-surface-0">{{ announceHeadline(announce) }}</h3>

    <ul v-if="styleTags.tags.length > 0" class="m-0 p-0 list-none flex flex-wrap gap-1.5" aria-label="Styles">
      <li
        v-for="tag in styleTags.tags"
        :key="tag"
        class="rounded-full bg-surface-100 dark:bg-surface-800 px-2.5 py-1 text-sm text-surface-700 dark:text-surface-200"
      >
        {{ tag }}
      </li>
      <!-- A button so the other styles show on focus as well as on hover, and are read out. -->
      <li v-if="styleTags.hidden.length > 0">
        <button
          v-tooltip.top="styleTags.hidden.join(', ')"
          type="button"
          class="rounded-full bg-surface-100 dark:bg-surface-800 px-2.5 py-1 text-sm text-surface-700 dark:text-surface-200 cursor-help"
          :aria-label="`Et aussi : ${styleTags.hidden.join(', ')}`"
        >
          +{{ styleTags.hidden.length }}
        </button>
      </li>
    </ul>

    <div
      v-if="!compact"
      class="mt-auto flex items-center justify-between gap-3 border-t border-surface-200 dark:border-surface-700 pt-3"
    >
      <span class="flex items-center gap-1.5 text-sm text-surface-600 dark:text-surface-300 min-w-0">
        <i class="pi pi-map-marker text-xs" aria-hidden="true" />
        <span class="truncate">{{ announce.location_name }}</span>
      </span>
      <div class="flex shrink-0 items-center gap-1">
        <Button
          v-if="canContact"
          size="small"
          icon="pi pi-envelope"
          label="Contacter"
          severity="secondary"
          text
          :aria-label="`Contacter ${authorName}`"
          @click="$emit('contact', announce)"
        />
        <AnnounceNoteButton :note="announce.note" :author-name="authorName" />
        <ReportButton
          v-if="!isDeleted"
          target-type="announce"
          :target-id="announce.id"
          :author-id="announce.author.id"
          :aria-label="`Signaler l'annonce de ${authorName}`"
          icon-only
        />
      </div>
    </div>
  </article>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import { computed } from 'vue'
import { TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { announceHeadline, announceKindLabel, announceStyleTags } from '../../utils/homeSearch.js'
import AnnounceNoteButton from '../Announce/AnnounceNoteButton.vue'
import ReportButton from '../Report/ReportButton.vue'
import UserName from '../User/UserName.vue'

/** One musician announce as the homepage shows it (#1074); `compact` is the hero's preview. */
const props = defineProps({
  announce: { type: Object, required: true },
  compact: { type: Boolean, default: false }
})

defineEmits(['contact'])

const userSecurityStore = useUserSecurityStore()

const authorName = computed(() => displayName(props.announce.author))
const isDeleted = computed(() => !!props.announce.author.deletion_datetime)
const styleTags = computed(() => announceStyleTags(props.announce))
const canContact = computed(
  () => !isDeleted.value && userSecurityStore.userProfile?.id !== props.announce.author.id
)
const profileRoute = computed(() => ({
  name: props.announce.author.has_musician_profile
    ? 'app_user_musician_profile'
    : 'app_user_public_profile',
  params: { username: props.announce.author.username },
  // For the profile's contextual « back » link.
  query: { from: 'home' }
}))
</script>
