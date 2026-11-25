<template>
  <div class="py-6 md:py-10" :class="{ 'max-md:pb-28': page && !isOwnPage }">
    <ServicePageStates
      :is-loading="isLoading"
      :is-private="isPrivate"
      :not-found="notFound"
      :not-found-title="t('profile.service_page.not_found_title')"
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

        <ServicePageHeader
          :page="page"
          :is-own-page="isOwnPage"
          icon="pi pi-sliders-v text-violet-500"
          :kind-label="t('profile.sound_engineer_page.kind')"
          :specialities="specialities.map((speciality) => t(SPECIALITY_KEYS[speciality]))"
          :title="page.headline || t('profile.sound_engineer_page.title_default', { name: page.display_name })"
          :facts="facts"
          :key-numbers="keyNumbers"
          :hero-media="heroMedia"
          :hero-label="t('profile.sound_engineer_page.listen', { name: page.display_name })"
          :share-title="t('profile.sound_engineer_page.share_title', { name: page.display_name })"
          @edit="showForm = true"
          @quote="showQuoteDialog = true"
          @question="openMessage('')"
          @play="openMedia"
        />

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

            <ServiceWorksCredits
              v-if="page.works.length"
              :title="t('profile.sound_engineer_page.works', { name: page.display_name })"
              :works="page.works"
              :username="page.username"
              :reportable="!isOwnPage"
              icon="pi pi-headphones"
              @play="openMedia"
            />

            <ServiceOffersCard v-if="page.offers.length" :offers="page.offers" :unit-keys="UNIT_KEYS" />

            <ServiceTextCard v-if="page.description" :title="t('profile.service_page.how')" :text="page.description" />

            <div v-if="page.places.length || page.gear.length" class="grid gap-5 md:gap-6 md:grid-cols-2">
              <ServicePlacesCard v-if="page.places.length" :title="t('profile.teacher_page.where')" :places="placeLines(page.places, PLACES, t)" />
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

            <ServiceMediaCard v-if="otherMedia.length" :media="otherMedia" @play="openMedia" />
          </div>

          <div class="flex flex-col gap-5 md:gap-6 lg:sticky lg:top-24">
            <ServiceContactCard
              :page="page"
              :is-own-page="isOwnPage"
              :facts="contactFacts"
              @edit="showForm = true"
              @quote="showQuoteDialog = true"
              @question="openMessage('')"
            />
            <ServiceWhoCard :page="page" />
          </div>
        </div>

        <ServicePhoneBar v-if="!isOwnPage">
          <div v-if="leadOffer" class="leading-tight">
            <span class="block font-extrabold text-surface-900 dark:text-surface-0">{{ formatLessonPrice(leadOffer.price) }}</span>
            <span class="block text-xs text-surface-500 dark:text-surface-400">{{ t(UNIT_KEYS[leadOffer.unit]) }}</span>
          </div>
          <Button :label="t('profile.service_page.ask_quote')" icon="pi pi-send" severity="info" class="flex-1" @click="showQuoteDialog = true" />
        </ServicePhoneBar>
      </div>
    </ServicePageStates>

    <MediaPlayerDialog v-model:visible="showPlayer" :media="playingMedia" />

    <EditSoundEngineeringDialog v-if="page && isOwnPage" v-model:visible="showForm" :service-id="page.id" @saved="refreshPage" @changed="refreshPage" />

    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="contactRecipient"
      :initial-content="initialMessage"
      :contact-origin="page ? { serviceId: page.id } : null"
      @sign-in-required="handleContactSignInRequired"
    />
    <QuoteRequestDialog v-if="page && !isOwnPage" v-model:visible="showQuoteDialog" :page="page" :kind="SERVICE_KIND" />
    <AuthRequiredModal v-model:visible="showAuthModal" :message="authModalMessage" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import soundEngineerApi from '../../../api/service/soundEngineer.js'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import MediaPlayerDialog from '../../../components/Media/MediaPlayerDialog.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ServiceAudioPlayer from '../../../components/Service/Page/ServiceAudioPlayer.vue'
import ServiceContactCard from '../../../components/Service/Page/ServiceContactCard.vue'
import ServiceMediaCard from '../../../components/Service/Page/ServiceMediaCard.vue'
import ServiceOffersCard from '../../../components/Service/Page/ServiceOffersCard.vue'
import ServicePageHeader from '../../../components/Service/Page/ServicePageHeader.vue'
import ServicePageStates from '../../../components/Service/Page/ServicePageStates.vue'
import ServicePageTopBar from '../../../components/Service/Page/ServicePageTopBar.vue'
import ServicePhoneBar from '../../../components/Service/Page/ServicePhoneBar.vue'
import ServicePlacesCard from '../../../components/Service/Page/ServicePlacesCard.vue'
import ServiceTextCard from '../../../components/Service/Page/ServiceTextCard.vue'
import ServiceWhoCard from '../../../components/Service/Page/ServiceWhoCard.vue'
import QuoteRequestDialog from '../../../components/Service/QuoteRequestDialog.vue'
import EditSoundEngineeringDialog from '../../../components/Service/SoundEngineering/EditSoundEngineeringDialog.vue'
import ServiceWorksCredits from '../../../components/Service/Works/ServiceWorksCredits.vue'
import ProfileDraftBar from '../../../components/User/Profile/ProfileDraftBar.vue'
import ProfilePrivateBar from '../../../components/User/Profile/ProfilePrivateBar.vue'
import { useServicePage } from '../../../composables/useServicePage.js'
import { useServicePageBack } from '../../../composables/useServicePageBack.js'
import {
  FORMAT_KEYS,
  PLACE_KEYS,
  SPECIALITY_KEYS,
  UNIT_KEYS
} from '../../../constants/soundEngineering.js'
import { headlineOffer, orderedSpecialities } from '../../../utils/servicePage.js'
import { placeLines, placesSummary } from '../../../utils/servicePlaces.js'
import { DELIVERY_FORMATS, SPECIALITIES } from '../../../utils/soundEngineerPage.js'
import { formatLessonPrice } from '../../../utils/teacherPage.js'

/** A sound engineer's page (#1410), on the template the lessons page shares. */
const SERVICE_KIND = 'sound_engineering'
const PLACES = {
  placeKeys: PLACE_KEYS,
  remoteDetailKey: 'profile.sound_engineer_page.remote_detail'
}
const route = useRoute()
const { t, locale } = useI18n()
const handleBack = useServicePageBack()
const {
  page,
  isLoading,
  notFound,
  isPrivate,
  isOwnPage,
  showForm,
  showPlayer,
  playingMedia,
  showMessageModal,
  showQuoteDialog,
  showAuthModal,
  authModalMessage,
  contactRecipient,
  initialMessage,
  refreshPage,
  openMedia,
  openMessage,
  handleContactSignInRequired
} = useServicePage({ kind: SERVICE_KIND, fetchPage: soundEngineerApi.getPage })

const specialities = computed(() => orderedSpecialities(SPECIALITIES, page.value?.specialities))
const places = computed(() => placesSummary(page.value?.places))
const leadOffer = computed(() => headlineOffer(page.value?.offers))
const heroMedia = computed(() => page.value?.media?.[0] ?? null)
const otherMedia = computed(() => page.value?.media?.slice(1) ?? [])

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
    where.push(t('profile.service_page.studio_in', { city: places.value.studioCity }))
  if (places.value.remote) where.push(t('profile.sound_engineer_page.remotely'))
  if (where.length)
    items.push({ key: 'where', icon: 'pi pi-map-marker', text: joinList(where, 'disjunction') })
  if (places.value.travels) {
    items.push({
      key: 'travel',
      icon: 'pi pi-car',
      text: places.value.travelRadius
        ? t('profile.service_page.travels_up_to', { radius: places.value.travelRadius })
        : t('profile.service_page.travels')
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
      value: t('profile.service_page.days', page.value.turnaround_days),
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

const contactFacts = computed(() => [
  ...(page.value.styles.length
    ? [
        {
          key: 'styles',
          label: t('profile.teacher_page.styles'),
          tags: page.value.styles.map((style) => style.name)
        }
      ]
    : []),
  ...(page.value.turnaround_days
    ? [
        {
          key: 'turnaround',
          label: t('profile.sound_engineer_page.turnaround'),
          text: t('profile.sound_engineer_page.working_days', page.value.turnaround_days)
        }
      ]
    : []),
  ...(page.value.formats.length
    ? [
        {
          key: 'formats',
          label: t('profile.sound_engineer_page.formats'),
          text: joinList(
            DELIVERY_FORMATS.filter((format) => page.value.formats.includes(format)).map((format) =>
              t(FORMAT_KEYS[format])
            ),
            'unit'
          )
        }
      ]
    : [])
])

useTitle(
  computed(() =>
    page.value
      ? t('profile.sound_engineer_page.page_title', { name: page.value.display_name })
      : t('profile.sound_engineer_page.page_title_default')
  ),
  { restoreOnUnmount: false }
)
</script>
