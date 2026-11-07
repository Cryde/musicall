<template>
  <section
    v-if="checklist.percent < 100"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    aria-labelledby="profile-checklist-title"
  >
    <h2 id="profile-checklist-title" class="m-0 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ title }}
    </h2>
    <div
      class="h-2 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden my-3"
      role="progressbar"
      :aria-valuenow="checklist.percent"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="title"
    >
      <span class="block h-full bg-primary" :style="{ width: `${checklist.percent}%` }" />
    </div>
    <ul class="flex flex-col gap-1 list-none m-0 p-0 text-sm">
      <li v-for="item in checklist.items" :key="item.key">
        <span v-if="item.done" class="flex items-center gap-2 py-1 text-surface-500 dark:text-surface-400">
          <i class="pi pi-check-circle text-green-600 dark:text-green-400" aria-hidden="true" />
          {{ t(ITEM_LABELS[item.key]) }}
          <span class="sr-only">{{ t('profile.owner.checklist.done') }}</span>
        </span>
        <router-link
          v-else-if="item.key === 'availability'"
          :to="{ name: 'app_user_teacher_profile', params: { username: profile.username } }"
          :class="TODO_CLASS"
        >
          <i class="pi pi-circle" aria-hidden="true" />{{ t(ITEM_LABELS[item.key]) }}
        </router-link>
        <button v-else type="button" :class="TODO_CLASS" @click="emit('act', item.key)">
          <i class="pi pi-circle" aria-hidden="true" />{{ t(ITEM_LABELS[item.key]) }}
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { profileChecklist } from '../../../utils/profileChecklist.js'

/**
 * « Ton profil est complet à N % » (#1317): what the owner's profile still lacks, each item opening where it is
 * filled. Gone once everything is done.
 */
const props = defineProps({
  profile: { type: Object, required: true }
})

const emit = defineEmits(['act'])
const { t } = useI18n()

const ITEM_LABELS = {
  photos: 'profile.owner.checklist.item.photos',
  bio: 'profile.owner.checklist.item.bio',
  instruments: 'profile.owner.checklist.item.instruments',
  media: 'profile.owner.checklist.item.media',
  post: 'profile.owner.checklist.item.post',
  availability: 'profile.owner.checklist.item.availability'
}
const TODO_CLASS =
  'flex w-full items-center gap-2 py-1 px-0 text-left font-semibold text-primary no-underline hover:underline cursor-pointer bg-transparent border-0'

const checklist = computed(() => profileChecklist(props.profile))
const title = computed(() =>
  t('profile.owner.checklist.title', { percent: checklist.value.percent })
)
</script>
