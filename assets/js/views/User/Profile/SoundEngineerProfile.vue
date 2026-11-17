<template>
  <div class="py-6 md:py-10" :class="{ 'max-md:pb-28': page && !isOwnPage }">
    <ServicePageStates
      :is-loading="isLoading"
      :is-private="isPrivate"
      :not-found="notFound"
      :not-found-title="t('profile.sound_engineer_page.not_found_title')"
      :not-found-help="t('profile.sound_engineer_page.not_found_help')"
      :username="route.params.username"
    >
      <div v-if="page" class="flex flex-col gap-5 md:gap-6">
        <ProfilePrivateBar v-if="isOwnPage && !page.is_public" @made-public="refreshPage" />
        <ProfileDraftBar v-if="isOwnPage && page.status === 'draft'" />
        <ServicePageTopBar
          :username="page.username"
          :came-from-profile="route.query.from === 'profile'"
          :back-to-profile-label="t('profile.teacher_page.back_to_profile', { name: page.display_name })"
          :breadcrumb-label="t('profile.teacher_page.breadcrumb')"
          :crumbs="crumbs"
          @back="handleBack"
        />

        <!-- Header: what, by whom, where, how much, and something to hear -->
        <section
          class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-7 grid gap-6 items-center"
          :class="{ 'md:grid-cols-[minmax(0,1fr)_minmax(0,380px)]': heroMedia }"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-3">
              <span class="inline-flex items-center gap-2 h-8 pl-2.5 pr-3 rounded-full border border-surface-200 dark:border-surface-700 text-sm font-semibold text-surface-700 dark:text-surface-200">
                <i class="pi pi-sliders-v text-violet-500" aria-hidden="true" />{{ t('profile.sound_engineer_page.kind') }}
              </span>
              <Tag v-for="speciality in specialities" :key="speciality" :value="t(SPECIALITY_KEYS[speciality])" severity="info" />
            </div>
            <h1 class="text-[26px] md:text-3xl font-extrabold leading-tight text-surface-900 dark:text-surface-0">
              {{ page.headline || t('profile.sound_engineer_page.title_default', { name: page.display_name }) }}
            </h1>
            <p class="mt-3 flex items-center gap-2 text-sm text-surface-600 dark:text-surface-400">
              <MemberAvatar :page="page" size="w-8! h-8!" />
              <!-- One flex item: the gap would otherwise split the sentence around its name. -->
              <i18n-t keypath="profile.sound_engineer_page.with" scope="global" tag="span">
                <template #name>
                  <strong class="text-surface-900 dark:text-surface-0">{{ page.display_name }}</strong>
                </template>
                <template #profile>
                  <router-link
                    :to="{ name: 'app_user_public_profile', params: { username: page.username } }"
                    class="underline hover:text-primary"
                  >{{ t('profile.sound_engineer_page.see_profile') }}</router-link>
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
              <Button v-if="isOwnPage" :label="t('profile.sound_engineer_page.edit')" icon="pi pi-pencil" @click="showForm = true" />
              <template v-else>
                <Button :label="t('profile.teacher_page.ask_question')" icon="pi pi-envelope" severity="info" size="large" class="max-md:hidden!" @click="openMessage('')" />
                <ShareButton :url="shareUrl" :title="t('profile.sound_engineer_page.share_title', { name: page.display_name })" />
              </template>
            </div>
          </div>

          <ServiceHeroMedia v-if="heroMedia" :media="heroMedia" :label="t('profile.sound_engineer_page.listen', { name: page.display_name })" @play="openMedia" />
        </section>

        <div class="grid gap-5 md:gap-6 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
          <div class="flex flex-col gap-5 md:gap-6 min-w-0">
            <section v-if="page.before_after.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
                <i class="pi pi-volume-up text-surface-400" aria-hidden="true" />{{ t('profile.sound_engineer_page.before_after') }}
              </h2>
              <div class="grid gap-3 md:grid-cols-2">
                <ServiceAudioPlayer v-for="audio in page.before_after" :key="audio.audio_id" :service-id="page.id" :audio="audio" />
              </div>
            </section>

            <section v-if="page.offers.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-1 flex items-center gap-2">
                <i class="pi pi-wallet text-surface-400" aria-hidden="true" />{{ t('profile.sound_engineer_page.offers') }}
              </h2>
              <ul class="divide-y divide-dashed divide-surface-200 dark:divide-surface-700">
                <li v-for="offer in page.offers" :key="offer.id" class="flex justify-between gap-4 py-3.5">
                  <div class="min-w-0">
                    <p class="text-surface-900 dark:text-surface-0">{{ offer.title }}</p>
                    <p v-if="offer.description" class="text-sm text-surface-500 dark:text-surface-400">{{ offer.description }}</p>
                  </div>
                  <p class="shrink-0 text-right">
                    <span class="block font-bold text-surface-900 dark:text-surface-0">{{ formatLessonPrice(offer.price) }}</span>
                    <span class="block text-xs text-surface-500 dark:text-surface-400">{{ t(UNIT_KEYS[offer.unit]) }}</span>
                  </p>
                </li>
              </ul>
            </section>

            <section v-if="page.description" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
                <i class="pi pi-align-left text-surface-400" aria-hidden="true" />{{ t('profile.sound_engineer_page.how') }}
              </h2>
              <div class="text-surface-700 dark:text-surface-300 whitespace-pre-line">{{ page.description }}</div>
            </section>

            <div v-if="page.places.length || page.gear.length" class="grid gap-5 md:gap-6 md:grid-cols-2">
              <ServicePlacesCard v-if="page.places.length" :title="t('profile.teacher_page.where')" :places="placeLines" />
              <section v-if="page.gear.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
                  <i class="pi pi-box text-surface-400" aria-hidden="true" />{{ t('profile.sound_engineer_page.gear') }}
                </h2>
                <ul class="flex flex-wrap gap-2">
                  <li v-for="item in page.gear" :key="item" class="h-8 px-3 inline-flex items-center rounded-lg bg-surface-100 dark:bg-surface-800 text-sm font-medium text-surface-700 dark:text-surface-200">
                    {{ item }}
                  </li>
                </ul>
              </section>
            </div>

            <section v-if="otherMedia.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
                <i class="pi pi-video text-surface-400" aria-hidden="true" />{{ t('profile.sound_engineer_page.media') }}
              </h2>
              <div class="grid gap-4 sm:grid-cols-2">
                <ServiceHeroMedia v-for="item in otherMedia" :key="item.id" :media="item" :label="item.title || t('profile.media.play_untitled')" @play="openMedia" />
              </div>
            </section>
          </div>

          <div class="flex flex-col gap-5 md:gap-6 lg:sticky lg:top-24">
            <!-- The contact and the facts, in view while the visitor reads -->
            <aside class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 flex flex-col gap-4">
              <div class="flex items-center gap-3">
                <MemberAvatar :page="page" size="w-11! h-11!" />
                <p class="font-semibold text-surface-900 dark:text-surface-0">{{ page.display_name }}</p>
              </div>
              <Button v-if="isOwnPage" :label="t('profile.sound_engineer_page.edit')" icon="pi pi-pencil" class="w-full" @click="showForm = true" />
              <Button v-else :label="t('profile.teacher_page.ask_question')" icon="pi pi-envelope" severity="info" class="w-full" @click="openMessage('')" />
              <dl v-if="hasFacts" class="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-3 text-sm border-t border-surface-200 dark:border-surface-700 pt-4">
                <template v-if="page.styles.length">
                  <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.styles') }}</dt>
                  <dd class="flex flex-wrap gap-1.5">
                    <Tag v-for="style in page.styles" :key="style.id" :value="style.name" severity="secondary" />
                  </dd>
                </template>
                <template v-if="page.turnaround_days">
                  <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.sound_engineer_page.turnaround') }}</dt>
                  <dd class="text-surface-900 dark:text-surface-0">{{ t('profile.sound_engineer_page.working_days', page.turnaround_days) }}</dd>
                </template>
                <template v-if="page.formats.length">
                  <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.sound_engineer_page.formats') }}</dt>
                  <dd class="text-surface-900 dark:text-surface-0">{{ formatsLine }}</dd>
                </template>
              </dl>
            </aside>

            <!-- « Qui est Julien ? »: the member behind the service -->
            <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 flex flex-col gap-3">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0">{{ t('profile.sound_engineer_page.who', { name: page.display_name }) }}</h2>
              <div class="flex items-center gap-3">
                <MemberAvatar :page="page" size="w-11! h-11!" />
                <div class="min-w-0">
                  <p class="font-semibold text-surface-900 dark:text-surface-0">{{ page.display_name }}</p>
                  <p class="text-sm text-surface-500 dark:text-surface-400">{{ memberLine }}</p>
                </div>
              </div>
              <p v-if="page.bio" class="text-sm text-surface-700 dark:text-surface-300 whitespace-pre-line">{{ page.bio }}</p>
              <Button
                as="router-link"
                :to="{ name: 'app_user_public_profile', params: { username: page.username } }"
                :label="t('profile.common.view_profile')"
                severity="secondary"
                outlined
                class="w-full"
              />
            </section>
          </div>
        </div>

        <ServicePhoneBar v-if="!isOwnPage">
          <div v-if="leadOffer" class="leading-tight">
            <span class="block font-extrabold text-surface-900 dark:text-surface-0">{{ formatLessonPrice(leadOffer.price) }}</span>
            <span class="block text-xs text-surface-500 dark:text-surface-400">{{ t(UNIT_KEYS[leadOffer.unit]) }}</span>
          </div>
          <Button :label="t('profile.teacher_page.ask_question')" icon="pi pi-envelope" severity="info" class="flex-1" @click="openMessage('')" />
        </ServicePhoneBar>
      </div>
    </ServicePageStates>

    <MediaPlayerDialog v-model:visible="showPlayer" :media="playingMedia" />

    <EditSoundEngineeringDialog v-if="page && isOwnPage" v-model:visible="showForm" :service-id="page.id" @saved="refreshPage" @changed="refreshPage" />

    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="contactRecipient"
      :initial-content="initialMessage"
      @sign-in-required="handleContactSignInRequired"
    />
    <AuthRequiredModal v-model:visible="showAuthModal" :message="authModalMessage" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import soundEngineerApi from '../../../api/service/soundEngineer.js'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import MediaPlayerDialog from '../../../components/Media/MediaPlayerDialog.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import MemberAvatar from '../../../components/Service/Page/MemberAvatar.vue'
import ServiceAudioPlayer from '../../../components/Service/Page/ServiceAudioPlayer.vue'
import ServiceHeroMedia from '../../../components/Service/Page/ServiceHeroMedia.vue'
import ServiceKeyNumbers from '../../../components/Service/Page/ServiceKeyNumbers.vue'
import ServicePageStates from '../../../components/Service/Page/ServicePageStates.vue'
import ServicePageTopBar from '../../../components/Service/Page/ServicePageTopBar.vue'
import ServicePhoneBar from '../../../components/Service/Page/ServicePhoneBar.vue'
import ServicePlacesCard from '../../../components/Service/Page/ServicePlacesCard.vue'
import EditSoundEngineeringDialog from '../../../components/Service/SoundEngineering/EditSoundEngineeringDialog.vue'
import ShareButton from '../../../components/ShareButton.vue'
import ProfileDraftBar from '../../../components/User/Profile/ProfileDraftBar.vue'
import ProfilePrivateBar from '../../../components/User/Profile/ProfilePrivateBar.vue'
import {
  useKeepProfileContactDraft,
  useProfileContactDraftResumer
} from '../../../composables/useContactDraft.js'
import { useServicePageBack } from '../../../composables/useServicePageBack.js'
import {
  FORMAT_KEYS,
  PLACE_KEYS,
  SPECIALITY_KEYS,
  UNIT_KEYS
} from '../../../constants/soundEngineering.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { isPrivateProfileError } from '../../../utils/profileError.js'
import {
  DELIVERY_FORMATS,
  headlineOffer,
  orderedSpecialities,
  placesSummary
} from '../../../utils/soundEngineerPage.js'
import { formatLessonPrice } from '../../../utils/teacherPage.js'

/** A sound engineer's page (#1410), on the template the lessons page shares. */
const route = useRoute()
const { t, locale } = useI18n()
const userSecurityStore = useUserSecurityStore()
const handleBack = useServicePageBack()
const keepProfileContactDraft = useKeepProfileContactDraft()

const page = ref(null)
const isLoading = ref(true)
const notFound = ref(false)
const isPrivate = ref(false)
const showForm = ref(false)
const showPlayer = ref(false)
const playingMedia = ref(null)
const showMessageModal = ref(false)
const showAuthModal = ref(false)
const authModalMessage = ref('')
const contactRecipient = ref(null)
const initialMessage = ref('')

const isOwnPage = computed(() =>
  Boolean(page.value && userSecurityStore.userProfile?.id === page.value.user_id)
)
const specialities = computed(() => orderedSpecialities(page.value?.specialities))
const places = computed(() => placesSummary(page.value?.places))
const leadOffer = computed(() => headlineOffer(page.value?.offers))
const heroMedia = computed(() => page.value?.media?.[0] ?? null)
const otherMedia = computed(() => page.value?.media?.slice(1) ?? [])
const shareUrl = computed(() => window.location.href)

function joinList(items, type) {
  return new Intl.ListFormat(locale.value, { type }).format(items)
}

const crumbs = computed(() => [
  { label: t('profile.sound_engineer_page.engineers'), to: { name: 'app_search_sound_engineer' } },
  ...(specialities.value.length ? [{ label: t(SPECIALITY_KEYS[specialities.value[0]]) }] : []),
  ...(places.value.city ? [{ label: places.value.city, current: true }] : [])
])

/** « Studio à Forest, ou à distance », « Se déplace jusqu'à 30 km », « 8 ans de studio ». */
const facts = computed(() => {
  const items = []
  const where = []
  if (places.value.studioCity)
    where.push(t('profile.sound_engineer_page.studio_in', { city: places.value.studioCity }))
  if (places.value.remote) where.push(t('profile.sound_engineer_page.remotely'))
  if (where.length)
    items.push({ key: 'where', icon: 'pi pi-map-marker', text: joinList(where, 'disjunction') })
  if (places.value.travels) {
    items.push({
      key: 'travel',
      icon: 'pi pi-car',
      text: places.value.travelRadius
        ? t('profile.sound_engineer_page.travels_up_to', { radius: places.value.travelRadius })
        : t('profile.sound_engineer_page.travels')
    })
  }
  if (page.value.years_of_experience) {
    items.push({
      key: 'years',
      icon: 'pi pi-star',
      text: t('profile.sound_engineer_page.years', page.value.years_of_experience)
    })
  }
  return items
})

const keyNumbers = computed(() => {
  const items = []
  if (leadOffer.value) {
    items.push({
      key: 'price',
      value: formatLessonPrice(leadOffer.value.price),
      label: t(UNIT_KEYS[leadOffer.value.unit])
    })
  }
  if (page.value.turnaround_days) {
    items.push({
      key: 'turnaround',
      value: t('profile.sound_engineer_page.days', page.value.turnaround_days),
      label: t('profile.sound_engineer_page.usual_turnaround')
    })
  }
  if (page.value.revisions_included !== null) {
    items.push({
      key: 'revisions',
      value: String(page.value.revisions_included),
      label: t('profile.sound_engineer_page.revisions_included', page.value.revisions_included)
    })
  }
  return items
})

const placeLines = computed(() =>
  page.value.places.map((place) => ({
    icon: PLACE_ICONS[place.type],
    label: t(PLACE_KEYS[place.type]),
    detail:
      place.type === 'at_client_place' && place.radius
        ? t('profile.sound_engineer_page.travels_up_to', { radius: place.radius })
        : place.type === 'remote'
          ? t('profile.sound_engineer_page.remote_detail')
          : (place.city ?? '')
  }))
)
const PLACE_ICONS = {
  at_my_place: 'pi pi-building',
  at_client_place: 'pi pi-car',
  remote: 'pi pi-cloud-upload'
}

const hasFacts = computed(
  () => page.value.styles.length || page.value.turnaround_days || page.value.formats.length
)
const formatsLine = computed(() =>
  joinList(
    DELIVERY_FORMATS.filter((format) => page.value.formats.includes(format)).map((format) =>
      t(FORMAT_KEYS[format])
    ),
    'unit'
  )
)

/** « Guitariste, membre depuis 2014 ». */
const memberLine = computed(() => {
  const since = t('profile.teacher_page.member_since', {
    year: new Date(page.value.member_since).getFullYear()
  })
  const instrument = page.value.musician_instruments?.[0]?.instrument_name
  return instrument
    ? `${instrument}, ${since.charAt(0).toLocaleLowerCase(locale.value)}${since.slice(1)}`
    : since
})

useTitle(
  computed(() =>
    page.value
      ? t('profile.sound_engineer_page.page_title', { name: page.value.display_name })
      : t('profile.sound_engineer_page.page_title_default')
  ),
  { restoreOnUnmount: false }
)

async function loadPage() {
  isLoading.value = true
  notFound.value = false
  isPrivate.value = false
  try {
    page.value = await soundEngineerApi.getPage(route.params.username)
  } catch (error) {
    page.value = null
    isPrivate.value = isPrivateProfileError(error)
    notFound.value = !isPrivate.value
  } finally {
    isLoading.value = false
  }
}

// A quiet refetch, after its owner changed something: the page stays on screen.
function refreshPage() {
  soundEngineerApi
    .getPage(route.params.username)
    .then((data) => {
      page.value = data
    })
    .catch(() => {})
}

function openMedia(item) {
  playingMedia.value = item
  showPlayer.value = true
}

// A visitor writes first (#1305): « Envoyer » asks them to sign in, and this page gives the message back.
function openMessage(content) {
  contactRecipient.value = { id: page.value.user_id, username: page.value.username }
  initialMessage.value = content
  showMessageModal.value = true
}

function handleContactSignInRequired({ content }) {
  authModalMessage.value = keepProfileContactDraft({ profile: page.value, content })
  showAuthModal.value = true
}

useProfileContactDraftResumer(
  { isLoading, loadedProfile: () => (notFound.value || isPrivate.value ? null : page.value) },
  ({ content }) => openMessage(content)
)

watch(
  () => route.params.username,
  (username) => {
    if (username) loadPage()
  }
)

onMounted(loadPage)
</script>
