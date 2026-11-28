<template>
  <div class="py-6 md:py-10" :class="{ 'max-md:pb-28': page && !isOwnPage }">
    <ServicePageStates
      :is-loading="isLoading"
      :is-private="isPrivate"
      :not-found="notFound"
      :not-found-title="t('profile.service_page.not_found_title')"
      :not-found-help="t('profile.producer_page.not_found_help')"
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
          :icon="`${SERVICE_KINDS.production.icon} text-fuchsia-500`"
          :kind-label="t(SERVICE_KINDS.production.labelKey)"
          :specialities="specialities.map((speciality) => t(PRODUCTION_SPECIALITY_KEYS[speciality]))"
          :title="page.headline || t('profile.producer_page.title_default', { name: page.display_name })"
          :facts="facts"
          :key-numbers="keyNumbers"
          :hero-media="heroMedia"
          :hero-label="t('profile.producer_page.listen', { name: page.display_name })"
          :share-title="t('profile.producer_page.share_title', { name: page.display_name })"
          @edit="showForm = true"
          @quote="showQuoteDialog = true"
          @question="openMessage('')"
          @play="openMedia"
        />

        <div class="grid gap-5 md:gap-6 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
          <div class="flex flex-col gap-5 md:gap-6 min-w-0">
            <ServiceWorksCredits
              v-if="page.works.length"
              :title="t('profile.producer_page.works', { name: page.display_name })"
              :works="page.works"
              :username="page.username"
              :reportable="!isOwnPage"
              icon="pi pi-headphones"
              @play="openMedia"
            />

            <ServiceOffersCard v-if="page.offers.length" :offers="page.offers" :unit-keys="UNIT_KEYS" />

            <ServiceTextCard v-if="page.description" :title="t('profile.service_page.how')" :text="page.description" />

            <ServicePlacesCard v-if="page.places.length" :title="t('profile.teacher_page.where')" :places="placeLines(page.places, PLACES, t)" />

            <ServiceMediaCard v-if="otherMedia.length" :media="otherMedia" @play="openMedia" />
          </div>

          <div class="flex flex-col gap-5 md:gap-6 lg:sticky lg:top-24">
            <ServiceContactCard
              :page="page"
              :is-own-page="isOwnPage"
              :facts="[]"
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

    <EditProductionDialog v-if="page && isOwnPage" v-model:visible="showForm" :service-id="page.id" @saved="refreshPage" @changed="refreshPage" />

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
import producerApi from '../../../api/service/producer.js'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import MediaPlayerDialog from '../../../components/Media/MediaPlayerDialog.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
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
import EditProductionDialog from '../../../components/Service/Production/EditProductionDialog.vue'
import QuoteRequestDialog from '../../../components/Service/QuoteRequestDialog.vue'
import ServiceWorksCredits from '../../../components/Service/Works/ServiceWorksCredits.vue'
import ProfileDraftBar from '../../../components/User/Profile/ProfileDraftBar.vue'
import ProfilePrivateBar from '../../../components/User/Profile/ProfilePrivateBar.vue'
import { useServicePage } from '../../../composables/useServicePage.js'
import { useServicePageBack } from '../../../composables/useServicePageBack.js'
import { PRODUCTION_SPECIALITY_KEYS } from '../../../constants/production.js'
import { SERVICE_KINDS } from '../../../constants/serviceKinds.js'
import { PLACE_KEYS, UNIT_KEYS } from '../../../constants/soundEngineering.js'
import { headlineOffer, orderedSpecialities } from '../../../utils/servicePage.js'
import { placeLines, placesSummary } from '../../../utils/servicePlaces.js'
import { formatLessonPrice } from '../../../utils/teacherPage.js'

/** A producer's page (#1413), on the template the other service pages share: their styles up front, works as credits. */
const SERVICE_KIND = 'production'
const SPECIALITIES = Object.keys(PRODUCTION_SPECIALITY_KEYS)
const PLACES = {
  placeKeys: PLACE_KEYS,
  remoteDetailKey: 'profile.producer_page.remote_detail'
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
} = useServicePage({ kind: SERVICE_KIND, fetchPage: producerApi.getPage })

const specialities = computed(() => orderedSpecialities(SPECIALITIES, page.value?.specialities))
const places = computed(() => placesSummary(page.value?.places))
const leadOffer = computed(() => headlineOffer(page.value?.offers))
const heroMedia = computed(() => page.value?.media?.[0] ?? null)
const otherMedia = computed(() => page.value?.media?.slice(1) ?? [])

const crumbs = computed(() => [
  { label: t('profile.producer_page.plural'), to: { name: SERVICE_KINDS.production.search.route } },
  ...(specialities.value.length
    ? [{ label: t(PRODUCTION_SPECIALITY_KEYS[specialities.value[0]]) }]
    : []),
  ...(places.value.city ? [{ label: places.value.city, current: true }] : [])
])

/** « Hip-hop, R&B » first, then « Studio à Paris, ou à distance », « Se déplace », « 10 ans d'expérience ». */
const facts = computed(() => {
  const items = []
  if (page.value.styles.length) {
    items.push({
      key: 'styles',
      icon: 'pi pi-tag',
      text: page.value.styles.map((style) => style.name).join(', ')
    })
  }
  const where = []
  if (places.value.studioCity)
    where.push(t('profile.service_page.studio_in', { city: places.value.studioCity }))
  if (places.value.remote) where.push(t('profile.producer_page.remotely'))
  if (where.length) {
    items.push({
      key: 'where',
      icon: 'pi pi-map-marker',
      text: new Intl.ListFormat(locale.value, { type: 'disjunction' }).format(where)
    })
  }
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
      text: t('profile.producer_page.years', page.value.years_of_experience)
    })
  }
  return items
})

const keyNumbers = computed(() =>
  leadOffer.value
    ? [
        {
          key: 'price',
          value: formatLessonPrice(leadOffer.value.price),
          label: t(UNIT_KEYS[leadOffer.value.unit])
        }
      ]
    : []
)

useTitle(
  computed(() =>
    page.value
      ? t('profile.producer_page.page_title', { name: page.value.display_name })
      : t('profile.producer_page.page_title_default')
  ),
  { restoreOnUnmount: false }
)
</script>
