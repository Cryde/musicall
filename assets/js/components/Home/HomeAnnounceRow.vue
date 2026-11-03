<template>
  <div class="flex items-center gap-1 pr-2">
    <component
      :is="isDeleted ? 'div' : RouterLink"
      v-bind="isDeleted ? {} : { to: announceAuthorRoute(announce) }"
      class="flex min-w-0 flex-1 items-center gap-3 py-3 pl-4"
    >
      <!-- 40px through PrimeVue's avatar tokens: check the size again after a PrimeVue upgrade. -->
      <HomeAnnounceAvatar
        :author="announce.author"
        size="normal"
        class="[--p-avatar-height:2.5rem] [--p-avatar-width:2.5rem]"
      />
      <span class="flex min-w-0 flex-col">
        <span class="line-clamp-2 text-[15px] leading-snug font-semibold text-surface-900 dark:text-surface-0">
          {{ announceHeadline(announce) }}
        </span>
        <span class="truncate text-[13px] text-surface-600 dark:text-surface-300">{{ meta }}</span>
      </span>
    </component>
    <Button
      v-if="canContact"
      icon="pi pi-envelope"
      severity="secondary"
      text
      rounded
      :aria-label="t('home.announce_card.contact_author', { name: authorName })"
      @click="$emit('contact', announce)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import {
  announceAuthorRoute,
  announceHeadline,
  announceRowMeta,
  canContactAnnounceAuthor
} from '../../utils/homeSearch.js'
import HomeAnnounceAvatar from './HomeAnnounceAvatar.vue'

/**
 * An announce as one line of the homepage list on a phone: what is sought, then who, where and when.
 * The card's styles, note and report stay on the search page, one tap away through « Tout voir ».
 */
const props = defineProps({
  announce: { type: Object, required: true }
})

defineEmits(['contact'])

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()

const authorName = computed(() => displayName(props.announce.author))
const isDeleted = computed(() => !!props.announce.author.deletion_datetime)
const canContact = computed(() =>
  canContactAnnounceAuthor(props.announce, userSecurityStore.userProfile?.id)
)
const meta = computed(() =>
  announceRowMeta([
    authorName.value,
    props.announce.location_name,
    props.announce.creation_datetime
      ? relativeDate(props.announce.creation_datetime, { showHours: false })
      : null
  ])
)
</script>
