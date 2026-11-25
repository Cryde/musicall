<template>
  <!-- Header: what, by whom, where, how much, and a media to play -->
  <section
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-7 grid gap-6 items-center"
    :class="{ 'md:grid-cols-[minmax(0,1fr)_minmax(0,380px)]': heroMedia }"
  >
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <span class="inline-flex items-center gap-2 h-8 pl-2.5 pr-3 rounded-full border border-surface-200 dark:border-surface-700 text-sm font-semibold text-surface-700 dark:text-surface-200">
          <i :class="icon" aria-hidden="true" />{{ kindLabel }}
        </span>
        <Tag v-for="speciality in specialities" :key="speciality" :value="speciality" severity="info" />
      </div>
      <h1 class="text-[26px] md:text-3xl font-extrabold leading-tight text-surface-900 dark:text-surface-0">
        {{ title }}
      </h1>
      <p class="mt-3 flex items-center gap-2 text-sm text-surface-600 dark:text-surface-400">
        <MemberAvatar :page="page" size="w-8! h-8!" />
        <!-- One flex item: the gap would otherwise split the sentence around its name. -->
        <i18n-t keypath="profile.service_page.with" scope="global" tag="span">
          <template #name>
            <strong class="text-surface-900 dark:text-surface-0">{{ page.display_name }}</strong>
          </template>
          <template #profile>
            <router-link
              :to="{ name: 'app_user_public_profile', params: { username: page.username } }"
              class="underline hover:text-primary"
            >{{ t('profile.service_page.see_profile') }}</router-link>
          </template>
        </i18n-t>
      </p>
      <div v-if="facts.length" class="flex flex-wrap gap-x-5 gap-y-1 mt-3 text-sm text-surface-600 dark:text-surface-400">
        <span v-for="fact in facts" :key="fact.key" class="inline-flex items-center gap-1.5">
          <i :class="fact.icon" class="text-xs text-surface-400" aria-hidden="true" />{{ fact.text }}
        </span>
      </div>
      <ServiceKeyNumbers :items="keyNumbers" />
      <div class="flex flex-wrap items-center gap-2 mt-6">
        <Button v-if="isOwnPage" :label="t('profile.service_page.edit')" icon="pi pi-pencil" @click="emit('edit')" />
        <template v-else>
          <Button :label="t('profile.service_page.ask_quote')" icon="pi pi-send" severity="info" size="large" class="max-md:hidden!" @click="emit('quote')" />
          <Button :label="t('profile.teacher_page.ask_question')" icon="pi pi-envelope" severity="secondary" outlined size="large" class="max-md:hidden!" @click="emit('question')" />
          <ShareButton :url="currentUrl()" :title="shareTitle" />
        </template>
      </div>
    </div>

    <ServiceHeroMedia v-if="heroMedia" :media="heroMedia" :label="heroLabel" @play="emit('play', $event)" />
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useI18n } from 'vue-i18n'
import ShareButton from '../../ShareButton.vue'
import MemberAvatar from './MemberAvatar.vue'
import ServiceHeroMedia from './ServiceHeroMedia.vue'
import ServiceKeyNumbers from './ServiceKeyNumbers.vue'

/** A service page's header (#1410, #1411): each kind words its own facts and numbers. */
defineProps({
  page: { type: Object, required: true },
  isOwnPage: { type: Boolean, required: true },
  /** The kind's icon and its colour. */
  icon: { type: String, required: true },
  kindLabel: { type: String, required: true },
  /** Already worded, in the kind's order. */
  specialities: { type: Array, required: true },
  title: { type: String, required: true },
  /** `{ key, icon, text }`: « Studio à Forest », « 8 ans de studio ». */
  facts: { type: Array, required: true },
  /** `{ key, value, label }`, as ServiceKeyNumbers takes them. */
  keyNumbers: { type: Array, required: true },
  /** The first media, played from the header. */
  heroMedia: { type: Object, default: null },
  heroLabel: { type: String, default: '' },
  shareTitle: { type: String, required: true }
})
/** `edit` for its owner; `quote`, `question` and `play` (with the media) for a visitor. */
const emit = defineEmits(['edit', 'quote', 'question', 'play'])

const { t } = useI18n()

/** Shared as it is now: the page can change member while it stays on screen. */
function currentUrl() {
  return window.location.href
}
</script>
