<template>
  <section
    v-if="musician || services.length || isOwner"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    :class="{ [OWNER_CARD_CLASS]: isOwner }"
    aria-labelledby="profile-activities-title"
  >
    <h2 id="profile-activities-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.public.activities.title') }}
    </h2>
    <ul class="flex flex-col gap-2 list-none m-0 p-0">
      <li v-if="musician" class="flex items-center gap-2">
        <router-link
          :to="{ name: 'app_user_public_profile', params: { username }, query: { voir: 'medias' } }"
          class="flex-1 min-w-0 flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
        >
          <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center bg-sky-500/15 text-sky-600 dark:text-sky-400" aria-hidden="true">
            <i class="pi pi-headphones" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0">
              {{ musician.instrumentName || t('profile.public.activities.musician_fallback') }}
            </span>
            <span
              v-if="musician.statusLabel"
              class="block text-xs"
              :class="musician.isAvailable ? 'text-green-700 dark:text-green-400' : 'text-surface-500 dark:text-surface-400'"
            >
              {{ musician.statusLabel }}
            </span>
          </span>
          <i class="pi pi-chevron-right text-xs text-surface-400" aria-hidden="true" />
        </router-link>
        <Button
          v-if="isOwner"
          icon="pi pi-pencil"
          rounded
          size="small"
          :aria-label="t('profile.owner.edit.availability')"
          :title="t('profile.owner.edit.availability')"
          @click="emit('edit-musician')"
        />
      </li>
      <li v-else-if="isOwner">
        <Button
          :label="t('profile.public.create_musician_profile')"
          icon="pi pi-headphones"
          severity="secondary"
          outlined
          size="small"
          class="w-full"
          @click="emit('create-musician')"
        />
      </li>

      <!-- One card per service, teaching included, in the member's order (#1415). -->
      <li v-for="service in services" :key="service.id" class="flex items-center gap-2">
        <router-link
          :to="{ ...SERVICE_KINDS[service.kind].page(username), query: { from: 'profile' } }"
          class="flex-1 min-w-0 flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
        >
          <span
            class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center"
            :class="SERVICE_KINDS[service.kind].iconClass"
            aria-hidden="true"
          >
            <i :class="SERVICE_KINDS[service.kind].icon" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2 min-w-0">
              <span class="text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ service.title }}</span>
              <Tag v-if="service.isDraft" :value="t('profile.services.status.draft')" severity="warn" class="shrink-0" />
            </span>
            <span v-if="service.details" class="block text-xs text-surface-500 dark:text-surface-400">
              {{ service.details }}
            </span>
          </span>
          <i class="pi pi-chevron-right text-xs text-surface-400" aria-hidden="true" />
        </router-link>
        <!-- Every service is edited, published and ordered in « Mes services ». -->
        <Button
          v-if="isOwner"
          as="router-link"
          :to="{ name: 'app_user_services' }"
          icon="pi pi-pencil"
          rounded
          size="small"
          :aria-label="t('profile.owner.edit.services')"
          :title="t('profile.owner.edit.services')"
        />
      </li>
      <li v-if="isOwner && hasKindsToAdd">
        <Button
          :label="t('profile.public.add_service')"
          icon="pi pi-plus"
          severity="secondary"
          outlined
          size="small"
          class="w-full"
          @click="emit('add-service')"
        />
      </li>
    </ul>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SERVICE_KINDS } from '../../../constants/serviceKinds.js'
import { kindsToAdd, serviceName } from '../../../utils/myServices.js'
import {
  musicianActivity,
  serviceCards,
  servicePriceText,
  teacherActivity
} from '../../../utils/profileActivities.js'
import { OWNER_CARD_CLASS } from '../../../utils/profileOwner.js'
import { formatLessonPrice } from '../../../utils/teacherPage.js'

/** « Ce que je fais » (#1317): one line per activity, each opening its page. */
const props = defineProps({
  profile: { type: Object, required: true },
  isOwner: { type: Boolean, default: false }
})

const emit = defineEmits(['create-musician', 'add-service', 'edit-musician'])
const { t, locale } = useI18n()

const username = computed(() => props.profile.username)
const musician = computed(() => musicianActivity(props.profile.musician))
// Only a teaching service online has these: what is taught, and the trial.
const teacher = computed(() => teacherActivity(props.profile.teacher))
const services = computed(() =>
  serviceCards(props.profile.services, props.isOwner).map((card) => ({
    ...card,
    title: serviceTitle(card),
    details: serviceDetails(card)
  }))
)
const hasKindsToAdd = computed(
  () => kindsToAdd(Object.keys(SERVICE_KINDS), props.profile.services ?? []).length > 0
)

const teacherTitle = computed(() => {
  const names = teacher.value.instrumentNames.map((name) => name.toLocaleLowerCase(locale.value))
  if (names.length === 0) return t('profile.public.activities.teacher_fallback')
  const title = t('profile.public.activities.teacher', {
    instruments: new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(names)
  })
  return title.charAt(0).toLocaleUpperCase(locale.value) + title.slice(1)
})

/** Its own headline first; teaching online still reads « Prof de guitare » without one. */
function serviceTitle(card) {
  if (!card.headline && card.kind === 'teaching' && teacher.value) return teacherTitle.value
  return serviceName(card, t)
}

// `teacher` is only there for the teaching service online, the one card it adds its trial to.
function serviceDetails(card) {
  const parts = [servicePriceText(card, t)].filter(Boolean)
  if (card.kind !== 'teaching' || !teacher.value) return parts.join(', ')
  const { trial, trialPrice } = teacher.value
  if (trial === 'free') parts.push(t('profile.public.activities.trial_free'))
  if (trial === 'paid')
    parts.push(t('profile.public.activities.trial_paid', { price: formatLessonPrice(trialPrice) }))
  return parts.join(', ')
}
</script>
